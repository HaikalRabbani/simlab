<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const berandaRole = (user) => {
  if (user.role === 'dinas_provinsi') return 'dashboard'
  if (user.role === 'petugas_lapangan') return 'input'
  return 'pengawas'
}

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push(route.query.redirect || { name: berandaRole(auth.user) })
  } catch (e) {
    error.value = e.response?.data?.message || e.response?.data?.errors?.email?.[0] || 'Gagal masuk. Periksa kembali email dan sandi Anda.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-brand">
      <div class="login-logo">SL</div>
      <h1>SIMLAB Jabar</h1>
      <p>Sistem Informasi Skrining Kesehatan Siswa<br />Dinas Pendidikan Provinsi Jawa Barat</p>
    </div>

    <form class="login-card" @submit.prevent="submit">
      <h2>Masuk</h2>
      <p class="muted">Gunakan akun yang diberikan Dinas.</p>

      <div v-if="error" class="banner banner-warning">{{ error }}</div>

      <div class="form-group">
        <label for="email">Email</label>
        <input id="email" v-model="email" type="email" class="form-control" autocomplete="email" required />
      </div>
      <div class="form-group">
        <label for="password">Kata sandi</label>
        <input id="password" v-model="password" type="password" class="form-control" autocomplete="current-password" required />
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%" :disabled="loading">
        {{ loading ? 'Memproses…' : 'Masuk' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 40px;
  flex-wrap: wrap;
  padding: 24px;
  background: var(--color-page-bg);
}

.login-brand { max-width: 380px; color: var(--color-text-primary); }
.login-logo {
  width: 64px; height: 64px; border-radius: 14px;
  background: var(--color-brand-accent); color: #fff;
  font-size: 26px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 16px;
}
.login-brand h1 { font-size: 26px; margin: 0 0 6px; }
.login-brand p { color: var(--color-text-muted); font-size: 13px; }

.login-card {
  width: 380px; max-width: 100%;
  background: #fff; border: 1px solid var(--color-border);
  border-radius: 14px; box-shadow: var(--shadow-card);
  padding: 28px;
}
.login-card h2 { margin: 0 0 4px; font-size: 18px; }
</style>
