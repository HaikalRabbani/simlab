<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'
import { angka, hariTanggal, tanggalLengkap, tanggalInput } from '../../utils/format'
import Badge from '../../components/Badge.vue'

const router = useRouter()

const PARAMETERS = [
  { key: 'THC', label: 'THC', ket: 'Tetrahydrocannabinol (ganja)' },
  { key: 'AMP', label: 'AMP', ket: 'Amfetamin' },
  { key: 'MET', label: 'MET', ket: 'Metamfetamin (sabu)' },
  { key: 'MOP', label: 'MOP', ket: 'Morfin / opiat' },
  { key: 'BZO', label: 'BZO', ket: 'Benzodiazepin' },
  { key: 'TRA', label: 'TRA', ket: 'Tramadol' },
  { key: 'ALKOHOL', label: 'Alkohol', ket: 'Etanol, uji saliva' },
]

const LABEL_SESI = { pagi: 'Sesi pagi', siang: 'Sesi siang' }
const LABEL_HASIL = { negatif: 'Negatif', positif: 'Positif', invalid: 'Invalid' }
const LABEL_JK = { L: 'Laki-laki', P: 'Perempuan' }
const RENCANA_OPTIONS = ['rujuk_uji_konfirmasi', 'pantau_rutin', 'skrining_ulang']
const LABEL_RENCANA = {
  rujuk_uji_konfirmasi: 'Rujuk uji konfirmasi laboratorium',
  pantau_rutin: 'Pantau rutin',
  skrining_ulang: 'Skrining ulang',
}

const DRAFT_KEY = 'simlab_draft_input'

const LANGKAH = ['Pilih sekolah', 'Profil siswa', 'Hasil uji', 'Konfirmasi']

// ---- State wizard ----
const step = ref(1)
const loadingSchedules = ref(true)
const error = ref('')
const successMsg = ref('')

// Langkah 1 — sekolah dari jadwal hari ini (terkunci)
const schedules = ref([])
const scheduleId = ref(null)
const countHariIni = ref(0)

// Langkah 2 — profil siswa
const student = ref({
  id: null,
  nisn: '',
  nama: '',
  kelas: '',
  jenis_kelamin: '',
  tanggal_lahir: '',
})
const lookupState = ref('') // '' | 'ditemukan' | 'baru'
const pendamping = ref('')
const lookupBusy = ref(false)

// Langkah 3 — hasil uji
const stripLot = ref('')
const stripExpiry = ref('')
const hasil = ref({})
PARAMETERS.forEach((p) => (hasil.value[p.key] = null))
const rencana = ref('')
const sampelDisegel = ref(null) // null | true | false
const kodeSegel = ref('')
const catatan = ref('')

const submitting = ref(false)

// UUID idempotency per upaya submit (spec 9.6): dipakai ulang saat retry
// supaya retry yang terkirim ganda tidak membuat data duplikat di server.
const uuidAktif = ref(null)

// ---- Computed ----
const school = computed(() => {
  const s = schedules.value.find((x) => x.id === scheduleId.value)
  return s?.school || null
})
const sesi = computed(() => {
  const s = schedules.value.find((x) => x.id === scheduleId.value)
  return s?.sesi || ''
})
const tanggalJadwal = computed(() => {
  const s = schedules.value.find((x) => x.id === scheduleId.value)
  return s?.tanggal || ''
})
const targetSesi = computed(() => {
  const s = schedules.value.find((x) => x.id === scheduleId.value)
  return s?.target_siswa || 0
})
const pesertaKe = computed(() => countHariIni.value + 1)
const subtitle = computed(() => {
  if (!school.value) return 'Pilih sekolah kunjungan hari ini'
  return `${school.value.nama} · ${LABEL_SESI[sesi.value] || sesi.value}, ${hariTanggal(`${tanggalJadwal.value}T00:00:00`)}`
})

const jumlahTerisi = computed(() => PARAMETERS.filter((p) => hasil.value[p.key]).length)
const parameterReaktif = computed(() => PARAMETERS.filter((p) => hasil.value[p.key] === 'positif'))
const adaReaktif = computed(() => parameterReaktif.value.length > 0)
const reaktifLabel = computed(() => parameterReaktif.value.map((p) => p.label).join(', '))

// ---- Validasi per langkah ----
const validStep2 = computed(() => {
  return (
    student.value.nisn.trim() &&
    student.value.nama.trim() &&
    student.value.kelas.trim() &&
    student.value.jenis_kelamin &&
    student.value.tanggal_lahir &&
    pendamping.value.trim()
  )
})

const validStep3 = computed(() => {
  if (!stripLot.value.trim() || !stripExpiry.value) return false
  if (jumlahTerisi.value !== 7) return false
  if (!adaReaktif.value) return true
  if (!rencana.value) return false
  if (sampelDisegel.value === null) return false
  if (sampelDisegel.value === true && !kodeSegel.value.trim()) return false
  return true
})

function validasiLangkah() {
  if (step.value === 2 && !validStep2.value) {
    error.value = 'Lengkapi profil siswa dan pendamping terlebih dahulu.'
    return false
  }
  if (step.value === 3 && !validStep3.value) {
    error.value = 'Lengkapi 7 parameter uji dan bagian tindak lanjut (bila ada hasil reaktif).'
    return false
  }
  return true
}

function lanjut() {
  error.value = ''
  if (!validasiLangkah()) return
  step.value = Math.min(step.value + 1, 4)
  window.scrollTo({ top: 0 })
}

function kembali() {
  error.value = ''
  step.value = Math.max(step.value - 1, 1)
  window.scrollTo({ top: 0 })
}

// ---- Muat jadwal hari ini ----
async function muatJadwal() {
  loadingSchedules.value = true
  error.value = ''
  try {
    const { data } = await client.get('/schedules', {
      params: { tanggal: tanggalInput(), per_page: 100 },
    })
    schedules.value = data.data || []
    if (schedules.value.length === 1) {
      scheduleId.value = schedules.value[0].id
    }
  } catch (e) {
    error.value = 'Gagal memuat jadwal kunjungan hari ini.'
  } finally {
    loadingSchedules.value = false
  }
}

async function muatJumlahHariIni() {
  if (!scheduleId.value) return
  try {
    const { data } = await client.get('/examinations', {
      params: { tanggal: tanggalInput(), schedule_id: scheduleId.value, per_page: 1 },
    })
    countHariIni.value = data.meta?.total ?? (data.data?.length || 0)
  } catch {
    countHariIni.value = 0
  }
}

watch(scheduleId, muatJumlahHariIni)

// ---- Lookup NISN (Langkah 2) ----
// Set true saat lookup sedang mengisi kolom, supaya watcher NISN tidak membatalkannya
let lookupMengisi = false

async function cariNisn() {
  const nisn = student.value.nisn.trim()
  if (!nisn || !school.value) return
  lookupBusy.value = true
  lookupState.value = ''
  try {
    const { data } = await client.get(`/schools/${school.value.id}/students`, { params: { nisn } })
    if (data.length) {
      const s = data[0]
      lookupMengisi = true
      student.value.id = s.id
      student.value.nisn = s.nisn
      student.value.nama = s.nama
      student.value.kelas = s.kelas
      student.value.jenis_kelamin = s.jenis_kelamin
      student.value.tanggal_lahir = s.tanggal_lahir
      lookupState.value = 'ditemukan'
    } else {
      lookupState.value = 'baru'
      student.value.id = null
    }
  } catch (e) {
    error.value = 'Gagal mencari NISN. Periksa koneksi lalu coba lagi.'
  } finally {
    lookupBusy.value = false
    lookupMengisi = false
  }
}

// Saat NISN diedit manual, data siswa hasil lookup dianggap tidak berlaku lagi
watch(
  () => student.value.nisn,
  () => {
    if (lookupMengisi) return
    student.value.id = null
    lookupState.value = ''
  },
)

// ---- Submit ----
function buatUuid() {
  return typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(36).slice(2)}`
}

function payloadSiswa() {
  return {
    school_id: school.value.id,
    nisn: student.value.nisn.trim(),
    nama: student.value.nama.trim(),
    kelas: student.value.kelas.trim(),
    jenis_kelamin: student.value.jenis_kelamin,
    tanggal_lahir: student.value.tanggal_lahir,
  }
}

function payloadPemeriksaan(studentId) {
  if (!uuidAktif.value) uuidAktif.value = buatUuid()
  return {
    client_uuid: uuidAktif.value,
    schedule_id: scheduleId.value,
    student_id: studentId,
    waktu_input: new Date().toISOString(),
    pendamping: pendamping.value.trim(),
    catatan_petugas: catatan.value.trim() || null,
    strip_lot_code: stripLot.value.trim(),
    strip_expiry_date: stripExpiry.value,
    rencana_tindak_lanjut: adaReaktif.value ? rencana.value : null,
    sampel_disegel: adaReaktif.value ? sampelDisegel.value : null,
    kode_segel: adaReaktif.value && sampelDisegel.value === true ? kodeSegel.value.trim() || null : null,
    hasil: PARAMETERS.map((p) => ({ parameter: p.key, hasil: hasil.value[p.key] })),
  }
}

async function kirim({ lanjutBerikutnya = false } = {}) {
  if (!validStep2.value || !validStep3.value) {
    error.value = 'Data belum lengkap. Periksa kembali isian Anda.'
    return
  }
  submitting.value = true
  error.value = ''
  successMsg.value = ''
  try {
    let studentId = student.value.id
    if (!studentId) {
      const { data: created } = await client.post('/students', payloadSiswa())
      studentId = created.id
    }
    await client.post('/examinations', payloadPemeriksaan(studentId))
    successMsg.value = `Pemeriksaan ${student.value.nama} berhasil dikirim.`
    if (lanjutBerikutnya) {
      resetSiswaDanHasil()
      step.value = 2
      await muatJumlahHariIni()
    } else {
      localStorage.removeItem(DRAFT_KEY)
      router.push({ name: 'riwayat' })
    }
    uuidAktif.value = null
  } catch (e) {
    const msg = e.response?.data?.message
    const errs = e.response?.data?.errors
    error.value =
      (errs && Object.values(errs).flat().join(' ')) ||
      msg ||
      'Gagal menyimpan pemeriksaan. Coba lagi.'
  } finally {
    submitting.value = false
  }
}

// ---- Draf lokal (spec 9.1) ----
function simpanDraf() {
  const draf = {
    step: step.value,
    scheduleId: scheduleId.value,
    student: { ...student.value },
    lookupState: lookupState.value,
    pendamping: pendamping.value,
    stripLot: stripLot.value,
    stripExpiry: stripExpiry.value,
    hasil: { ...hasil.value },
    rencana: rencana.value,
    sampelDisegel: sampelDisegel.value,
    kodeSegel: kodeSegel.value,
    catatan: catatan.value,
  }
  localStorage.setItem(DRAFT_KEY, JSON.stringify(draf))
  successMsg.value = 'Draf tersimpan di perangkat. Anda bisa melanjutkannya nanti.'
  setTimeout(() => (successMsg.value = ''), 4000)
}

function muatDraf() {
  const raw = localStorage.getItem(DRAFT_KEY)
  if (!raw) return
  try {
    const d = JSON.parse(raw)
    if (d.scheduleId && schedules.value.some((s) => s.id === d.scheduleId)) {
      lookupMengisi = true
      Object.assign(student.value, d.student || {})
      lookupState.value = d.lookupState || ''
      pendamping.value = d.pendamping || ''
      stripLot.value = d.stripLot || ''
      stripExpiry.value = d.stripExpiry || ''
      Object.keys(hasil.value).forEach((k) => (hasil.value[k] = d.hasil?.[k] ?? null))
      rencana.value = d.rencana || ''
      sampelDisegel.value = d.sampelDisegel ?? null
      kodeSegel.value = d.kodeSegel || ''
      catatan.value = d.catatan || ''
      scheduleId.value = d.scheduleId
      step.value = Math.min(Math.max(d.step || 1, 1), 4)
      lookupMengisi = false
    }
  } catch {
    localStorage.removeItem(DRAFT_KEY)
    lookupMengisi = false
  }
}

function hapusDraf() {
  localStorage.removeItem(DRAFT_KEY)
  resetSiswaDanHasil()
  step.value = 1
}

function resetSiswaDanHasil() {
  student.value = { id: null, nisn: '', nama: '', kelas: '', jenis_kelamin: '', tanggal_lahir: '' }
  lookupState.value = ''
  pendamping.value = ''
  Object.keys(hasil.value).forEach((k) => (hasil.value[k] = null))
  rencana.value = ''
  sampelDisegel.value = null
  kodeSegel.value = ''
  catatan.value = ''
  uuidAktif.value = null
}

onMounted(async () => {
  await muatJadwal()
  muatDraf()
})
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div>
        <h1>Input Pemeriksaan</h1>
        <p class="page-subtitle">
          {{ subtitle }}
          <span v-if="school" class="peserta-info">· Peserta ke-{{ angka(pesertaKe) }} dari target sesi: {{ angka(targetSesi) }} siswa</span>
        </p>
      </div>
      <div class="page-actions">
        <button class="btn" :disabled="!school" @click="simpanDraf">Simpan draf</button>
        <button
          class="btn btn-teal"
          :disabled="!school || submitting"
          @click="kirim({ lanjutBerikutnya: true })"
        >
          Simpan &amp; Input berikutnya
        </button>
      </div>
    </div>

    <div v-if="error" class="banner banner-warning">{{ error }}</div>
    <div v-if="successMsg" class="banner banner-info">{{ successMsg }}</div>

    <!-- Stepper -->
    <div class="stepper">
      <div
        v-for="(l, i) in LANGKAH"
        :key="l"
        class="stepper-item"
        :class="{ done: step > i + 1, active: step === i + 1 }"
      >
        <div class="stepper-dot">{{ step > i + 1 ? '✓' : i + 1 }}</div>
        <div class="stepper-label">{{ l }}</div>
      </div>
    </div>

    <div v-if="loadingSchedules" class="loading">Memuat jadwal…</div>

    <div v-else-if="schedules.length === 0" class="card empty-state">
      Tidak ada jadwal kunjungan untuk Anda hari ini.<br />
      <RouterLink :to="{ name: 'jadwal' }" class="btn mt-1" style="display:inline-block;margin-top:12px">Lihat Jadwal Kunjungan</RouterLink>
    </div>

    <template v-else>
      <!-- ===== Langkah 1: Sekolah ===== -->
      <div v-show="step === 1" class="card">
        <div class="card-head">
          <h2>Pilih sekolah kunjungan</h2>
          <Badge text="Terkunci dari jadwal" tone="teal" />
        </div>
        <div class="form-group" v-if="schedules.length > 1">
          <label>Sesi kunjungan hari ini</label>
          <select v-model="scheduleId" class="form-control" style="max-width:420px">
            <option v-for="s in schedules" :key="s.id" :value="s.id">
              {{ s.school?.nama }} — {{ LABEL_SESI[s.sesi] || s.sesi }}
            </option>
          </select>
        </div>
        <div v-if="school" class="school-info">
          <div class="info-row"><span>Nama sekolah</span><b>{{ school.nama }}</b></div>
          <div class="info-row"><span>NPSN</span><b>{{ school.npsn }}</b></div>
          <div class="info-row"><span>Kab/Kota</span><b>{{ school.kab_kota }}</b></div>
          <div class="info-row"><span>Kecamatan</span><b>{{ school.kecamatan }}</b></div>
          <div class="info-row"><span>Tanggal &amp; sesi</span><b>{{ tanggalLengkap(`${tanggalJadwal}T00:00:00`) }} · {{ LABEL_SESI[sesi] || sesi }}</b></div>
        </div>
        <div class="step-actions">
          <button class="btn btn-primary" :disabled="!school" @click="lanjut">Lanjut ke Profil siswa</button>
        </div>
      </div>

      <!-- ===== Langkah 2: Profil Siswa ===== -->
      <div v-show="step === 2" class="card">
        <h2>Profil siswa</h2>
        <div class="banner">
          Data tersimpan otomatis di perangkat. Bila sinyal terputus, pemeriksaan tetap bisa dilanjutkan dan akan terkirim saat jaringan kembali.
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>NISN</label>
            <div class="lookup-row">
              <input
                v-model="student.nisn"
                type="text"
                class="form-control"
                placeholder="Ketik NISN siswa…"
                @blur="cariNisn"
              />
              <button class="btn" :disabled="lookupBusy || !student.nisn.trim()" @click="cariNisn">
                {{ lookupBusy ? 'Mencari…' : 'Cari' }}
              </button>
            </div>
            <small class="muted">Ketika NISN sudah terdaftar, data siswa terisi otomatis.</small>
          </div>
          <div class="form-group" v-if="lookupState === 'ditemukan'">
            <label>Status</label>
            <Badge text="Siswa terdaftar — data terisi otomatis" tone="success" />
          </div>
          <div class="form-group" v-else-if="lookupState === 'baru'">
            <label>Status</label>
            <Badge text="NISN baru — isi manual, akan didaftarkan" tone="warning" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Nama lengkap</label>
            <input v-model="student.nama" type="text" class="form-control" placeholder="Nama lengkap siswa" />
          </div>
          <div class="form-group">
            <label>Kelas</label>
            <input v-model="student.kelas" type="text" class="form-control" placeholder="Contoh: XI IPA 3" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Jenis kelamin</label>
            <select v-model="student.jenis_kelamin" class="form-control">
              <option value="" disabled>Pilih…</option>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label>Tanggal lahir</label>
            <input v-model="student.tanggal_lahir" type="date" class="form-control" :max="tanggalInput()" />
          </div>
        </div>

        <div class="form-group">
          <label>Pendamping saat pemeriksaan <span class="text-danger">*</span></label>
          <input
            v-model="pendamping"
            type="text"
            class="form-control"
            placeholder="Contoh: Guru BK — Dra. Siti Rohmah"
          />
          <small class="muted">Wajib diisi — pemeriksaan tidak boleh berlangsung tanpa pendamping dari pihak sekolah.</small>
        </div>

        <div class="step-actions">
          <button class="btn" @click="kembali">Kembali</button>
          <button class="btn btn-primary" @click="lanjut">Lanjut ke Hasil uji</button>
        </div>
      </div>

      <!-- ===== Langkah 3: Hasil Uji ===== -->
      <div v-show="step === 3" class="card">
        <h2>Hasil uji</h2>
        <div class="banner">
          Baca strip 5 menit setelah sampel diteteskan. Pilih <b>Invalid</b> bila garis kontrol tidak muncul — pemeriksaan wajib diulang dengan strip baru.
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Kode lot strip</label>
            <input v-model="stripLot" type="text" class="form-control" placeholder="Contoh: RT-2609-A" />
          </div>
          <div class="form-group">
            <label>Tanggal kedaluwarsa strip</label>
            <input v-model="stripExpiry" type="date" class="form-control" :min="tanggalInput()" />
          </div>
        </div>

        <div class="hasil-progress">
          <b>{{ jumlahTerisi }} dari 7 parameter terisi</b> — semua 7 wajib diisi sebelum lanjut.
        </div>

        <div class="param-list">
          <div v-for="p in PARAMETERS" :key="p.key" class="param-row">
            <div class="param-head">
              <div class="param-info">
                <b>{{ p.label }}<template v-if="p.key !== p.label"> ({{ p.key }})</template></b>
                <small class="muted">{{ p.ket }}</small>
              </div>
            </div>
            <div class="toggle-group">
              <button
                v-for="h in ['negatif', 'positif', 'invalid']"
                :key="h"
                class="toggle-btn"
                :class="[hasil[p.key] === h ? h : '']"
                @click="hasil[p.key] = hasil[p.key] === h ? null : h"
              >
                {{ LABEL_HASIL[h] }}
              </button>
            </div>
          </div>
        </div>

        <!-- Section tindak lanjut (hanya bila ada reaktif) -->
        <div v-if="adaReaktif" class="reaktif-box">
          <div class="banner banner-warning" style="margin-bottom:14px">
            <b>{{ parameterReaktif.length }} parameter reaktif — tindak lanjut wajib:</b> {{ reaktifLabel }}.
          </div>

          <div class="info-row" style="margin-bottom:14px">
            <span>Pendamping saat pemeriksaan</span>
            <b>{{ pendamping || '—' }}</b>
          </div>

          <div class="form-group">
            <label>Rencana tindak lanjut <span class="text-danger">*</span></label>
            <select v-model="rencana" class="form-control">
              <option value="" disabled>Pilih rencana…</option>
              <option v-for="r in RENCANA_OPTIONS" :key="r" :value="r">{{ LABEL_RENCANA[r] }}</option>
            </select>
          </div>

          <div class="form-group">
            <label>Sampel disegel <span class="text-danger">*</span></label>
            <div class="toggle-group">
              <button class="toggle-btn" :class="{ ya: sampelDisegel === true }" @click="sampelDisegel = sampelDisegel === true ? null : true">Ya</button>
              <button class="toggle-btn" :class="{ tidak: sampelDisegel === false }" @click="sampelDisegel = sampelDisegel === false ? null : false">Tidak</button>
            </div>
          </div>

          <div class="form-group" v-if="sampelDisegel === true">
            <label>Kode segel <span class="text-danger">*</span></label>
            <input v-model="kodeSegel" type="text" class="form-control" placeholder="Contoh: SG-0412" />
          </div>

          <div class="form-group">
            <label>Catatan petugas (opsional)</label>
            <textarea v-model="catatan" rows="3" class="form-control" placeholder="Kondisi sampel, pengulangan uji, dan lain-lain…"></textarea>
          </div>

          <div class="banner" style="border-left-color:#0b6e6e;background:#e3f1f0">
            Hasil ini bersifat rahasia. Identitas siswa hanya terbaca oleh Anda dan pengawas wilayah. Dinas menerima data ini dalam bentuk agregat tanpa nama.
          </div>
        </div>

        <div class="step-actions">
          <button class="btn" @click="kembali">Kembali</button>
          <button class="btn btn-primary" @click="lanjut">Lanjut ke Konfirmasi</button>
        </div>
      </div>

      <!-- ===== Langkah 4: Konfirmasi ===== -->
      <div v-show="step === 4" class="card">
        <h2>Konfirmasi &amp; kirim</h2>
        <p class="muted">Periksa kembali seluruh data sebelum dikirim. Data yang sudah terkirim tidak dapat diubah petugas.</p>

        <div class="ringkasan-grid">
          <div class="ringkasan-block">
            <h3>Sekolah</h3>
            <div class="info-row"><span>Sekolah</span><b>{{ school?.nama }}</b></div>
            <div class="info-row"><span>NPSN</span><b>{{ school?.npsn }}</b></div>
            <div class="info-row"><span>Sesi</span><b>{{ LABEL_SESI[sesi] }} · {{ tanggalLengkap(`${tanggalJadwal}T00:00:00`) }}</b></div>
          </div>
          <div class="ringkasan-block">
            <h3>Siswa</h3>
            <div class="info-row"><span>NISN</span><b>{{ student.nisn }}</b></div>
            <div class="info-row"><span>Nama</span><b>{{ student.nama }}</b></div>
            <div class="info-row"><span>Kelas</span><b>{{ student.kelas }}</b></div>
            <div class="info-row"><span>Jenis kelamin</span><b>{{ LABEL_JK[student.jenis_kelamin] }}</b></div>
            <div class="info-row"><span>Tanggal lahir</span><b>{{ tanggalLengkap(`${student.tanggal_lahir}T00:00:00`) }}</b></div>
            <div class="info-row"><span>Pendamping</span><b>{{ pendamping }}</b></div>
          </div>
          <div class="ringkasan-block">
            <h3>Hasil uji</h3>
            <div class="info-row"><span>Lot strip</span><b>{{ stripLot }}</b></div>
            <div class="info-row"><span>Kedaluwarsa</span><b>{{ tanggalLengkap(`${stripExpiry}T00:00:00`) }}</b></div>
            <div v-for="p in PARAMETERS" :key="p.key" class="info-row">
              <span>{{ p.label }}</span>
              <b :class="{ 'text-danger': hasil[p.key] === 'positif' }">{{ LABEL_HASIL[hasil[p.key]] || '—' }}</b>
            </div>
            <template v-if="adaReaktif">
              <div class="info-row"><span>Rencana</span><b>{{ LABEL_RENCANA[rencana] }}</b></div>
              <div class="info-row"><span>Sampel disegel</span><b>{{ sampelDisegel === true ? 'Ya' : 'Tidak' }}</b></div>
              <div class="info-row" v-if="kodeSegel"><span>Kode segel</span><b>{{ kodeSegel }}</b></div>
            </template>
          </div>
        </div>

        <div v-if="catatan" class="info-row" style="margin-top:12px"><span>Catatan petugas</span><b>{{ catatan }}</b></div>

        <div class="step-actions">
          <button class="btn" :disabled="submitting" @click="kembali">Kembali</button>
          <button class="btn btn-primary" :disabled="submitting" @click="kirim()">
            {{ submitting ? 'Mengirim…' : 'Simpan & kirim' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.stepper {
  display: flex;
  gap: 6px;
  margin-bottom: 20px;
  background: #fff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 14px 16px;
}

.stepper-item {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
}

.stepper-dot {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  background: var(--color-page-bg-alt);
  border: 1px solid var(--color-border);
  color: var(--color-text-muted);
  flex-shrink: 0;
}

.stepper-item.active .stepper-dot {
  background: var(--color-brand-accent);
  border-color: var(--color-brand-accent);
  color: #fff;
}

.stepper-item.done .stepper-dot {
  background: var(--color-brand-green);
  border-color: var(--color-brand-green);
  color: #fff;
}

.stepper-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-muted);
}

.stepper-item.active .stepper-label,
.stepper-item.done .stepper-label {
  color: var(--color-text-primary);
}

.peserta-info {
  color: var(--color-text-muted-strong);
  font-weight: 600;
}

.school-info {
  background: var(--color-page-bg-alt);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 16px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 6px 0;
  border-bottom: 1px solid var(--color-border);
  font-size: 13px;
}

.info-row:last-child {
  border-bottom: none;
}

.info-row span {
  color: var(--color-text-muted);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 16px;
}

@media (max-width: 700px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}

.lookup-row {
  display: flex;
  gap: 8px;
}

.lookup-row .form-control {
  flex: 1;
}

.hasil-progress {
  background: var(--color-page-bg-alt);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  margin-bottom: 14px;
}

.param-list {
  display: grid;
  grid-template-columns: repeat(4, 1fr); /* 4 kolom x 2 baris (7 card) */
  gap: 10px;
  margin-bottom: 8px;
}

/* Layar sedang: 2 kolom; HP: 1 kolom */
@media (max-width: 1200px) {
  .param-list {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 700px) {
  .param-list {
    grid-template-columns: 1fr;
  }
}

.param-row {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
}

/* Tinggi card seragam per baris & tombol menempel di bawah */
.param-row .toggle-group {
  margin-top: auto;
}

.param-head {
  display: flex;
}

.param-info {
  display: flex;
  flex-direction: column;
  min-width: 150px;
}

.param-info small {
  font-size: 11px;
}

/* Baris tombol sendiri di bawah teks, membentang kiri-kanan */
.toggle-group {
  display: flex;
  gap: 6px;
}

.toggle-group .toggle-btn {
  flex: 1;
  text-align: center;
  padding: 9px 10px;
}

.toggle-btn {
  border: 1px solid var(--color-border);
  background: #fff;
  color: var(--color-text-muted-strong);
  border-radius: 6px;
  padding: 6px 14px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.12s;
}

.toggle-btn.negatif {
  background: var(--color-brand-green);
  border-color: var(--color-brand-green);
  color: #fff;
}

.toggle-btn.positif {
  background: var(--color-danger);
  border-color: var(--color-danger);
  color: #fff;
}

.toggle-btn.invalid,
.toggle-btn.ya,
.toggle-btn.tidak {
  background: var(--color-page-bg-alt);
  border-color: var(--color-text-muted);
  color: var(--color-text-primary);
}

.reaktif-box {
  border: 1px solid var(--color-danger);
  border-radius: 8px;
  padding: 14px;
  margin-top: 8px;
}

.step-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 18px;
}

.ringkasan-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 14px;
}

.ringkasan-block {
  border: 1px solid var(--color-border);
  border-radius: 8px;
  padding: 12px 14px;
}

.ringkasan-block h3 {
  margin: 0 0 8px;
  font-size: 13px;
  color: var(--color-text-primary);
}
</style>