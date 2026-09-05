<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { angka, tanggalLengkap } from '../../utils/format'
import { unduhCsvDariData } from '../../utils/download'

const WILAYAH_OPTIONS = ['Bandung Raya', 'Bodebek', 'Pantura', 'Priangan Timur']

const tahun = ref(new Date().getFullYear())
const wilayah = ref('semua')
const jenjang = ref('semua')
const loading = ref(true)
const error = ref('')
const gender = ref(null)
const grade = ref(null)
const cross = ref(null)

const subtitle = computed(() => {
  if (!gender.value) return ''
  const bagian = [`Total ${angka(gender.value.total_diperiksa)} pemeriksaan`]
  if (wilayah.value !== 'semua') bagian.push(`Wilayah ${wilayah.value}`)
  else bagian.push('Seluruh wilayah')
  if (jenjang.value !== 'semua') bagian.push(`Jenjang ${jenjang.value}`)
  return bagian.join(' · ')
})

const barMax = computed(() => {
  const vals = []
  if (gender.value) vals.push(...gender.value.data.map((d) => d.jumlah_diuji))
  if (grade.value) {
    vals.push(...grade.value.data.kelas.map((d) => d.jumlah_diuji))
    vals.push(...grade.value.data.jenjang.map((d) => d.jumlah_diuji))
  }
  return Math.max(...vals, 1)
})

async function muat() {
  loading.value = true
  error.value = ''
  try {
    const params = { tahun: tahun.value, wilayah: wilayah.value, jenjang: jenjang.value }
    const [g, gr, c] = await Promise.all([
      client.get('/demographics/by-gender', { params }),
      client.get('/demographics/by-grade', { params }),
      client.get('/demographics/cross-table', { params }),
    ])
    gender.value = g.data
    grade.value = gr.data
    cross.value = c.data
  } catch (e) {
    error.value = 'Gagal memuat data demografi.'
  } finally {
    loading.value = false
  }
}

function unduhTabel() {
  if (!cross.value) return
  const headers = ['Parameter', 'Laki-laki', 'Perempuan', 'SMA', 'SMK', 'Kelas X', 'Kelas XI', 'Kelas XII', 'Total']
  const baris = cross.value.rows.map((r) => [
    r.label,
    r.laki_laki,
    r.perempuan,
    r.sma,
    r.smk,
    r.kelas_x,
    r.kelas_xi,
    r.kelas_xii,
    r.total,
  ])
  unduhCsvDariData(`tabel-silang-demografi-${tahun.value}.csv`, headers, baris)
}

onMounted(muat)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Analisis Demografi</h1>
        <p class="page-subtitle">{{ subtitle }}</p>
      </div>
      <div class="page-actions">
        <select v-model="tahun" class="form-control" style="width:auto" @change="muat">
          <option :value="new Date().getFullYear()">{{ new Date().getFullYear() }}</option>
          <option :value="new Date().getFullYear() - 1">{{ new Date().getFullYear() - 1 }}</option>
        </select>
        <button class="btn" :disabled="loading || !cross" @click="unduhTabel">Unduh tabel</button>
        <button class="btn" :disabled="loading" @click="muat">Muat ulang</button>
      </div>
    </div>

    <div class="card" style="padding:14px 18px">
      <div class="filter-bar" style="margin-bottom:0">
        <div class="form-group">
          <label>Wilayah</label>
          <select v-model="wilayah" class="form-control" @change="muat">
            <option value="semua">Semua wilayah</option>
            <option v-for="w in WILAYAH_OPTIONS" :key="w" :value="w">{{ w }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>Jenjang</label>
          <select v-model="jenjang" class="form-control" @change="muat">
            <option value="semua">Semua jenjang</option>
            <option value="SMA">SMA</option>
            <option value="SMK">SMK</option>
            <option value="MA">MA</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>
    <div v-if="loading" class="loading">Memuat data…</div>

    <template v-if="gender && grade && cross">
      <!-- Menurut jenis kelamin -->
      <div class="card">
        <h2>Menurut jenis kelamin</h2>
        <div v-for="d in gender.data" :key="d.jenis_kelamin" class="bar-row">
          <div class="bar-label">{{ d.label }}</div>
          <div class="bar-track">
            <div
              class="bar-fill"
              :style="{
                width: (d.jumlah_diuji / barMax) * 100 + '%',
                background: d.persentase_reaktif > 0 ? '#be2b22' : '#1b7f4b',
              }"
            ></div>
          </div>
          <div class="bar-value">
            {{ d.persentase_reaktif.toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}%
            <small class="muted">({{ angka(d.jumlah_reaktif) }} dari {{ angka(d.jumlah_diuji) }})</small>
          </div>
        </div>
        <div v-if="gender.insight" class="insight-note">{{ gender.insight }}</div>
      </div>

      <!-- Menurut tingkat kelas -->
      <div class="card">
        <h2>Menurut tingkat kelas</h2>
        <div class="kelas-grid">
          <div v-for="k in grade.data.kelas" :key="k.kategori" class="kelas-bar">
            <div class="kelas-track">
              <div
                class="kelas-fill"
                :style="{
                  height: (k.jumlah_diuji / barMax) * 100 + '%',
                  background: k.persentase_reaktif > 0 ? '#be2b22' : '#1b7f4b',
                }"
              ></div>
            </div>
            <div class="kelas-pct">{{ k.persentase_reaktif.toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}%</div>
            <div class="kelas-label">{{ k.label }}</div>
            <div class="kelas-sub">{{ angka(k.jumlah_reaktif) }} / {{ angka(k.jumlah_diuji) }}</div>
          </div>
        </div>
        <div class="jenjang-bars" style="margin-top:16px">
          <div v-for="j in grade.data.jenjang" :key="j.kategori" class="bar-row">
            <div class="bar-label">{{ j.label }}</div>
            <div class="bar-track">
              <div
                class="bar-fill"
                :style="{
                  width: (j.jumlah_diuji / barMax) * 100 + '%',
                  background: j.persentase_reaktif > 0 ? '#be2b22' : '#1b7f4b',
                }"
              ></div>
            </div>
            <div class="bar-value">
              {{ j.persentase_reaktif.toLocaleString('id-ID', { maximumFractionDigits: 2 }) }}%
              <small class="muted">({{ angka(j.jumlah_reaktif) }} dari {{ angka(j.jumlah_diuji) }})</small>
            </div>
          </div>
        </div>
        <div v-if="grade.insight" class="insight-note">{{ grade.insight }}</div>
      </div>

      <!-- Tabel silang -->
      <div class="card">
        <h2>Silang parameter dengan jenis kelamin dan jenjang</h2>
        <div class="table-wrap">
          <table class="data">
            <thead>
              <tr>
                <th>Parameter</th>
                <th>Laki-laki</th>
                <th>Perempuan</th>
                <th>SMA</th>
                <th>SMK</th>
                <th>Kelas X</th>
                <th>Kelas XI</th>
                <th>Kelas XII</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in cross.rows" :key="r.parameter">
                <td>{{ r.label }}</td>
                <td>{{ angka(r.laki_laki) }}</td>
                <td>{{ angka(r.perempuan) }}</td>
                <td>{{ angka(r.sma) }}</td>
                <td>{{ angka(r.smk) }}</td>
                <td>{{ angka(r.kelas_x) }}</td>
                <td>{{ angka(r.kelas_xi) }}</td>
                <td>{{ angka(r.kelas_xii) }}</td>
                <td><b>{{ angka(r.total) }}</b></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="card-note">Jumlah hasil reaktif · satu siswa dapat reaktif di lebih dari satu parameter.</p>
      </div>
    </template>
  </div>
</template>

<style scoped>
.kelas-grid {
  display: flex;
  gap: 14px;
  align-items: flex-end;
  min-height: 200px;
  padding: 10px 4px 0;
}

.kelas-bar {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.kelas-track {
  width: 100%;
  max-width: 60px;
  height: 140px;
  background: var(--color-page-bg-alt);
  border-radius: 6px 6px 0 0;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}

.kelas-fill {
  width: 100%;
  border-radius: 6px 6px 0 0;
}

.kelas-pct {
  font-size: 13px;
  font-weight: 700;
}

.kelas-label {
  font-size: 12px;
  font-weight: 600;
}

.kelas-sub {
  font-size: 11px;
  color: var(--color-text-muted);
}
</style>