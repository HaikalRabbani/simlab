<script setup>
import { ref, onMounted } from 'vue'
import client from '../../api/client'
import { hariTanggal, angka, tanggalLengkap } from '../../utils/format'
import Badge from '../../components/Badge.vue'

const STATUS_FILTERS = [
  { key: 'menunggu', label: 'Menunggu' },
  { key: 'disetujui', label: 'Disetujui' },
  { key: 'ditolak', label: 'Ditolak' },
]

const status = ref('menunggu')
const loading = ref(true)
const actionId = ref(null)
const error = ref('')
const items = ref([])
const detail = ref(null)

const labelStatus = { menunggu: 'Menunggu', disetujui: 'Disetujui', ditolak: 'Ditolak' }
const toneStatus = (s) => (s === 'menunggu' ? 'warning' : s === 'disetujui' ? 'success' : 'danger')

async function muat() {
  loading.value = true
  error.value = ''
  detail.value = null
  try {
    const { data } = await client.get('/correction-requests', {
      params: { status: status.value, per_page: 100 },
    })
    items.value = data.data || []
  } catch (e) {
    error.value = 'Gagal memuat pengajuan koreksi.'
  } finally {
    loading.value = false
  }
}

async function bukaDetail(item) {
  try {
    const { data } = await client.get(`/correction-requests/${item.id}`)
    detail.value = data
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal memuat detail.'
  }
}

async function putuskan(item, keputusan) {
  const catatan = keputusan === 'disetujui'
    ? window.prompt('Catatan peninjau (opsional):', '') || null
    : window.prompt('Alasan penolakan (opsional):', '') || null
  actionId.value = item.id
  error.value = ''
  try {
    await client.post(`/correction-requests/${item.id}/${keputusan}`, { catatan_peninjau: catatan })
    detail.value = null
    await muat()
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal memproses pengajuan.'
  } finally {
    actionId.value = null
  }
}

const LABEL_HASIL = { negatif: 'Negatif', positif: 'Positif', invalid: 'Invalid' }

onMounted(muat)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Persetujuan Koreksi</h1>
        <p class="page-subtitle">Pengajuan koreksi data pemeriksaan dari petugas di wilayah Anda</p>
      </div>
      <button class="btn" :disabled="loading" @click="muat">Muat ulang</button>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>

    <div class="card" style="padding:14px 18px">
      <div class="filter-bar" style="margin-bottom:0">
        <div class="form-group">
          <label>Status</label>
          <select v-model="status" class="form-control" style="min-width:180px" @change="muat">
            <option v-for="f in STATUS_FILTERS" :key="f.key" :value="f.key">{{ f.label }}</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="loading" class="loading">Memuat data…</div>

    <template v-for="item in items" :key="item.id">
      <div class="card">
        <div class="koreksi-head">
          <div>
            <b>{{ item.examination?.student?.nama }}</b>
            <div class="muted" style="font-size:12px">
              NISN {{ item.examination?.student?.nisn }} · {{ item.examination?.student?.kelas }}
              · diajukan {{ item.diajukan_oleh?.nama }} · {{ hariTanggal(item.created_at) }}
            </div>
          </div>
          <Badge :text="labelStatus[item.status] || item.status" :tone="toneStatus(item.status)" />
        </div>
        <div class="alasan-box">
          <b style="font-size:12px">Alasan:</b>
          <p style="margin:4px 0 0">{{ item.alasan }}</p>
        </div>
        <div class="koreksi-actions">
          <button class="btn" :disabled="actionId === item.id" @click="bukaDetail(item)">Lihat detail</button>
          <template v-if="item.status === 'menunggu'">
            <button class="btn btn-primary" :disabled="actionId === item.id" @click="putuskan(item, 'approve')">
              {{ actionId === item.id ? 'Memproses…' : '✓ Setujui' }}
            </button>
            <button class="btn btn-danger" :disabled="actionId === item.id" @click="putuskan(item, 'reject')">✕ Tolak</button>
          </template>
        </div>
      </div>
    </template>

    <div v-if="!loading && items.length === 0" class="card empty-state">
      Tidak ada pengajuan koreksi berstatus {{ labelStatus[status] || status }}.
    </div>

    <!-- Detail modal inline -->
    <div v-if="detail" class="card" style="border:2px solid var(--color-brand-accent)">
      <h2>Detail pemeriksaan — {{ detail.examination?.student?.nama }}</h2>
      <div class="info-grid">
        <div class="info-row"><span>NISN</span><b>{{ detail.examination?.student?.nisn }}</b></div>
        <div class="info-row"><span>Kelas</span><b>{{ detail.examination?.student?.kelas }}</b></div>
        <div class="info-row"><span>Tanggal lahir</span><b>{{ tanggalLengkap(detail.examination?.student?.tanggal_lahir) }}</b></div>
        <div class="info-row"><span>Waktu input</span><b>{{ hariTanggal(detail.examination?.waktu_input) }}</b></div>
        <div class="info-row"><span>Pendamping</span><b>{{ detail.examination?.pendamping }}</b></div>
        <div class="info-row"><span>Lot strip</span><b>{{ detail.examination?.strip_lot_code }}</b></div>
        <div class="info-row"><span>Rencana tindak lanjut</span><b>{{ detail.examination?.rencana_tindak_lanjut || '—' }}</b></div>
        <div class="info-row"><span>Kode segel</span><b>{{ detail.examination?.kode_segel || '—' }}</b></div>
      </div>
      <div class="table-wrap" style="margin-top:10px">
        <table class="data">
          <thead><tr><th>Parameter</th><th>Hasil</th></tr></thead>
          <tbody>
            <tr v-for="r in detail.examination?.examination_results" :key="r.id || r.parameter">
              <td>{{ r.parameter }}</td>
              <td>
                <Badge
                  :text="LABEL_HASIL[r.hasil] || r.hasil"
                  :tone="r.hasil === 'positif' ? 'danger' : r.hasil === 'negatif' ? 'success' : 'warning'"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <button class="btn" style="margin-top:12px" @click="detail = null">Tutup detail</button>
    </div>
  </div>
</template>

<style scoped>
.koreksi-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 10px;
}

.alasan-box {
  background: var(--color-page-bg-alt);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 12px;
}

.alasan-box p {
  font-size: 13px;
}

.koreksi-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 0 20px;
}
</style>