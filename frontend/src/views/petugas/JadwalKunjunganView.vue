<script setup>
import { ref, onMounted } from 'vue'
import client from '../../api/client'
import { hariTanggal, angka } from '../../utils/format'
import Badge from '../../components/Badge.vue'

const loading = ref(true)
const error = ref('')
const groups = ref([])

const labelSesi = { pagi: 'Sesi pagi', siang: 'Sesi siang' }
const labelStatus = { terjadwal: 'Terjadwal', berlangsung: 'Berlangsung', selesai: 'Selesai' }

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/schedules', { params: { per_page: 500 } })
    const byDate = {}
    for (const s of data.data) {
      const d = s.tanggal
      if (!byDate[d]) byDate[d] = []
      byDate[d].push(s)
    }
    groups.value = Object.entries(byDate).sort((a, b) => (a[0] < b[0] ? 1 : -1))
  } catch (e) {
    error.value = 'Gagal memuat jadwal kunjungan.'
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
        <h1>Jadwal Kunjungan</h1>
        <p class="page-subtitle">Sekolah yang harus Anda kunjungi</p>
      </div>
      <div class="page-actions">
        <button class="btn" :disabled="loading" @click="muat">Muat ulang</button>
      </div>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>
    <div v-if="loading" class="loading">Memuat data…</div>

    <template v-for="[tanggal, list] in groups" :key="tanggal">
      <div class="card">
        <h2>{{ hariTanggal(`${tanggal}T00:00:00`) }}</h2>
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr><th>Sekolah</th><th>NPSN</th><th>Kab/Kota</th><th>Sesi</th><th>Target</th><th>Status</th></tr>
            </thead>
            <tbody>
              <tr v-for="s in list" :key="s.id">
                <td>{{ s.school?.nama }}</td>
                <td>{{ s.school?.npsn }}</td>
                <td>{{ s.school?.kab_kota }}</td>
                <td>{{ labelSesi[s.sesi] || s.sesi }}</td>
                <td>{{ angka(s.target_siswa) }} siswa</td>
                <td><Badge :text="labelStatus[s.status] || s.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <div v-if="!loading && groups.length === 0" class="card empty-state">Belum ada jadwal kunjungan untuk Anda.</div>
  </div>
</template>
