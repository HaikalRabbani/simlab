<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { angka, tanggalLengkap } from '../../utils/format'
import Badge from '../../components/Badge.vue'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const schedules = ref([])
const schools = ref([])
const petugasList = ref([])
const editingId = ref(null)

const form = ref({
  school_id: '',
  petugas_id: '',
  tanggal: '',
  sesi: 'pagi',
  target_siswa: '',
  status: 'terjadwal',
})

const labelSesi = { pagi: 'Sesi pagi', siang: 'Sesi siang' }
const labelStatus = { terjadwal: 'Terjadwal', berlangsung: 'Berlangsung', selesai: 'Selesai' }

const isEditing = computed(() => editingId.value !== null)
const sekolahDipilih = computed(() => schools.value.find((s) => s.id === Number(form.value.school_id)))

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const [s, sc, u] = await Promise.all([
      client.get('/schedules', { params: { per_page: 200 } }),
      client.get('/schools', { params: { per_page: 500 } }),
      client.get('/users', { params: { role: 'petugas_lapangan', per_page: 500 } }),
    ])
    schedules.value = s.data.data || []
    schools.value = sc.data.data || []
    petugasList.value = u.data.data || []
  } catch (e) {
    error.value = 'Gagal memuat data jadwal.'
  } finally {
    loading.value = false
  }
}

function resetForm() {
  editingId.value = null
  form.value = {
    school_id: '',
    petugas_id: '',
    tanggal: '',
    sesi: 'pagi',
    target_siswa: '',
    status: 'terjadwal',
  }
}

function edit(s) {
  editingId.value = s.id
  form.value = {
    school_id: s.school_id,
    petugas_id: s.petugas_id,
    tanggal: s.tanggal,
    sesi: s.sesi,
    target_siswa: s.target_siswa,
    status: s.status,
  }
  window.scrollTo({ top: 0 })
}

async function simpan() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      school_id: Number(form.value.school_id),
      petugas_id: Number(form.value.petugas_id),
      tanggal: form.value.tanggal,
      sesi: form.value.sesi,
      target_siswa: Number(form.value.target_siswa),
      status: form.value.status,
    }
    if (isEditing.value) {
      await client.put(`/schedules/${editingId.value}`, payload)
    } else {
      await client.post('/schedules', payload)
    }
    resetForm()
    await muat()
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value =
      (errs && Object.values(errs).flat().join(' ')) ||
      e.response?.data?.message ||
      'Gagal menyimpan jadwal.'
  } finally {
    saving.value = false
  }
}

async function hapus(s) {
  if (!confirm(`Hapus jadwal ${s.school?.nama} tanggal ${tanggalLengkap(s.tanggal)}?`)) return
  try {
    await client.delete(`/schedules/${s.id}`)
    await muat()
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal menghapus jadwal.'
  }
}

onMounted(muat)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Jadwal Skrining</h1>
        <p class="page-subtitle">Atur jadwal kunjungan petugas ke sekolah</p>
      </div>
      <button v-if="isEditing" class="btn" @click="resetForm">Batal edit</button>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>

    <div class="card">
      <h2>{{ isEditing ? 'Ubah jadwal' : 'Buat jadwal baru' }}</h2>
      <div class="form-row">
        <div class="form-group">
          <label>Sekolah</label>
          <select v-model="form.school_id" class="form-control">
            <option value="" disabled>Pilih sekolah…</option>
            <option v-for="s in schools" :key="s.id" :value="s.id">
              {{ s.nama }} ({{ s.kab_kota }})
            </option>
          </select>
        </div>
        <div class="form-group">
          <label>Petugas</label>
          <select v-model="form.petugas_id" class="form-control">
            <option value="" disabled>Pilih petugas…</option>
            <option v-for="p in petugasList" :key="p.id" :value="p.id">
              {{ p.nama }} ({{ p.wilayah_scope }})
            </option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Tanggal</label>
          <input v-model="form.tanggal" type="date" class="form-control" />
        </div>
        <div class="form-group">
          <label>Sesi</label>
          <select v-model="form.sesi" class="form-control">
            <option value="pagi">Sesi pagi</option>
            <option value="siang">Sesi siang</option>
          </select>
        </div>
        <div class="form-group">
          <label>Target siswa</label>
          <input v-model="form.target_siswa" type="number" min="1" class="form-control" placeholder="cth: 120" />
        </div>
        <div class="form-group">
          <label>Status</label>
          <select v-model="form.status" class="form-control">
            <option value="terjadwal">Terjadwal</option>
            <option value="berlangsung">Berlangsung</option>
            <option value="selesai">Selesai</option>
          </select>
        </div>
      </div>
      <p v-if="sekolahDipilih" class="card-note" style="margin:0 0 10px">
        {{ sekolahDipilih.nama }} · NPSN {{ sekolahDipilih.npsn }} · {{ sekolahDipilih.kab_kota }} / {{ sekolahDipilih.kecamatan }}
      </p>
      <button class="btn btn-primary" :disabled="saving" @click="simpan">
        {{ saving ? 'Menyimpan…' : isEditing ? 'Simpan perubahan' : 'Buat jadwal' }}
      </button>
    </div>

    <div v-if="loading" class="loading">Memuat data…</div>

    <div class="card">
      <h2>Daftar jadwal ({{ angka(schedules.length) }})</h2>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr>
              <th>Tanggal</th><th>Sekolah</th><th>NPSN</th><th>Kab/Kota</th>
              <th>Petugas</th><th>Sesi</th><th>Target</th><th>Status</th><th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in schedules" :key="s.id">
              <td>{{ tanggalLengkap(s.tanggal) }}</td>
              <td>{{ s.school?.nama }}</td>
              <td>{{ s.school?.npsn }}</td>
              <td>{{ s.school?.kab_kota }}</td>
              <td>{{ s.petugas?.nama }}</td>
              <td>{{ labelSesi[s.sesi] || s.sesi }}</td>
              <td>{{ angka(s.target_siswa) }}</td>
              <td><Badge :text="labelStatus[s.status] || s.status" /></td>
              <td>
                <button class="btn btn-sm" style="margin-right:4px" @click="edit(s)">Edit</button>
                <button class="btn btn-sm btn-danger" @click="hapus(s)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!loading && schedules.length === 0" class="empty-state">Belum ada jadwal.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0 16px;
}
</style>