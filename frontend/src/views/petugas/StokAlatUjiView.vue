<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { angka, tanggalLengkap } from '../../utils/format'

const PARAMETERS = [
  { key: 'THC', label: 'THC — ganja' },
  { key: 'AMP', label: 'AMP — amfetamin' },
  { key: 'MET', label: 'MET — sabu' },
  { key: 'MOP', label: 'MOP — morfin/opiat' },
  { key: 'BZO', label: 'BZO — benzodiazepin' },
  { key: 'TRA', label: 'TRA — tramadol' },
  { key: 'ALKOHOL', label: 'Alkohol (etanol saliva)' },
]

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const stocks = ref([])
const schools = ref([])
const editingId = ref(null)

const form = ref({
  school_id: '',
  parameter: 'THC',
  jumlah_stok: '',
  lot_code: '',
  expiry_date: '',
})

const isEditing = computed(() => editingId.value !== null)

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const [st, sc] = await Promise.all([
      client.get('/test-strip-stock', { params: { per_page: 500 } }),
      client.get('/schools', { params: { per_page: 500 } }),
    ])
    stocks.value = st.data.data || []
    schools.value = sc.data.data || []
  } catch (e) {
    error.value = 'Gagal memuat data stok.'
  } finally {
    loading.value = false
  }
}

function resetForm() {
  editingId.value = null
  form.value = {
    school_id: '',
    parameter: 'THC',
    jumlah_stok: '',
    lot_code: '',
    expiry_date: '',
  }
}

function edit(s) {
  editingId.value = s.id
  form.value = {
    school_id: s.school_id || '',
    parameter: s.parameter,
    jumlah_stok: s.jumlah_stok,
    lot_code: s.lot_code,
    expiry_date: s.expiry_date,
  }
  window.scrollTo({ top: 0 })
}

async function simpan() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      school_id: form.value.school_id ? Number(form.value.school_id) : null,
      parameter: form.value.parameter,
      jumlah_stok: Number(form.value.jumlah_stok),
      lot_code: form.value.lot_code,
      expiry_date: form.value.expiry_date,
    }
    if (isEditing.value) {
      await client.put(`/test-strip-stock/${editingId.value}`, payload)
    } else {
      await client.post('/test-strip-stock', payload)
    }
    resetForm()
    await muat()
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value =
      (errs && Object.values(errs).flat().join(' ')) ||
      e.response?.data?.message ||
      'Gagal menyimpan stok.'
  } finally {
    saving.value = false
  }
}

async function hapus(s) {
  if (!confirm(`Hapus catatan stok ${s.parameter} (${s.lot_code})?`)) return
  try {
    await client.delete(`/test-strip-stock/${s.id}`)
    await muat()
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal menghapus stok.'
  }
}

onMounted(muat)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Stok Alat Uji</h1>
        <p class="page-subtitle">Catatan stok strip uji yang Anda pegang</p>
      </div>
      <button v-if="isEditing" class="btn" @click="resetForm">Batal edit</button>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>

    <div class="card">
      <h2>{{ isEditing ? 'Ubah catatan stok' : 'Tambah stok strip' }}</h2>
      <div class="form-row">
        <div class="form-group">
          <label>Parameter</label>
          <select v-model="form.parameter" class="form-control">
            <option v-for="p in PARAMETERS" :key="p.key" :value="p.key">{{ p.label }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Jumlah stok</label>
          <input v-model="form.jumlah_stok" type="number" min="0" class="form-control" placeholder="cth: 120" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Kode lot</label>
          <input v-model="form.lot_code" type="text" class="form-control" placeholder="cth: RT-2609-A" />
        </div>
        <div class="form-group">
          <label>Tanggal kedaluwarsa</label>
          <input v-model="form.expiry_date" type="date" class="form-control" />
        </div>
      </div>
      <div class="form-group">
        <label>Sekolah pemakaian (opsional)</label>
        <select v-model="form.school_id" class="form-control">
          <option value="">— Stok umum petugas —</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.nama }}</option>
        </select>
      </div>
      <button class="btn btn-primary" :disabled="saving" @click="simpan">
        {{ saving ? 'Menyimpan…' : isEditing ? 'Simpan perubahan' : 'Tambah stok' }}
      </button>
    </div>

    <div v-if="loading" class="loading">Memuat data…</div>

    <div class="card">
      <h2>Catatan stok ({{ angka(stocks.length) }})</h2>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr><th>Parameter</th><th>Jumlah</th><th>Kode lot</th><th>Kedaluwarsa</th><th>Sekolah</th><th>Aksi</th></tr>
          </thead>
          <tbody>
            <tr v-for="s in stocks" :key="s.id">
              <td>{{ s.parameter }}</td>
              <td>{{ angka(s.jumlah_stok) }}</td>
              <td>{{ s.lot_code }}</td>
              <td>{{ tanggalLengkap(s.expiry_date) }}</td>
              <td>{{ s.school?.nama || '—' }}</td>
              <td>
                <button class="btn btn-sm" style="margin-right:4px" @click="edit(s)">Edit</button>
                <button class="btn btn-sm btn-danger" @click="hapus(s)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!loading && stocks.length === 0" class="empty-state">Belum ada catatan stok.</p>
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