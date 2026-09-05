import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppLayout from '../layouts/AppLayout.vue'
import LoginView from '../views/LoginView.vue'
import ComingSoonView from '../views/ComingSoonView.vue'

const berandaRole = (user) => {
  if (!user) return 'login'
  if (user.role === 'dinas_provinsi') return 'dashboard'
  if (user.role === 'petugas_lapangan') return 'input'
  return 'pengawas'
}

const routes = [
  { path: '/login', name: 'login', component: LoginView, meta: { public: true } },
  {
    path: '/',
    component: AppLayout,
    children: [
      { path: '', redirect: () => ({ name: 'beranda' }) },
      {
        path: 'beranda',
        name: 'beranda',
        redirect: () => ({ name: berandaRole(useAuthStore().user) }),
      },

      // ---- Dinas Provinsi ----
      {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('../views/dinas/DashboardView.vue'),
        meta: { roles: ['dinas_provinsi'], title: 'Dashboard Skrining Provinsi' },
      },
      {
        path: 'sekolah',
        name: 'sekolah',
        component: () => import('../views/SekolahView.vue'),
        meta: { roles: ['dinas_provinsi', 'petugas_lapangan', 'pengawas_wilayah'], title: 'Daftar Sekolah' },
      },
      {
        path: 'ekspor',
        name: 'ekspor',
        component: () => import('../views/dinas/EksporDataView.vue'),
        meta: { roles: ['dinas_provinsi'], title: 'Ekspor Data' },
      },
      {
        path: 'pengguna',
        name: 'pengguna',
        component: () => import('../views/dinas/PenggunaView.vue'),
        meta: { roles: ['dinas_provinsi'], title: 'Pengguna & Akses' },
      },
      {
        path: 'jadwal-skrining',
        name: 'jadwal-skrining',
        component: () => import('../views/dinas/JadwalSkriningView.vue'),
        meta: { roles: ['dinas_provinsi'], title: 'Jadwal Skrining' },
      },
      {
        path: 'laporan',
        name: 'laporan',
        component: () => import('../views/dinas/LaporanPeriodikView.vue'),
        meta: { roles: ['dinas_provinsi'], title: 'Laporan Periodik' },
      },
      {
        path: 'peta',
        name: 'peta',
        component: () => import('../views/dinas/PetaWilayahView.vue'),
        meta: { roles: ['dinas_provinsi'], title: 'Peta Wilayah' },
      },
      {
        path: 'demografi',
        name: 'demografi',
        component: () => import('../views/dinas/DemografiView.vue'),
        meta: { roles: ['dinas_provinsi'], title: 'Analisis Demografi' },
      },

      // ---- Petugas Lapangan ----
      {
        path: 'riwayat',
        name: 'riwayat',
        component: () => import('../views/petugas/RiwayatInputView.vue'),
        meta: { roles: ['petugas_lapangan'], title: 'Riwayat Input' },
      },
      {
        path: 'jadwal',
        name: 'jadwal',
        component: () => import('../views/petugas/JadwalKunjunganView.vue'),
        meta: { roles: ['petugas_lapangan'], title: 'Jadwal Kunjungan' },
      },
      {
        path: 'input',
        name: 'input',
        component: () => import('../views/petugas/InputPemeriksaanView.vue'),
        meta: { roles: ['petugas_lapangan'], title: 'Input Pemeriksaan' },
      },
      {
        path: 'stok',
        name: 'stok',
        component: () => import('../views/petugas/StokAlatUjiView.vue'),
        meta: { roles: ['petugas_lapangan'], title: 'Stok Alat Uji' },
      },

      // ---- Pengawas Wilayah ----
      {
        path: 'pengawas',
        name: 'pengawas',
        component: () => import('../views/pengawas/PengawasView.vue'),
        meta: { roles: ['pengawas_wilayah'], title: 'Panel Pengawas Wilayah' },
      },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    if (auth.isLoggedIn && to.name === 'login') {
      return { name: berandaRole(auth.user) }
    }
    return true
  }

  if (!auth.isLoggedIn) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.roles && !to.meta.roles.includes(auth.user.role)) {
    return { name: berandaRole(auth.user) }
  }

  return true
})

router.afterEach((to) => {
  document.title = to.meta.title
    ? `${to.meta.title} · SIMLAB Jabar`
    : 'SIMLAB Jabar'
})

export default router
