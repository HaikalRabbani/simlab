# SIMLAB Jabar

**Sistem Informasi Skrining Kesehatan Siswa Jawa Barat** — aplikasi untuk mendukung program skrining penyalahgunaan zat (napza) pada siswa SMA/SMK/MA se-Jawa Barat. Petugas lapangan menginput hasil pemeriksaan via aplikasi mobile/web (termasuk mode offline), pengawas wilayah memvalidasi & menyetujui koreksi data, dan Dinas Provinsi memantau lewat dashboard agregat.

Spesifikasi lengkap (mockup & aturan bisnis): [`SIMLAB-JABAR-SPEC.md`](./SIMLAB-JABAR-SPEC.md).

---

## Daftar Isi

- [Tech Stack](#tech-stack)
- [Struktur Repo](#struktur-repo)
- [Peran & Aturan Akses](#peran--aturan-akses)
- [Cara Menjalankan](#cara-menjalankan)
  - [1. Backend (Laravel API)](#1-backend-laravel-api)
  - [2. Frontend (Web Vue 3)](#2-frontend-web-vue-3)
  - [3. Mobile (Flutter)](#3-mobile-flutter)
  - [4. Database (opsional: PostgreSQL via Docker)](#4-database-opsional-postgresql-via-docker)
- [Akun Demo](#akun-demo)
- [Ringkasan API](#ringkasan-api)
- [Keamanan yang Diterapkan](#keamanan-yang-diterapkan)
- [Alur Data Pemeriksaan & Koreksi](#alur-data-pemeriksaan--koreksi)
- [Catatan & Hal yang Belum Dikerjakan](#catatan--hal-yang-belum-dikerjakan)

---

## Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 (PHP 8.3+) — REST API, Sanctum (token auth), Policy/Gate per role |
| Frontend Web | Vue 3 + Vite + Pinia + Vue Router + Axios (+ Leaflet untuk peta wilayah) |
| Mobile | Flutter (offline-first: antrian `pending_sync`, draft lokal, cache siswa) |
| Database | SQLite (dev lokal, zero-config) / PostgreSQL 16 (produksi — lihat `docker-compose.yml`) |

---

## Struktur Repo

```
├── SIMLAB-JABAR-SPEC.md      # Spesifikasi lengkap: mockup, aturan bisnis, skema DB
├── docker-compose.yml        # PostgreSQL untuk staging/produksi
├── backend/                  # Laravel API (melayani web & mobile)
│   ├── app/Http/Controllers/ # Auth, Pemeriksaan, Siswa, Sekolah, Jadwal, Stok,
│   │                         # Koreksi, Audit Log, Dashboard, Demografi, Ekspor
│   ├── app/Policies/         # ExaminationPolicy (role + scope wilayah)
│   ├── app/Support/          # DashboardData (helper agregasi SQL)
│   ├── database/migrations/  # Skema: users, schools, students, examinations, dst.
│   ├── database/seeders/     # Data demo (SimlabSeeder, dll.)
│   └── tests/Feature/        # 28 test end-to-end alur API
├── frontend/                 # Web Vue 3 (petugas + dinas)
│   └── src/views/            # Dashboard, Sekolah, Siswa, Input Pemeriksaan,
│                             # Riwayat, Jadwal, Stok Alat Uji, Demografi, Peta
├── mobile/                   # Flutter (petugas lapangan, offline-first)
│   ├── lib/services/         # api.dart (REST), local_db.dart (sqflite), sync_service.dart
│   └── lib/screens/          # Login, Wizard Input, Riwayat, Jadwal, Ringkasan
└── UI reference/             # Screenshot mockup (web & mobile)
```

---

## Peran & Aturan Akses

| Peran | Scope | Bisa apa |
|---|---|---|
| **Petugas Lapangan** | Sekolah pada jadwalnya | Input pemeriksaan, lihat riwayat & jadwal sendiri, kelola stok strip. **Tidak bisa** mengedit data yang sudah terkirim — koreksi lewat pengawas. |
| **Pengawas Wilayah** | 1 kab/kota | Lihat siswa reaktif di wilayahnya, menerima & menyetujui/menolak koreksi data, lalu **memperbaiki datanya** setelah disetujui. |
| **Dinas Provinsi** | Seluruh provinsi | Dashboard agregat, analisis demografi, daftar sekolah, kelola jadwal & pengguna, ekspor CSV. **Tidak pernah** melihat nama siswa individual. |

Aturan kritis (ditegakkan di level API, bukan cuma UI):
- Identitas siswa (nama, NISN, tanggal lahir) + hasil reaktif hanya untuk: petugas penginput & pengawas wilayah sekolah tsb.
- Dinas hanya menerima data **agregat** — semua endpoint dashboard/ekspor memakai `authorizeDinas()`.
- Setiap query di-scope sesuai wilayah/role user yang login.

---

## Cara Menjalankan

### 1. Backend (Laravel API)

```bash
cd backend
composer install
cp .env.example .env        # lalu isi APP_KEY, dsb. (SQLite default untuk dev)
php artisan key:generate
php artisan migrate --seed  # skema + data demo
php artisan serve           # http://127.0.0.1:8000
```

Test:

```bash
php artisan test            # 28 test fitur (alur pemeriksaan, koreksi, ekspor, dsb.)
```

### 2. Frontend (Web Vue 3)

```bash
cd frontend
npm install
npm run dev                 # http://localhost:5173 (proxy /api → 127.0.0.1:8000)
```

### 3. Mobile (Flutter)

```bash
cd mobile
flutter pub get
flutter run                 # default API_URL http://10.0.2.2:8000/api (emulator Android)
```

Ganti base URL untuk perangkat fisik:

```bash
flutter run --dart-define=API_URL=https://api.example.com/api
```

### 4. Database (opsional: PostgreSQL via Docker)

```bash
docker compose up -d db
# lalu di backend/.env: DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_DATABASE=simlab, dst.
php artisan migrate --seed
```

---

## Akun Demo

Dibuat oleh `DatabaseSeeder` (password: `password`):

| Email | Role | Wilayah |
|---|---|---|
| `dinas@simlab.test` | Dinas Provinsi | — |
| `pengawas@simlab.test` | Pengawas Wilayah | Kota Bandung |
| `petugas@simlab.test` | Petugas Lapangan | Kota Bandung |

Data demo lain (27 kab/kota, sekolah, siswa, pemeriksaan) di-seed oleh `SimlabSeeder` — semua data **fiktif** (email `@simlab.test`, nama acak, NISN acak).

---

## Ringkasan API

Semua endpoint di bawah `/api`, autentikasi via `Authorization: Bearer <token>` (kecuali login).

| Metode | Endpoint | Akses | Keterangan |
|---|---|---|---|
| POST | `/login` | Publik | Rate limit 10/menit/IP (anti brute force) |
| POST | `/logout` · GET `/me` | Semua | — |
| GET | `/dashboard/*` , `/demographics/*` | Dinas | Agregat + filter periode/wilayah/jenjang |
| GET | `/export/{type}` | Dinas | CSV agregat (`regions`, `schools`, `parameters`) |
| GET/POST/PUT/DELETE | `/schools`, `/students` | Sesuai scope | Lookup siswa: `/schools/{id}/students?nisn=` |
| GET/POST | `/examinations` | Petugas | Submit idempoten via `client_uuid`; langsung `terkirim` + `is_locked` |
| POST | `/examinations/{id}/correction-request` | Petugas pemilik | Ajukan koreksi data terkirim |
| PUT | `/examinations/{id}` | Petugas (belum terkunci) / Pengawas (koreksi disetujui) | Perbaikan data |
| POST | `/correction-requests/{id}/approve` · `/reject` | Pengawas wilayah | Putuskan koreksi |
| GET/POST/PUT/DELETE | `/schedules` | Dinas (CRUD), petugas/pengawas (list) | Jadwal skrining |
| GET/POST/PUT/DELETE | `/test-strip-stock` | Petugas (miliknya sendiri) | Stok alat uji |
| GET | `/users` , `/audit-logs` | Dinas | Kelola user & jejak audit |

---

## Keamanan yang Diterapkan

- **Auth**: Sanctum token; token **berlaku 24 jam** (`SANCTUM_TOKEN_EXPIRATION`), semua token lama dihapus saat login ulang; prefix token (`SANCTUM_TOKEN_PREFIX`) untuk GitHub secret scanning.
- **Rate limiting**: login 10/menit/IP, seluruh API 120/menit per user/IP (`API_RATE_LIMIT`).
- **Otorisasi**: Policy + scope wilayah di semua endpoint; data siswa tidak pernah bocor ke role Dinas.
- **Input validation**: FormRequest ketat (7 parameter uji wajib, tanpa duplikat, kondisi reaktif → tindak lanjut & segel wajib, dll.).
- **Integritas data**: data pemeriksaan terkirim bersifat immutable (`is_locked`) — perbaikan lewat alur koreksi yang diaudit; siswa/sekolah/user ber-riwayat tidak bisa dihapus (mencegah cascade menghapus data final).
- **Audit log**: submit, koreksi (ajukan/approve/reject), CRUD user & pemeriksaan dicatat (hash password tidak pernah ikut tersimpan).
- **Mobile**: token & profil disimpan terenkripsi (`flutter_secure_storage` — Keychain/Keystore).
- **CSV export**: terlindung dari formula injection (nilai diawali `=`, `+`, `-`, `@` di-netralkan).
- **CORS**: bisa dipersempit via `CORS_ALLOWED_ORIGINS` di produksi.

---

## Alur Data Pemeriksaan & Koreksi

```
Petugas input hasil (mobile/web)
  └─ POST /examinations → server simpan, status=terkirim, is_locked=true
       (idempoten: retry offline dengan client_uuid sama tidak bikin data dobel)

Data salah? Petugas ajukan koreksi
  └─ POST /examinations/{id}/correction-request (alasan wajib)

Pengawas wilayah putuskan
  ├─ reject  → data tetap terkunci, petugas bisa ajukan ulang
  └─ approve → data DIBUKA (is_locked=false) untuk diperbaiki pengawas
        └─ Pengawas PUT /examinations/{id} (data hasil lengkap)
              └─ otomatis terkunci lagi (is_locked=true) — final & immutable
```

Semua langkah dicatat di `audit_logs`.

---

## Catatan & Hal yang Belum Dikerjakan

- **DB offline mobile belum terenkripsi**: `sqflite` (antrian `pending_sync`, draft, cache siswa) masih plaintext. Rencana sesuai spec: `sqflite_sqlcipher` atau Hive encryption box — butuh migrasi & pengujian di perangkat asli.
- **HTTPS**: wajib diaktifkan di server produksi (spec Bagian 10) — belum ada konfigurasi deploy.
- Endpoint `refresh` token belum ada — saat token habis, frontend/mobile otomatis minta login ulang (sudah ditangani interceptor 401 di kedua klien).