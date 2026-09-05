<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import client from '../../api/client'
import { angka } from '../../utils/format'

const tahun = ref(new Date().getFullYear())
const loading = ref(true)
const error = ref('')
const legend = ref({})
const wilayah = ref([])
const mapEl = ref(null)

// Perkiraan koordinat pusat 27 kab/kota Jawa Barat (untuk titik peta)
const KOORD = {
  'Kota Bandung': [-6.9175, 107.6191],
  'Kab. Bandung': [-7.0339, 107.5200],
  'Kab. Bandung Barat': [-6.8375, 107.5075],
  'Kota Cimahi': [-6.8723, 107.5425],
  'Kab. Sumedang': [-6.8588, 107.9206],
  'Kab. Bogor': [-6.4817, 106.8525],
  'Kota Bogor': [-6.5971, 106.8060],
  'Kota Depok': [-6.4025, 106.7942],
  'Kab. Bekasi': [-6.2612, 107.2762],
  'Kota Bekasi': [-6.2383, 106.9896],
  'Kab. Karawang': [-6.3227, 107.3376],
  'Kab. Purwakarta': [-6.5570, 107.4433],
  'Kab. Subang': [-6.5699, 107.7629],
  'Kab. Indramayu': [-6.3270, 108.3240],
  'Kab. Cirebon': [-6.7604, 108.4833],
  'Kota Cirebon': [-6.7320, 108.5523],
  'Kab. Sukabumi': [-6.9183, 106.9283],
  'Kota Sukabumi': [-6.9214, 106.9272],
  'Kab. Cianjur': [-6.8175, 107.1417],
  'Kab. Garut': [-7.2279, 107.9087],
  'Kab. Tasikmalaya': [-7.3500, 108.1167],
  'Kota Tasikmalaya': [-7.3274, 108.2207],
  'Kab. Ciamis': [-7.3260, 108.3530],
  'Kota Banjar': [-7.3740, 108.5350],
  'Kab. Pangandaran': [-7.6833, 108.6333],
  'Kab. Kuningan': [-6.9830, 108.4830],
  'Kab. Majalengka': [-6.8300, 108.2200],
}

let map = null
let markersLayer = null

const WARNA = {
  rendah: '#1b7f4b',
  sedang: '#a96a0c',
  tinggi: '#e8544a',
}

function initMap() {
  if (map) return
  map = L.map(mapEl.value, { center: [-6.9, 107.6], zoom: 8, zoomControl: true })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(map)
}

function gambarWilayah() {
  if (!map) return
  if (markersLayer) {
    markersLayer.remove()
  }
  markersLayer = L.layerGroup().addTo(map)

  wilayah.value.forEach((w) => {
    const koord = KOORD[w.kab_kota]
    if (!koord) return
    const warna = WARNA[w.tingkat] || WARNA.rendah
    const radius = Math.max(6, Math.min(20, 6 + Math.sqrt(w.jumlah_diuji) * 0.6))

    L.circleMarker(koord, {
      radius,
      color: '#ffffff',
      weight: 1.5,
      fillColor: warna,
      fillOpacity: 0.85,
    })
      .addTo(markersLayer)
      .bindPopup(
        `<b>${w.kab_kota}</b><br/>` +
          `${angka(w.jumlah_diuji)} diperiksa · ${angka(w.jumlah_reaktif)} reaktif<br/>` +
          `<b>${w.persentase_reaktif.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%</b>`
      )
  })
}

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/dashboard/reactive-by-region', { params: { tahun: tahun.value } })
    legend.value = data.legenda || {}
    wilayah.value = data.wilayah || []
    initMap()
    gambarWilayah()
  } catch (e) {
    error.value = 'Gagal memuat data peta wilayah.'
  } finally {
    loading.value = false
  }
}

onMounted(muat)
onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
  }
})
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Peta Wilayah</h1>
        <p class="page-subtitle">Tingkat hasil reaktif per kabupaten/kota — periode {{ tahun }}</p>
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
    <div v-if="loading" class="loading">Memuat peta…</div>

    <div class="card">
      <div class="heatmap-legend" style="justify-content:flex-start;margin-bottom:10px">
        <span><span class="legend-chip" style="background:#e4f2e9;border:1px solid #bcd9c6"></span>{{ legend.rendah || '&lt; 0.7%' }}</span>
        <span><span class="legend-chip" style="background:#fcf0dc;border:1px solid #ecd9a8"></span>{{ legend.sedang || '0.7 – 1.6%' }}</span>
        <span><span class="legend-chip" style="background:#fbe7e5;border:1px solid #e8b6b0"></span>{{ legend.tinggi || '≥ 1.6%' }}</span>
      </div>
      <div ref="mapEl" class="map-container"></div>
      <p class="card-note">
        Ukuran titik menggambarkan jumlah siswa diperiksa; warna menggambarkan tingkat reaktif. Klik titik untuk detail.
      </p>
    </div>
  </div>
</template>

<style scoped>
.map-container {
  height: 560px;
  width: 100%;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  z-index: 0;
}
</style>