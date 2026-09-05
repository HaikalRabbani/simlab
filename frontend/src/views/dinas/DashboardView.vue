<script setup>
import { ref, onMounted, computed } from 'vue'
import client from '../../api/client'
import { angka, tanggalLengkap } from '../../utils/format'

const tahun = ref(new Date().getFullYear())
const loading = ref(true)
const error = ref('')
const summary = ref(null)
const regions = ref([])
const parameters = ref([])
const topSchools = ref([])
const legend = ref({})

const subtitle = computed(() => {
  if (!summary.value?.periode) return ''
  return `Periode ${tahun.value} · ${summary.value.jumlah_kab_kota} kabupaten/kota · diperbarui ${tanggalLengkap(summary.value.diperbarui)}`
})

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const [s, r, p, t] = await Promise.all([
      client.get('/dashboard/summary', { params: { tahun: tahun.value } }),
      client.get('/dashboard/reactive-by-region', { params: { tahun: tahun.value } }),
      client.get('/dashboard/reactive-by-parameter', { params: { tahun: tahun.value } }),
      client.get('/dashboard/top-schools', { params: { tahun: tahun.value } }),
    ])
    summary.value = s.data
    regions.value = r.data.wilayah
    legend.value = r.data.legenda
    parameters.value = p.data.parameters
    topSchools.value = t.data.schools
  } catch (e) {
    error.value = 'Gagal memuat data dashboard.'
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
        <h1>Dashboard Skrining Provinsi</h1>
        <p class="page-subtitle">{{ subtitle }}</p>
      </div>
      <div class="page-actions">
        <select v-model="tahun" class="form-control" style="width:auto" @change="muat">
          <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
          <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
        </select>
        <button class="btn" :disabled="loading" @click="muat">Muat ulang</button>
      </div>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>
    <div v-if="loading" class="loading">Memuat data…</div>

    <template v-if="summary">
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label">Siswa diperiksa</div>
          <div class="stat-value">{{ angka(summary.cards.siswa_diperiksa) }}</div>
          <div class="stat-hint">dari target tahunan {{ angka(summary.cards.target_tahunan) }} ({{ summary.cards.persentase_capaian }}%)</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Sekolah terjangkau</div>
          <div class="stat-value">{{ angka(summary.cards.sekolah_terjangkau) }}</div>
          <div class="stat-hint">dari {{ angka(summary.cards.total_sekolah_sasaran) }} sekolah sasaran</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Hasil reaktif</div>
          <div class="stat-value text-danger">{{ angka(summary.cards.hasil_reaktif) }}</div>
          <div class="stat-hint">{{ summary.cards.persentase_reaktif }}% dari total pemeriksaan</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Sudah uji konfirmasi</div>
          <div class="stat-value text-warning">{{ angka(summary.cards.sudah_uji_konfirmasi) }}</div>
          <div class="stat-hint">{{ angka(summary.cards.sisa_menunggu_lab) }} masih menunggu jadwal lab</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Petugas aktif</div>
          <div class="stat-value">{{ angka(summary.cards.petugas_aktif) }}</div>
          <div class="stat-hint">tersebar di {{ angka(summary.cards.kab_kota_petugas) }} kab/kota</div>
        </div>
      </div>

      <div class="card">
        <div class="page-header" style="margin-bottom:4px">
          <h2 style="margin:0">Tingkat hasil reaktif per kabupaten/kota</h2>
        </div>
        <div class="heatmap-legend">
          <span><span class="legend-chip" style="background:#e4f2e9;border:1px solid #bcd9c6"></span>&lt; 0.7%</span>
          <span><span class="legend-chip" style="background:#fcf0dc;border:1px solid #ecd9a8"></span>0.7 – 1.6%</span>
          <span><span class="legend-chip" style="background:#fbe7e5;border:1px solid #e8b6b0"></span>≥ 1.6%</span>
        </div>
        <div class="region-grid">
          <div
            v-for="w in regions"
            :key="w.kab_kota"
            class="region-card"
            :style="{
              background: w.tingkat === 'tinggi' ? '#fbe7e5' : w.tingkat === 'sedang' ? '#fcf0dc' : '#e4f2e9',
              border: w.tingkat === 'tinggi' ? '1px solid #e8544a' : '1px solid var(--color-border)',
            }"
          >
            <div class="region-name">{{ w.kab_kota }}</div>
            <div
              class="region-pct"
              :style="{ color: w.tingkat === 'tinggi' ? '#e8544a' : w.tingkat === 'sedang' ? '#a96a0c' : '#1b7f4b' }"
            >
              {{ w.persentase_reaktif.toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}%
            </div>
            <div class="region-sub">{{ angka(w.jumlah_reaktif) }} dari {{ angka(w.jumlah_diuji) }} siswa</div>
          </div>
        </div>
        <p v-if="regions.length === 0" class="card-note">Belum ada data pemeriksaan pada periode ini.</p>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="dash-bottom">
        <div class="card">
          <h2>Reaktif menurut jenis parameter</h2>
          <div v-for="p in parameters" :key="p.parameter" class="bar-row">
            <div class="bar-label">{{ p.label }}</div>
            <div class="bar-track">
              <div
                class="bar-fill"
                :style="{ width: (parameters.length && p.jumlah_reaktif / Math.max(...parameters.map(x => x.jumlah_reaktif), 1)) * 100 + '%', background: '#be2b22' }"
              ></div>
            </div>
            <div class="bar-value muted">{{ angka(p.jumlah_reaktif) }}</div>
          </div>
          <p class="card-note">Satu siswa dapat reaktif pada lebih dari satu parameter, sehingga jumlah di atas dapat melebihi total hasil reaktif. Angka ini adalah hasil skrining awal — status akhir mengikuti uji konfirmasi laboratorium.</p>
        </div>

        <div class="card">
          <h2>Sekolah dengan temuan tertinggi</h2>
          <div class="table-wrap">
            <table class="data">
              <thead>
                <tr><th>Sekolah</th><th>Kab/Kota</th><th>Diuji</th><th>Reaktif</th><th>%</th></tr>
              </thead>
              <tbody>
                <tr v-for="s in topSchools" :key="s.id">
                  <td>{{ s.nama }}</td>
                  <td>{{ s.kab_kota }}</td>
                  <td>{{ angka(s.jumlah_diuji) }}</td>
                  <td class="text-danger">{{ angka(s.jumlah_reaktif) }}</td>
                  <td>{{ s.persentase_reaktif.toLocaleString('id-ID') }}%</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="topSchools.length === 0" class="card-note">Belum ada data pemeriksaan pada periode ini.</p>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dash-bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 900px) { .dash-bottom { grid-template-columns: 1fr; } }
</style>
