<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { angka, tanggalLengkap } from '../../utils/format'
import { unduhCsv } from '../../utils/download'

const mulai = ref(`${new Date().getFullYear()}-01-01`)
const sampai = ref(() => '') // set di onMounted
const loading = ref(true)
const busy = ref('')
const error = ref('')
const summary = ref(null)
const regions = ref([])
const parameters = ref([])

const periodeLabel = computed(() => {
  if (!summary.value) return ''
  return `Periode ${tanggalLengkap(summary.value.periode.mulai)} – ${tanggalLengkap(summary.value.periode.sampai)}`
})

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const params = { mulai: mulai.value, sampai: sampai.value }
    const [s, r, p] = await Promise.all([
      client.get('/dashboard/summary', { params }),
      client.get('/dashboard/reactive-by-region', { params }),
      client.get('/dashboard/reactive-by-parameter', { params }),
    ])
    summary.value = s.data
    regions.value = r.data.wilayah.filter((w) => w.jumlah_diuji > 0)
    parameters.value = p.data.parameters
  } catch (e) {
    error.value = 'Gagal memuat laporan.'
  } finally {
    loading.value = false
  }
}

async function unduh(jenis, namaFile) {
  busy.value = jenis
  error.value = ''
  try {
    await unduhCsv(`/export/${jenis}`, { mulai: mulai.value, sampai: sampai.value }, namaFile)
  } catch {
    error.value = 'Gagal mengunduh laporan.'
  } finally {
    busy.value = ''
  }
}

const maxParam = computed(() => Math.max(...parameters.value.map((x) => x.jumlah_reaktif), 1))

onMounted(() => {
  const now = new Date()
  const pad = (x) => String(x).padStart(2, '0')
  sampai.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
  muat()
})
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Laporan Periodik</h1>
        <p class="page-subtitle">{{ periodeLabel }}</p>
      </div>
      <div class="page-actions">
        <button class="btn" :disabled="loading" @click="muat">Muat ulang</button>
      </div>
    </div>

    <div class="card" style="padding:14px 18px">
      <div class="filter-bar" style="margin-bottom:0">
        <div class="form-group">
          <label>Dari tanggal</label>
          <input v-model="mulai" type="date" class="form-control" style="width:auto" @change="muat" />
        </div>
        <div class="form-group">
          <label>Sampai tanggal</label>
          <input v-model="sampai" type="date" class="form-control" style="width:auto" @change="muat" />
        </div>
      </div>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>
    <div v-if="loading" class="loading">Menyusun laporan…</div>

    <template v-if="summary">
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label">Siswa diperiksa</div>
          <div class="stat-value">{{ angka(summary.cards.siswa_diperiksa) }}</div>
          <div class="stat-hint">{{ angka(summary.cards.sekolah_terjangkau) }} sekolah terjangkau</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Hasil reaktif</div>
          <div class="stat-value text-danger">{{ angka(summary.cards.hasil_reaktif) }}</div>
          <div class="stat-hint">{{ summary.cards.persentase_reaktif }}% dari pemeriksaan</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Uji konfirmasi</div>
          <div class="stat-value text-warning">{{ angka(summary.cards.sudah_uji_konfirmasi) }}</div>
          <div class="stat-hint">{{ angka(summary.cards.sisa_menunggu_lab) }} menunggu jadwal lab</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Petugas aktif</div>
          <div class="stat-value">{{ angka(summary.cards.petugas_aktif) }}</div>
          <div class="stat-hint">di {{ angka(summary.cards.kab_kota_petugas) }} kab/kota</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="card">
          <h2>Reaktif per wilayah ({{ regions.length }})</h2>
          <table class="data">
            <thead><tr><th>Kabupaten/Kota</th><th>Diperiksa</th><th>Reaktif</th><th>%</th></tr></thead>
            <tbody>
              <tr v-for="w in regions" :key="w.kab_kota">
                <td>{{ w.kab_kota }}</td>
                <td>{{ angka(w.jumlah_diuji) }}</td>
                <td class="text-danger">{{ angka(w.jumlah_reaktif) }}</td>
                <td>{{ w.persentase_reaktif.toLocaleString('id-ID') }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="card">
          <h2>Reaktif per parameter</h2>
          <div v-for="p in parameters" :key="p.parameter" class="bar-row">
            <div class="bar-label">{{ p.label }}</div>
            <div class="bar-track">
              <div class="bar-fill" :style="{ width: (p.jumlah_reaktif / maxParam) * 100 + '%', background: '#be2b22' }"></div>
            </div>
            <div class="bar-value muted">{{ angka(p.jumlah_reaktif) }}</div>
          </div>
          <p class="card-note">Satu siswa dapat reaktif pada lebih dari satu parameter.</p>
        </div>
      </div>

      <div class="card">
        <h2>Unduh laporan CSV</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-primary" :disabled="busy === 'regions'" @click="unduh('regions', `laporan-wilayah-${mulai}-${sampai}.csv`)">
            {{ busy === 'regions' ? 'Mengunduh…' : 'Rekap wilayah' }}
          </button>
          <button class="btn btn-primary" :disabled="busy === 'schools'" @click="unduh('schools', `laporan-sekolah-${mulai}-${sampai}.csv`)">
            {{ busy === 'schools' ? 'Mengunduh…' : 'Rekap sekolah' }}
          </button>
          <button class="btn btn-primary" :disabled="busy === 'parameters'" @click="unduh('parameters', `laporan-parameter-${mulai}-${sampai}.csv`)">
            {{ busy === 'parameters' ? 'Mengunduh…' : 'Rekap parameter' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>