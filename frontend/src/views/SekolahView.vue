<script setup>
import { ref, onMounted, computed } from 'vue'
import client from '../api/client'
import { useAuthStore } from '../stores/auth'
import { angka, tanggalLengkap } from '../utils/format'
import { unduhCsv } from '../utils/download'
import Badge from '../components/Badge.vue'

const auth = useAuthStore()
const isDinas = computed(() => auth.isDinas)

const filters = ref({ kab_kota: '', jenjang: '', status: '', search: '' })
const kabOptions = ref([])
const loading = ref(true)
const error = ref('')
const summary = ref(null)
const schools = ref([])

const rowsTampil = computed(() => {
  if (!isDinas.value || !filters.value.status) return schools.value
  return schools.value.filter((s) => s.status_skrining === filters.value.status)
})

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const params = {
      ...(filters.value.kab_kota && { kab_kota: filters.value.kab_kota }),
      ...(filters.value.jenjang && { jenjang: filters.value.jenjang }),
      ...(filters.value.search && { search: filters.value.search }),
    }
    if (isDinas.value) {
      const { data } = await client.get('/schools/status', { params })
      summary.value = data.summary
      schools.value = data.schools
      kabOptions.value = [...new Set(data.schools.map((s) => s.kab_kota))].sort()
    } else {
      const { data } = await client.get('/schools', { params: { ...params, per_page: 100 } })
      schools.value = data.data
      kabOptions.value = [...new Set(data.data.map((s) => s.kab_kota))].sort()
    }
  } catch (e) {
    error.value = 'Gagal memuat data sekolah.'
  } finally {
    loading.value = false
  }
}

onMounted(muat)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Daftar Sekolah</h1>
        <p v-if="isDinas && summary" class="page-subtitle">
          {{ angka(summary.total_sekolah) }} sekolah sasaran · {{ angka(summary.sudah_diskrining) }} sudah diskrining, {{ angka(summary.belum_terjadwal) }} belum terjadwal
        </p>
        <p v-else class="page-subtitle">Sekolah pada wilayah tugas Anda (hanya baca)</p>
      </div>
      <div class="page-actions">
        <RouterLink v-if="isDinas" :to="{ name: 'jadwal-skrining' }" class="btn">Atur jadwal kunjungan</RouterLink>
        <button v-if="isDinas" class="btn" @click="unduhCsv('/export/schools', {}, 'daftar-sekolah.csv')">Ekspor daftar (CSV)</button>
      </div>
    </div>

    <div class="card" style="padding:14px 18px">
      <div class="filter-bar">
        <div class="form-group">
          <label>Kabupaten/Kota</label>
          <select v-model="filters.kab_kota" class="form-control">
            <option value="">Semua</option>
            <option v-for="k in kabOptions" :key="k" :value="k">{{ k }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Jenjang</label>
          <select v-model="filters.jenjang" class="form-control">
            <option value="">Semua</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
            <option value="MA">MA</option>
          </select>
        </div>
        <div v-if="isDinas" class="form-group">
          <label>Status skrining</label>
          <select v-model="filters.status" class="form-control">
            <option value="">Semua</option>
            <option value="sudah_diskrining">Sudah diskrining</option>
            <option value="terjadwal">Terjadwal</option>
            <option value="belum_terjadwal">Belum terjadwal</option>
          </select>
        </div>
        <div class="form-group" style="flex:1;min-width:200px">
          <label>Cari sekolah atau NPSN</label>
          <input v-model="filters.search" class="form-control" placeholder="Ketik nama sekolah / NPSN…" @keyup.enter="muat" />
        </div>
        <button class="btn btn-primary" :disabled="loading" @click="muat">Terapkan</button>
      </div>
    </div>

    <div v-if="isDinas && summary" class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Sudah diskrining</div>
        <div class="stat-value">{{ angka(summary.sudah_diskrining) }}</div>
        <div class="stat-hint">{{ summary.total_sekolah ? Math.round((summary.sudah_diskrining / summary.total_sekolah) * 100) : 0 }}% dari sekolah sasaran</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Terjadwal bulan ini</div>
        <div class="stat-value">{{ angka(summary.terjadwal_bulan_ini) }}</div>
        <div class="stat-hint">jadwal kunjungan aktif</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Belum terjadwal</div>
        <div class="stat-value text-warning">{{ angka(summary.belum_terjadwal) }}</div>
        <div class="stat-hint">perlu koordinasi wilayah</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Temuan di atas 2%</div>
        <div class="stat-value text-danger">{{ angka(summary.temuan_di_atas_2) }}</div>
        <div class="stat-hint">direkomendasikan skrining ulang</div>
      </div>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>
    <div v-if="loading" class="loading">Memuat data…</div>

    <div class="card">
      <h2 v-if="isDinas">Sekolah yang sudah diskrining</h2>
      <h2 v-else>Sekolah pada wilayah tugas</h2>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Nama sekolah</th><th>NPSN</th><th>Kab/Kota</th><th>Jenjang</th>
              <template v-if="isDinas">
                <th>Skrining terakhir</th><th>Diuji</th><th>Reaktif</th><th>%</th><th>Tindak lanjut</th>
              </template>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in rowsTampil" :key="s.id">
              <td>{{ s.nama }}</td>
              <td>{{ s.npsn }}</td>
              <td>{{ s.kab_kota }}</td>
              <td>{{ s.jenjang }}</td>
              <template v-if="isDinas">
                <td>{{ s.tanggal_skrining_terakhir ? tanggalLengkap(s.tanggal_skrining_terakhir) : '—' }}</td>
                <td>{{ angka(s.jumlah_diuji) }}</td>
                <td class="text-danger">{{ angka(s.jumlah_reaktif) }}</td>
                <td>{{ s.persentase_reaktif.toLocaleString('id-ID') }}%</td>
                <td><Badge :text="s.tindak_lanjut" /></td>
              </template>
            </tr>
          </tbody>
        </table>
        <p v-if="rowsTampil.length === 0 && !loading" class="empty-state">Tidak ada sekolah yang cocok dengan filter.</p>
      </div>
      <p v-if="isDinas" class="card-note">Dinas melihat rekapitulasi per sekolah. Identitas siswa dengan hasil reaktif tidak ditampilkan di tingkat ini — hanya diakses petugas penginput dan pengawas wilayah yang berwenang, sesuai ketentuan perlindungan data pribadi.</p>
    </div>
  </div>
</template>
