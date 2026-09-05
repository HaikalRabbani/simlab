import client from '../api/client'

export async function unduhCsv(path, params = {}, namaFile = 'unduhan.csv') {
  const { data } = await client.get(path, { params, responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const link = document.createElement('a')
  link.href = url
  link.download = namaFile
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

function selCsv(v) {
  const s = String(v ?? '')
  return s.includes(';') || s.includes('"') || s.includes('\n') ? `"${s.replace(/"/g, '""')}"` : s
}

/**
 * Unduh CSV dari data yang sudah ada di sisi klien (rekap sesi petugas, dsb).
 * Menggunakan separator ';' + BOM UTF-8 agar terbaca rapi di Excel Indonesia.
 */
export function unduhCsvDariData(namaFile, headers, baris) {
  const konten =
    '\uFEFF' +
    [headers, ...baris].map((r) => r.map(selCsv).join(';')).join('\r\n')
  const url = URL.createObjectURL(new Blob([konten], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = namaFile
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
