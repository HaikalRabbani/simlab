const _bulan = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const _hari = [
  'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu',
];

String pad2(int n) => n.toString().padLeft(2, '0');

/// "2026-09-12" -> "12 September 2026"
String tanggalLengkap(String iso) {
  final d = DateTime.tryParse(iso);
  if (d == null) return '—';
  return '${d.day} ${_bulan[d.month - 1]} ${d.year}';
}

/// "2026-09-12" -> "Sabtu, 12 September 2026"
String hariTanggal(String iso) {
  final d = DateTime.tryParse(iso);
  if (d == null) return '—';
  return '${_hari[d.weekday % 7]}, ${d.day} ${_bulan[d.month - 1]} ${d.year}';
}

/// ISO datetime -> "07.30" (jam.menit)
String jamMenit(String iso) {
  final d = DateTime.tryParse(iso);
  if (d == null) return '—';
  return '${pad2(d.hour)}.${pad2(d.minute)}';
}

String angka(dynamic n) {
  if (n == null) return '0';
  final s = n.toString();
  final buf = StringBuffer();
  for (int i = 0; i < s.length; i++) {
    buf.write(s[i]);
    final remaining = s.length - i - 1;
    if (remaining > 0 && remaining % 3 == 0) buf.write('.');
  }
  return buf.toString();
}