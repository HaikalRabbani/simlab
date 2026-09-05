<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const NAV = {
  petugas_lapangan: [
    {
      label: 'Petugas Lapangan',
      items: [
        { name: 'input', text: 'Input Pemeriksaan' },
        { name: 'riwayat', text: 'Riwayat Input' },
        { name: 'jadwal', text: 'Jadwal Kunjungan' },
      ],
    },
    {
      label: 'Data',
      items: [
        { name: 'sekolah', text: 'Daftar Sekolah' },
        { name: 'stok', text: 'Stok Alat Uji' },
      ],
    },
  ],
  dinas_provinsi: [
    {
      label: 'Dinas Provinsi',
      items: [
        { name: 'dashboard', text: 'Dashboard' },
        { name: 'demografi', text: 'Analisis Demografi' },
        { name: 'sekolah', text: 'Daftar Sekolah' },
        { name: 'peta', text: 'Peta Wilayah' },
      ],
    },
    {
      label: 'Pelaporan',
      items: [
        { name: 'laporan', text: 'Laporan Periodik' },
        { name: 'ekspor', text: 'Ekspor Data' },
      ],
    },
    {
      label: 'Pengelolaan',
      items: [
        { name: 'jadwal-skrining', text: 'Jadwal Skrining' },
        { name: 'pengguna', text: 'Pengguna & Akses' },
      ],
    },
  ],
  pengawas_wilayah: [
    {
      label: 'Pengawasan',
      items: [{ name: 'pengawas', text: 'Persetujuan Koreksi' }],
    },
    {
      label: 'Data',
      items: [{ name: 'sekolah', text: 'Daftar Sekolah' }],
    },
  ],
}

const groups = computed(() => NAV[auth.user?.role] || [])
const namaRole = computed(() => {
  const role = auth.user?.role
  if (role === 'petugas_lapangan') return 'Petugas Lapangan'
  if (role === 'pengawas_wilayah') return 'Pengawas Wilayah'
  if (role === 'dinas_provinsi') return 'Dinas Pendidikan'
  return ''
})

async function keluar() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="sidebar-logo">SL</div>
        <div class="sidebar-brand-text">
          <b>SIMLAB Jabar</b>
          <small>Sistem Informasi Skrining<br />Kesehatan Siswa</small>
        </div>
      </div>

      <nav class="sidebar-nav">
        <template v-for="group in groups" :key="group.label">
          <div class="nav-group-label">{{ group.label }}</div>
          <RouterLink
            v-for="item in group.items"
            :key="item.name"
            :to="{ name: item.name }"
            class="nav-item"
            :class="{ active: route.name === item.name }"
          >
            <span>{{ item.text }}</span>
          </RouterLink>
        </template>
      </nav>

      <div class="sidebar-user">
        <div class="avatar">{{ auth.user?.avatar_initial || '??' }}</div>
        <div style="flex:1; min-width:0">
          <b>{{ auth.user?.nama }}</b>
          <small>{{ namaRole }}{{ auth.user?.wilayah_scope ? ' · ' + auth.user.wilayah_scope : '' }}</small>
        </div>
        <button class="btn btn-sm" title="Keluar" style="background:transparent;border:none;color:#9fb3c6;padding:2px" @click="keluar">⏻</button>
      </div>
    </aside>

    <main class="app-main">
      <RouterView />
    </main>
  </div>
</template>
