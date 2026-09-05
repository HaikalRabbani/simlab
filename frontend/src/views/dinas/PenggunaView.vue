<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { angka } from '../../utils/format'
import Badge from '../../components/Badge.vue'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const users = ref([])
const editingId = ref(null)

const form = ref({
  nama: '',
  email: '',
  password: '',
  role: 'petugas_lapangan',
  wilayah_scope: '',
})

const labelRole = {
  petugas_lapangan: 'Petugas Lapangan',
  pengawas_wilayah: 'Pengawas Wilayah',
  dinas_provinsi: 'Dinas Provinsi',
}

const isEditing = computed(() => editingId.value !== null)
const butuhWilayah = computed(() => ['petugas_lapangan', 'pengawas_wilayah'].includes(form.value.role))

const toneRole = (r) =>
  r === 'dinas_provinsi' ? 'teal' : r === 'pengawas_wilayah' ? 'warning' : 'neutral'

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/users', { params: { per_page: 500 } })
    users.value = data.data || []
  } catch (e) {
    error.value = 'Gagal memuat data pengguna.'
  } finally {
    loading.value = false
  }
}

function resetForm() {
  editingId.value = null
  form.value = {
    nama: '',
    email: '',
    password: '',
    role: 'petugas_lapangan',
    wilayah_scope: '',
  }
}

function edit(u) {
  editingId.value = u.id
  form.value = {
    nama: u.nama,
    email: u.email,
    password: '',
    role: u.role,
    wilayah_scope: u.wilayah_scope || '',
  }
  window.scrollTo({ top: 0 })
}

async function simpan() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      nama: form.value.nama,
      email: form.value.email,
      role: form.value.role,
      wilayah_scope: butuhWilayah.value ? form.value.wilayah_scope : null,
    }
    if (form.value.password) payload.password = form.value.password
    if (isEditing.value) {
      await client.put(`/users/${editingId.value}`, payload)
    } else {
      await client.post('/users', payload)
    }
    resetForm()
    await muat()
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value =
      (errs && Object.values(errs).flat().join(' ')) ||
      e.response?.data?.message ||
      'Gagal menyimpan pengguna.'
  } finally {
    saving.value = false
  }
}

async function hapus(u) {
  if (!confirm(`Hapus pengguna ${u.nama}?`)) return
  try {
    await client.delete(`/users/${u.id}`)
    await muat()
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal menghapus pengguna.'
  }
}

onMounted(muat)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Pengguna &amp; Akses</h1>
        <p class="page-subtitle">Kelola akun, role, dan wilayah tugas pengguna</p>
      </div>
      <button v-if="isEditing" class="btn" @click="resetForm">Batal edit</button>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>

    <div class="card">
      <h2>{{ isEditing ? 'Ubah pengguna' : 'Tambah pengguna baru' }}</h2>
      <div class="form-row">
        <div class="form-group">
          <label>Nama lengkap</label>
          <input v-model="form.nama" type="text" class="form-control" placeholder="Nama pengguna" />
        </div>
        <div class="form-group">
          <label>Email</label>
          <input v-model="form.email" type="email" class="form-control" placeholder="nama@simlab.test" />
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Role</label>
          <select v-model="form.role" class="form-control">
            <option value="petugas_lapangan">Petugas Lapangan</option>
            <option value="pengawas_wilayah">Pengawas Wilayah</option>
            <option value="dinas_provinsi">Dinas Provinsi</option>
          </select>
        </div>
        <div class="form-group" v-if="butuhWilayah">
          <label>Wilayah (kab/kota)</label>
          <input v-model="form.wilayah_scope" type="text" class="form-control" placeholder="cth: Kota Bandung" />
        </div>
        <div class="form-group">
          <label>{{ isEditing ? 'Password baru (kosongkan bila tidak diganti)' : 'Password' }}</label>
          <input v-model="form.password" type="password" class="form-control" placeholder="Min. 8 karakter" />
        </div>
      </div>
      <p class="card-note" style="margin:0 0 10px">
        Dinas Provinsi tidak memakai wilayah (scope seluruh provinsi). Password default saat dibuat: <code>password123</code> bila dikosongkan.
      </p>
      <button class="btn btn-primary" :disabled="saving" @click="simpan">
        {{ saving ? 'Menyimpan…' : isEditing ? 'Simpan perubahan' : 'Tambah pengguna' }}
      </button>
    </div>

    <div v-if="loading" class="loading">Memuat data…</div>

    <div class="card">
      <h2>Daftar pengguna ({{ angka(users.length) }})</h2>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr><th>Nama</th><th>Email</th><th>Role</th><th>Wilayah</th><th>Aksi</th></tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td>{{ u.nama }}</td>
              <td>{{ u.email }}</td>
              <td><Badge :text="labelRole[u.role] || u.role" :tone="toneRole(u.role)" /></td>
              <td>{{ u.wilayah_scope || 'Seluruh provinsi' }}</td>
              <td>
                <button class="btn btn-sm" style="margin-right:4px" @click="edit(u)">Edit</button>
                <button class="btn btn-sm btn-danger" @click="hapus(u)">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!loading && users.length === 0" class="empty-state">Belum ada pengguna.</p>
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