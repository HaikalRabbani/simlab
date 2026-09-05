<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { hariTanggal, jamMenit, angka, tanggalInput } from '../../utils/format'
import { unduhCsvDariData } from '../../utils/download'
import Badge from '../../components/Badge.vue'

const tanggal = ref(tanggalInput())
const loading = ref(true)
const error = ref('')
const items = ref([])

const perHari = computed(() => hariTanggal(`${tanggal.value}T00:00:00`))
const total = computed(() => items.value.length)
const terkirim = computed(() => items.value.filter((i) => i.status_kirim === 'terkirim').length)
const menunggu = computed(() => items.value.filter((i) => i.status_kirim !== 'terkirim').length)
const reaktif = computed(() => items.value.filter((i) => (i.reactive_parameters || []).length > 0).length)

const menungguItems = computed(() => items.value.filter((i) => i.status_kirim !== 'terkirim'))
const terkirimItems = computed(() => items.value.filter((i) => i.status_kirim === 'terkirim'))

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/examinations', {
      params: { tanggal: tanggal.value, per_page: 500 },
    })
    items.value = data.data || []
  } catch (e) {
    error.value = 'Gagal memuat riwayat input.'
  } finally {
    loading.value = false
  }
}

function unduhRekap() {
  const headers = ['Waktu input', 'Nama siswa', 'NISN', 'Kelas', 'Jenis kelamin', 'Hasil uji', 'Status kirim']
  const baris = items.value.map((i) => [
    jamMenit(i.waktu_input),
    i.student?.nama || '',
    i.student?.nisn || '',
    i.student?.kelas || '',
    i.student?.jenis_kelamin === 'L' ? 'Laki-laki' : i.student?.jenis_kelamin === 'P' ? 'Perempuan' : '',
    i.hasil_ringkasan || '',
    i.status_kirim === 'terkirim' ? 'Terkirim' : 'Menunggu',
  ])
  unduhCsvDariData(`rekap-sesi-${tanggal.value}.csv`, headers, baris)
}

async function kirimPending() {
  if (menungguItems.value.length === 0) return
  const gagal = []
  for (const item of menungguItems.value) {
    try {
      await client.post('/examinations', {
        client_uuid: item.client_uuid,
        schedule_id: item.schedule_id,
        student_id: item.student?.id,
        waktu_input: item.waktu_input,
        pendamping: item.pendamping,
        catatan_petugas: item.catatan_petugas || null,
        strip_lot_code: item.strip_lot_code,
        strip_expiry_date: item.strip_expiry_date,
        rencana_tindak_lanjut: item.rencana_tindak_lanjut || null,
        sampel_disegel: item.sampel_disegel ?? null,
        kode_segel: item.kode_segel || null,
        hasil: item.results?.map((r) => ({ parameter: r.parameter, hasil: r.hasil })) || [],
      })
    } catch {
      gagal.push(item.student?.nama || item.id)
    }
  }
  await muat()
  if (gagal.length) {
    error.value = `Sebagian data gagal dikirim (${gagal.length}). Cek koneksi lalu coba lagi.`
  } else {
    error.value = ''
  }
}

onMounted(muat)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Riwayat Input</h1>
        <p class="page-subtitle">Hasil yang sudah Anda kirim pada {{ perHari }}</p>
      </div>
      <div class="page-actions">
        <input v-model="tanggal" type="date" class="form-control" style="width:auto" @change="muat" />
        <button class="btn" :disabled="items.length === 0" @click="unduhRekap">Unduh rekap sesi</button>
        <button class="btn btn-primary" :disabled="menunggu === 0 || loading" @click="kirimPending">
          Kirim {{ angka(menunggu) }} data tertunda
        </button>
        <button class="btn" :disabled="loading" @click="muat">Muat ulang</button>
      </div>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>

    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">Total diperiksa</div>
        <div class="stat-value">{{ angka(total) }}</div>
        <div class="stat-hint">entri pada {{ perHari }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Terkirim ke server</div>
        <div class="stat-value text-success">{{ angka(terkirim) }}</div>
        <div class="stat-hint">tersinkronisasi otomatis</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Menunggu sinkronisasi</div>
        <div class="stat-value text-warning">{{ angka(menunggu) }}</div>
        <div class="stat-hint">tersimpan di perangkat, aman</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Hasil reaktif</div>
        <div class="stat-value text-danger">{{ angka(reaktif) }}</div>
        <div class="stat-hint">sudah dirujuk uji konfirmasi</div>
      </div>
    </div>

    <div v-if="menunggu > 0" class="banner">
      {{ menunggu }} data belum terkirim. Tersimpan aman di perangkat dan akan dikirim otomatis saat jaringan stabil.
    </div>

    <div v-if="loading" class="loading">Memuat data…</div>

    <div v-if="menungguItems.length" class="card">
      <h2>Menunggu sinkronisasi</h2>
      <div class="table-wrap">
        <table class="data">
          <tbody>
            <tr v-for="i in menungguItems" :key="i.id">
              <td>{{ jamMenit(i.waktu_input) }}</td>
              <td>{{ i.student?.nama }}</td>
              <td>{{ i.student?.nisn }}</td>
              <td>{{ i.student?.kelas }}</td>
              <td>{{ i.student?.jenis_kelamin }}</td>
              <td><Badge :text="i.hasil_ringkasan" /></td>
              <td><Badge :text="i.status_kirim === 'terkirim' ? 'Terkirim' : 'Menunggu'" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h2>Sudah terkirim · {{ angka(terkirimItems.length) }} entri</h2>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Waktu input</th><th>Nama siswa</th><th>NISN</th><th>Kelas</th><th>L/P</th><th>Hasil uji</th><th>Status kirim</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="i in terkirimItems" :key="i.id">
              <td>{{ jamMenit(i.waktu_input) }}</td>
              <td>{{ i.student?.nama }}</td>
              <td>{{ i.student?.nisn }}</td>
              <td>{{ i.student?.kelas }}</td>
              <td>{{ i.student?.jenis_kelamin }}</td>
              <td><Badge :text="i.hasil_ringkasan" /></td>
              <td><Badge text="Terkirim" tone="success" /></td>
            </tr>
          </tbody>
        </table>
        <p v-if="terkirimItems.length === 0 && !loading" class="empty-state">Belum ada data terkirim pada tanggal ini.</p>
      </div>
      <p class="card-note">Data tersimpan di perangkat bila jaringan terputus dan terkirim otomatis saat sinyal kembali. Entri yang sudah terkirim tidak dapat diubah petugas — koreksi diajukan lewat pengawas wilayah agar jejak audit tetap utuh.</p>
    </div>
  </div>
</template>
