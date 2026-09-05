const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']

/**
 * Parse tanggal dengan aman. Tanggal murni (YYYY-MM-DD) di-parse di tengah hari
 * supaya tidak geser sehari akibat zona waktu. Mengembalikan null untuk input
 * kosong/invalid supaya pemanggil bisa menampilkan placeholder ("—").
 */
function parseTanggal(iso) {
  if (!iso) return null
  const d = /^\d{4}-\d{2}-\d{2}$/.test(iso) ? new Date(`${iso}T12:00:00`) : new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

export function tanggalLengkap(iso) {
  const d = parseTanggal(iso)
  if (!d) return '—'
  return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`
}

export function hariTanggal(iso) {
  const d = parseTanggal(iso)
  if (!d) return '—'
  return `${HARI[d.getDay()]}, ${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`
}

export function jamMenit(iso) {
  const d = parseTanggal(iso)
  if (!d) return '—'
  return `${String(d.getHours()).padStart(2, '0')}.${String(d.getMinutes()).padStart(2, '0')}`
}

export function angka(n) {
  if (n === null || n === undefined || n === '') return '0'
  const num = Number(n)
  return Number.isNaN(num) ? '0' : num.toLocaleString('id-ID')
}

export function tanggalInput(d = new Date()) {
  const pad = (x) => String(x).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}