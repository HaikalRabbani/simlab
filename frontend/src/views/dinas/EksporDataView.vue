<script setup>
import { ref } from 'vue'
import { unduhCsv } from '../../utils/download'
import { tanggalInput } from '../../utils/format'

const mulai = ref(`${new Date().getFullYear()}-01-01`)
const sampai = ref(tanggalInput())
const error = ref('')
const busy = ref('')

async function unduh(jenis, namaFile) {
  busy.value = jenis
  error.value = ''
  try {
    await unduhCsv(`/export/${jenis}`, { mulai: mulai.value, sampai: sampai.value }, namaFile)
  } catch {
    error.value = 'Gagal mengunduh data. Coba lagi.'
  } finally {
    busy.value = ''
  }
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Ekspor Data</h1>
        <p class="page-subtitle">Unduh data agregat skrining sebagai CSV (Microsoft Excel / aplikasi spreadsheet)</p>
      </div>
    </div>

    <div class="card">
      <h2>Rentang periode</h2>
      <div class="filter-bar">
        <div class="form-group">
          <label>Dari tanggal</label>
          <input v-model="mulai" type="date" class="form-control" style="width:auto" />
        </div>
        <div class="form-group">
          <label>Sampai tanggal</label>
          <input v-model="sampai" type="date" class="form-control" style="width:auto" />
        </div>
      </div>
      <p v-if="error" class="banner banner-warning" style="margin:0 0 12px">{{ error }}</p>

      <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
        <div class="card" style="margin:0">
          <h2>Rekap per kabupaten/kota</h2>
          <p class="card-note">Jumlah diperiksa & reaktif per wilayah.</p>
          <button class="btn btn-primary" style="margin-top:8px" :disabled="busy === 'regions'" @click="unduh('regions', 'rekap-per-wilayah.csv')">
            {{ busy === 'regions' ? 'Mengunduh…' : 'Unduh CSV' }}
          </button>
        </div>
        <div class="card" style="margin:0">
          <h2>Daftar sekolah</h2>
          <p class="card-note">Status skrining & tindak lanjut per sekolah.</p>
          <button class="btn btn-primary" style="margin-top:8px" :disabled="busy === 'schools'" @click="unduh('schools', 'daftar-sekolah.csv')">
            {{ busy === 'schools' ? 'Mengunduh…' : 'Unduh CSV' }}
          </button>
        </div>
        <div class="card" style="margin:0">
          <h2>Reaktif per parameter</h2>
          <p class="card-note">Rekap 7 parameter alat uji.</p>
          <button class="btn btn-primary" style="margin-top:8px" :disabled="busy === 'parameters'" @click="unduh('parameters', 'reaktif-per-parameter.csv')">
            {{ busy === 'parameters' ? 'Mengunduh…' : 'Unduh CSV' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
