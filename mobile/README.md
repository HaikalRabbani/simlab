# SIMLAB Jabar — Aplikasi Mobile (Petugas Lapangan)

Aplikasi Flutter untuk petugas lapangan — **offline-first** (spec 9): input tersimpan
lokal dulu (sqflite), dikirim otomatis saat jaringan kembali (connectivity_plus).

## Menjalankan

```bash
# 1. Pastikan backend Laravel jalan di port 8000
cd ../backend && php artisan serve

# 2. Jalankan aplikasi (Android emulator)
flutter run --dart-define=API_URL=http://10.0.2.2:8000/api
```

> **Base URL API** diatur lewat `--dart-define=API_URL=...` (default `10.0.2.2` =
> localhost dari dalam Android emulator). Untuk device fisik, pakai IP komputer di
> jaringan LAN yang sama, misal:
> `flutter run --dart-define=API_URL=http://192.168.1.10:8000/api`

## Akun demo

Sama dengan web: `petugas.kota-bandung@simlab.test` / `password`
(atau akun petugas lain sesuai jadwal yang dibuat `JadwalHariIniSeeder`).

## Struktur

```
lib/
  main.dart                 # entry + auth gate + auto-sync start
  theme.dart                # design tokens (spec 15.1/15.5)
  models.dart               # User/School/Schedule/Student/ExaminationEntry
  services/
    api.dart                # HTTP client (token, login, schedules, lookup, submit)
    local_db.dart           # sqflite: antrian pending_sync + draft + cache siswa
    sync_service.dart       # auto-retry FIFO saat online (spec 9.3)
  screens/
    login_screen.dart
    home_shell.dart         # bottom nav 3 tab: Input / Riwayat / Jadwal
    input_wizard_screen.dart  # wizard 4 langkah (spec 6.1) + tombol global
    riwayat_screen.dart     # riwayat + status sinkronisasi (spec 6.2)
    jadwal_screen.dart      # jadwal kunjungan
  utils/format.dart         # format tanggal/angka Indonesia
```

## Verifikasi

```bash
flutter analyze   # 0 issues
flutter test      # semua lulus
```
