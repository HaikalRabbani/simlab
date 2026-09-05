# SIMLAB Jabar — Spesifikasi Teknis Lengkap

**Nama sistem:** SIMLAB Jabar (Sistem Informasi Skrining Kesehatan Siswa)
**Instansi:** Dinas Pendidikan Provinsi Jawa Barat
**Domain bisnis:** Skrining kesehatan siswa SMA/SMK/MA — deteksi penyalahgunaan zat (narkoba/alkohol) menggunakan strip uji cepat, dilakukan oleh petugas lapangan yang berkunjung ke sekolah.

Dokumen ini adalah spesifikasi lengkap untuk dikembangkan oleh AI coding agent. Semua field, dropdown, status, dan aturan bisnis diambil dari mockup resmi (5 layar web + 4 layar mobile). Ikuti spesifikasi ini apa adanya kecuali ada instruksi tambahan dari pemilik proyek.

---

## 1. Ringkasan Proyek

Sistem terdiri dari **satu backend API** yang melayani **tiga permukaan (surface)**:

1. **Web — Petugas Lapangan**: input hasil pemeriksaan siswa per sekolah, lihat riwayat input hari itu, lihat jadwal kunjungan.
2. **Web — Dinas Provinsi**: dashboard agregat, analisis demografi, daftar & status skrining tiap sekolah, pengelolaan jadwal dan pengguna.
3. **Mobile — Petugas Lapangan**: fungsi sama seperti web petugas, tapi **wajib offline-first** karena koneksi internet di lokasi sekolah tidak stabil.

Web Petugas dan Web Dinas adalah **satu aplikasi web**, dibedakan lewat role & routing — bukan dua project terpisah.

---

## 2. Tujuan & Ruang Lingkup

**Tujuan:**
- Mendigitalkan proses skrining kesehatan siswa yang sebelumnya manual.
- Memberi Dinas Provinsi visibilitas real-time atas hasil skrining se-Jawa Barat tanpa membuka identitas siswa individual.
- Menjaga jejak audit (audit trail) yang tidak bisa dimanipulasi petugas lapangan setelah data terkirim.
- Mendukung kerja lapangan tanpa koneksi internet stabil (offline-first pada mobile).

**Ruang lingkup (in-scope) — MVP:**
- Input pemeriksaan per siswa dengan 7 parameter uji.
- Riwayat input + status sinkronisasi.
- Jadwal kunjungan sekolah untuk petugas.
- Dashboard agregat provinsi.
- Analisis demografi (gender, jenjang, kelas).
- Daftar sekolah + status skrining + rekomendasi tindak lanjut.
- Role-based access control (petugas, pengawas wilayah, dinas provinsi).
- Sinkronisasi offline-to-online di mobile.

**Di luar lingkup (out-of-scope) untuk MVP** — lihat juga Bagian 14:
- Modul "Peta Wilayah" (ada di nav tapi belum ada mockup detail — treat sebagai halaman map sederhana pakai data kab/kota yang sudah ada).
- Modul "Laporan Periodik" & "Ekspor Data" detail UI (belum ada mockup — implementasikan sebagai ekspor CSV/PDF dari data yang sudah ada di Dashboard & Daftar Sekolah).
- Modul "Pengguna & Akses" detail UI (belum ada mockup — implementasikan CRUD user + assign role + assign wilayah sederhana).
- Modul "Stok Alat Uji" (ada di nav sidebar petugas tapi belum ada mockup — implementasikan sebagai pencatatan stok strip sederhana per sekolah/petugas).
- Integrasi laboratorium eksternal untuk uji konfirmasi (saat ini cukup dicatat sebagai status/rencana tindak lanjut, bukan integrasi sistem).

---

## 3. Aktor & Peran (RBAC)

| Role | Scope | Akses |
|---|---|---|
| **Petugas Lapangan** | Diri sendiri + sekolah pada jadwalnya | Input pemeriksaan, lihat riwayat & jadwal miliknya sendiri. **Tidak bisa** mengedit data yang sudah terkirim ke server. |
| **Pengawas Wilayah** | 1 kab/kota | Melihat identitas siswa dengan hasil reaktif di wilayahnya. Menerima & menyetujui pengajuan koreksi data dari petugas. |
| **Dinas Provinsi (admin)** | Seluruh provinsi | Dashboard agregat, analisis demografi, daftar sekolah, kelola jadwal skrining, kelola pengguna & akses. **Tidak pernah** melihat nama siswa individual — hanya data agregat/statistik. |

**Aturan akses kritis (harus ditegakkan di level API/backend, bukan cuma disembunyikan di UI):**
- Identitas siswa (nama, NISN, tanggal lahir) dengan hasil reaktif hanya bisa diakses oleh: (a) petugas yang menginput data tersebut, dan (b) pengawas wilayah tempat sekolah itu berada.
- Dinas Provinsi hanya menerima data dalam bentuk agregat/statistik tanpa nama siswa.
- Setiap query harus di-scope sesuai `wilayah_id` milik user yang login (kecuali role Dinas Provinsi yang punya akses provinsi penuh, dan role Petugas yang di-scope ke sekolah pada jadwalnya).

---

## 4. Arsitektur & Tech Stack

- **Backend**: Laravel (REST API), satu backend melayani web & mobile.
  - Auth: Laravel Sanctum (token-based, cocok untuk SPA + mobile).
  - Otorisasi: Laravel Policy/Gate per role + scope wilayah.
  - Audit trail: package `spatie/laravel-activitylog` atau tabel `audit_log` custom.
- **Web (Petugas + Dinas)**: Vue 3 + Vue Router + Pinia, konsumsi REST API di atas.
  - Reuse pola desain dari project [[pos-gw]] milik pemilik proyek (struktur folder, state management) bila memungkinkan agar konsisten.
- **Mobile (Petugas)**: Flutter.
  - Local storage: `sqflite` atau `Hive` untuk antrian offline (`pending_sync` queue).
  - Background sync: cek konektivitas (`connectivity_plus`), auto-retry kirim data pending saat online.
- **Database**: MySQL/PostgreSQL (pilih salah satu, PostgreSQL disarankan untuk query agregat/analisis yang lebih berat).
- **Deployment**: bebas menyesuaikan infrastruktur yang tersedia (VPS/cloud), tidak ada batasan spesifik dari mockup.

---

## 5. Modul & Fitur per Aplikasi

### 5.1 Web/Mobile — Petugas Lapangan

Sidebar nav: **Input Pemeriksaan**, **Riwayat Input**, **Jadwal Kunjungan** (grup "Petugas Lapangan") + **Daftar Sekolah**, **Stok Alat Uji** (grup "Data").

1. **Input Pemeriksaan** — wizard 4 langkah (lihat Bagian 6.1)
2. **Riwayat Input** — daftar hasil yang sudah diinput hari itu + status kirim (lihat Bagian 6.2)
3. **Jadwal Kunjungan** — kalender/daftar sekolah yang harus dikunjungi petugas (belum ada mockup detail — implementasikan sebagai list jadwal per hari dengan nama sekolah, tanggal, sesi (pagi/siang))
4. **Daftar Sekolah** (read-only, versi terbatas dari milik Dinas — hanya sekolah pada wilayah tugasnya)
5. **Stok Alat Uji** — catatan stok strip uji per jenis parameter yang dipegang petugas

### 5.2 Web — Dinas Provinsi

Sidebar nav: **Dashboard** (grup "Dinas Provinsi") + **Analisis Demografi**, **Daftar Sekolah**, **Peta Wilayah** (grup sama) + **Laporan Periodik**, **Ekspor Data** (grup "Pelaporan") + **Jadwal Skrining**, **Pengguna & Akses** (grup "Pengelolaan").

1. **Dashboard Skrining Provinsi** (lihat Bagian 6.3)
2. **Analisis Demografi** (lihat Bagian 6.4)
3. **Daftar Sekolah** (lihat Bagian 6.5)
4. **Peta Wilayah** — visualisasi peta Jawa Barat dengan warna per tingkat reaktif per kab/kota (reuse data yang sama dengan heatmap di Dashboard)
5. **Laporan Periodik** — generate laporan berdasarkan rentang periode
6. **Ekspor Data** — ekspor data agregat ke CSV/PDF/Excel
7. **Jadwal Skrining** — CRUD jadwal kunjungan petugas ke sekolah (sumber data untuk "Jadwal Kunjungan" di sisi petugas)
8. **Pengguna & Akses** — CRUD user, assign role, assign wilayah scope

### 5.3 Mobile — Petugas Lapangan

Bottom nav 3 tab: **Input**, **Riwayat**, **Jadwal**. Fungsi identik dengan web petugas (5.1 poin 1–3), tapi:
- Semua input tersimpan lokal dulu di device sebelum dikirim ke server.
- Header tiap layar menampilkan avatar inisial petugas yang login (contoh: "RK") dan indikator jaringan (opsional).
- Progress bar step di bagian atas layar wizard (lihat Bagian 6.1).

---

## 6. Detail Layar (Field-by-Field)

### 6.1 Input Pemeriksaan (Web & Mobile) — Wizard 4 Langkah

**Step indicator:** Pilih sekolah → Profil siswa → Hasil uji → Konfirmasi

**Konteks halaman:** nama sekolah + sesi (pagi/siang) + tanggal, ditampilkan sebagai subjudul. Progress: "Peserta ke-N dari total target sesi".

**Langkah 1 — Sekolah** *(terkunci dari jadwal, read-only, auto-terisi dari data Jadwal Kunjungan petugas hari itu)*
- Nama sekolah
- NPSN
- Kab/Kota
- Kecamatan

**Langkah 2 — Profil Siswa**
- NISN (input, dengan lookup: ketik NISN → auto-isi nama/kelas/dll dari data sekolah jika sudah terdaftar; jika belum ada, isi manual)
- Nama lengkap (text)
- Kelas (text/select, contoh: "XI IPA 3")
- Jenis kelamin (select: Laki-laki / Perempuan)
- Tanggal lahir (date picker)
- Pendamping saat pemeriksaan (select/text, **wajib diisi** — contoh: "Guru BK — Dra. Siti Rohmah"). Validasi: pemeriksaan siswa tidak boleh berlangsung tanpa pendamping dari pihak sekolah.
- Info banner: "Data tersimpan otomatis di perangkat. Bila sinyal terputus, pemeriksaan tetap bisa dilanjutkan dan akan terkirim saat jaringan kembali."

**Langkah 3 — Hasil Uji**
- Info banner: "Baca strip 5 menit setelah sampel diteteskan. Pilih Invalid bila garis kontrol tidak muncul — pemeriksaan wajib diulang dengan strip baru."
- Metadata: Kode lot strip (contoh "RT-2609-A"), tanggal kedaluwarsa strip
- 7 parameter, tiap parameter berupa toggle button 3 pilihan (**Negatif** / **Positif** / **Invalid**), default belum terpilih:
  1. **THC** — Tetrahydrocannabinol (ganja)
  2. **AMP** — Amfetamin
  3. **MET** — Metamfetamin (sabu)
  4. **MOP** — Morfin / opiat
  5. **BZO** — Benzodiazepin
  6. **TRA** — Tramadol
  7. **Alkohol** — Etanol, uji saliva
- Progress indicator "N dari 7 parameter terisi" — semua 7 wajib diisi sebelum lanjut.
- **Jika ada ≥1 parameter Positif** → tampilkan banner merah "N parameter reaktif — tindak lanjut wajib" + nama parameter yang reaktif, dan section tambahan wajib diisi:
  - Rencana tindak lanjut (select, contoh opsi: "Rujuk uji konfirmasi laboratorium", kemungkinan opsi lain: "Pantau rutin", "Skrining ulang")
  - Sampel disegel (select: Ya/Tidak — jika Ya, tampilkan input kode segel, contoh "SG-0412")
  - Catatan petugas (textarea, opsional — kondisi sampel, pengulangan uji, dsb.)
- Jika **tidak ada** parameter reaktif, section rencana tindak lanjut **tidak ditampilkan**.
- Banner privasi (khusus saat ada hasil reaktif): "Hasil ini bersifat rahasia. Identitas siswa hanya terbaca oleh Anda dan pengawas wilayah. Dinas menerima data ini dalam bentuk agregat tanpa nama."

**Langkah 4 — Konfirmasi**
- Ringkasan semua data dari langkah 1–3 untuk direview sebelum submit.
- Tombol: "Kembali" dan "Simpan & kirim" (submit — coba kirim ke server; jika gagal/offline, simpan sebagai `pending_sync`).

**Tombol global di header:** "Simpan draf" (simpan tanpa submit, bisa dilanjut nanti) dan "Simpan & Input berikutnya" (submit lalu langsung buka form kosong untuk siswa berikutnya, mempercepat alur input massal).

### 6.2 Riwayat Input (Web & Mobile)

**Header:** "Hasil yang sudah Anda kirim hari ini" + tanggal. Tombol: "Unduh rekap sesi", "Kirim N data tertunda" (badge jumlah pending).

**Summary cards (4):**
1. Total diperiksa hari ini (+ target sesi, contoh "147 / Target sesi: 180 siswa")
2. Terkirim ke server (+ waktu sinkronisasi terakhir)
3. Menunggu sinkronisasi (+ keterangan "tersimpan di perangkat, aman")
4. Hasil reaktif (+ keterangan sudah dirujuk uji konfirmasi)

**Banner (mobile, saat ada data pending):** "N data belum terkirim. Tersimpan aman di perangkat dan akan dikirim otomatis saat jaringan stabil."

**Daftar entri**, dikelompokkan 2 section: "Menunggu sinkronisasi" dan "Sudah terkirim · N entri". Kolom per baris:
- Waktu input
- Nama siswa
- NISN
- Kelas
- Jenis kelamin (L/P)
- Hasil uji (ringkasan: "Negatif semua" / "[PARAMETER] reaktif" / "[Parameter] invalid — diulang")
- Status kirim (badge: Terkirim / Menunggu)

**Footer note:** "Data tersimpan di perangkat bila jaringan terputus dan terkirim otomatis saat sinyal kembali. Entri yang sudah terkirim tidak dapat diubah petugas — koreksi diajukan lewat pengawas wilayah agar jejak audit tetap utuh."

### 6.3 Dashboard Skrining Provinsi (Web Dinas)

**Header:** "Dashboard Skrining Provinsi" + subjudul periode aktif, jumlah kab/kota, timestamp update terakhir. Tombol: "Ubah periode", "Unduh laporan".

**Summary cards (5):**
1. Siswa diperiksa (+ target tahunan & persentase capaian)
2. Sekolah terjangkau (+ dari total sekolah sasaran)
3. Hasil reaktif (+ persentase dari total pemeriksaan)
4. Sudah uji konfirmasi (+ sisa yang masih menunggu jadwal lab)
5. Petugas aktif (+ tersebar di berapa kab/kota)

**Heatmap "Tingkat hasil reaktif per kabupaten/kota":**
- Grid card per kab/kota (semua 27 kab/kota Jawa Barat)
- Tiap card: nama wilayah, persentase reaktif, jumlah reaktif dari total siswa diperiksa
- Color coding 3 tingkat: `<0.7%` (hijau muda/netral), `0.7–1.6%` (kuning/oranye muda), `≥1.6%` (merah) — tampilkan legenda di pojok kanan atas section

**Panel "Reaktif menurut jenis parameter":**
- Horizontal bar chart 7 parameter (THC, TRA/Tramadol, Alkohol, MET, BZO, AMP, MOP), diurutkan dari jumlah reaktif terbanyak
- Catatan di bawah chart: "Satu siswa dapat reaktif pada lebih dari satu parameter, sehingga jumlah di atas melebihi [total hasil reaktif]. Angka ini adalah hasil skrining awal — status akhir mengikuti uji konfirmasi laboratorium."

**Panel "Sekolah dengan temuan tertinggi"** (perlu prioritas pendampingan):
- Tabel: Nama sekolah, Kab/Kota, jumlah diuji, jumlah reaktif, persentase (diurutkan tertinggi ke terendah, top ~7)

### 6.4 Analisis Demografi (Web Dinas)

**Header:** total pemeriksaan + periode + cakupan wilayah. Tombol: "Bandingkan periode", "Unduh tabel".

**Filter tab ganda:**
- Wilayah: Semua wilayah / Bandung Raya / Bodebek / Pantura / Priangan Timur
- Jenjang: Semua jenjang / SMA / SMK / MA

**Panel "Menurut jenis kelamin":**
- Bar horizontal per gender (Laki-laki, Perempuan): persentase reaktif + jumlah reaktif dari total siswa gender tsb yang diperiksa
- Insight note otomatis (contoh: "Temuan pada siswa laki-laki N kali lebih tinggi. Selisih ini konsisten di seluruh wilayah dan dapat menjadi dasar penyesuaian sasaran program pencegahan.")

**Panel "Menurut tingkat kelas":**
- Bar chart vertikal: Kelas X, XI, XII, SMA, SMK — tiap bar menampilkan persentase reaktif + jumlah reaktif/total diperiksa
- Insight note otomatis (contoh pola: temuan naik seiring jenjang kelas, dan lebih tinggi di SMK dibanding SMA)

**Tabel "Silang parameter dengan jenis kelamin dan jenjang":**
- Baris: 7 parameter (THC, Tramadol, Alkohol, MET, BZO, AMP, MOP)
- Kolom: Laki-laki, Perempuan, SMA, SMK, Kelas X, Kelas XI, Kelas XII, Total
- Footer note: "Jumlah hasil reaktif · satu siswa dapat reaktif di lebih dari satu parameter"

### 6.5 Daftar Sekolah (Web Dinas)

**Header:** total sekolah sasaran + status ("N sudah diskrining, N belum terjadwal"). Tombol: "Atur jadwal kunjungan", "Ekspor daftar".

**Filter bar:** Kabupaten/Kota (select), Jenjang (select), Status skrining (select: Sudah diskrining / Belum terjadwal / dst.), Tingkat temuan (select), Cari sekolah atau NPSN (text search), tombol "Terapkan".

**Summary cards (4):**
1. Sudah diskrining (+ % dari sekolah sasaran)
2. Terjadwal bulan ini
3. Belum terjadwal (+ catatan perlu koordinasi wilayah)
4. Temuan di atas 2% (+ direkomendasikan skrining ulang)

**Tabel "Sekolah yang sudah diskrining"** (diurutkan berdasarkan tingkat temuan tertinggi):
- Kolom: Nama sekolah, NPSN, Kab/Kota, Jenjang, Tanggal skrining terakhir, Jumlah diuji, Jumlah reaktif, Persentase, Tindak lanjut (badge status: **Pendampingan** [merah, >2.5%] / **Skrining ulang** [kuning, ~1.7–2.3%] / **Pantau rutin** [hijau, ~1–1.6%] / **Selesai** [hijau tua, <1%])

**Footer note:** "Dinas melihat rekapitulasi per sekolah. Identitas siswa dengan hasil reaktif tidak ditampilkan di tingkat ini — data tersebut hanya diakses petugas penginput dan pengawas wilayah yang berwenang, sesuai ketentuan perlindungan data pribadi."

---

## 7. Skema Data (Entitas & Kolom)

```
schools (sekolah)
- id
- npsn (unique)
- nama
- kab_kota
- kecamatan
- wilayah_group (Bandung Raya/Bodebek/Pantura/Priangan Timur) — untuk filter Analisis Demografi
- jenjang (SMA/SMK/MA)
- target_siswa (untuk hitung capaian)
- created_at, updated_at

students (siswa)
- id
- nisn (unique)
- nama
- kelas
- jenis_kelamin (L/P)
- tanggal_lahir
- school_id (FK -> schools)
- created_at, updated_at

users (pengguna)
- id
- nama
- email/username
- password
- role (petugas_lapangan / pengawas_wilayah / dinas_provinsi)
- wilayah_scope (kab_kota_id, nullable untuk role dinas_provinsi yang scope-nya seluruh provinsi)
- avatar_initial
- created_at, updated_at

screening_schedules (jadwal skrining)
- id
- school_id (FK)
- petugas_id (FK -> users)
- tanggal
- sesi (pagi/siang)
- target_siswa
- status (terjadwal/berlangsung/selesai)

screening_sessions (sesi kunjungan aktif, opsional — bisa digabung ke schedule)
- id
- schedule_id (FK)
- tanggal_mulai, tanggal_selesai

examinations (pemeriksaan)
- id
- session_id / schedule_id (FK)
- student_id (FK -> students)
- petugas_id (FK -> users)
- waktu_input (timestamp)
- pendamping (text, wajib)
- catatan_petugas (text, nullable)
- rencana_tindak_lanjut (enum, nullable — hanya wajib jika ada reaktif: "rujuk_uji_konfirmasi" / "pantau_rutin" / "skrining_ulang")
- sampel_disegel (boolean, nullable)
- kode_segel (text, nullable)
- status_kirim (enum: pending_sync / terkirim)
- is_locked (boolean — true setelah terkirim, tidak bisa diedit langsung oleh petugas)
- strip_lot_code (text)
- strip_expiry_date (date)
- created_at, updated_at (created_at = waktu pemeriksaan sebenarnya, untuk kasus offline harus disimpan dari waktu device, bukan waktu server terima)

examination_results (hasil per parameter)
- id
- examination_id (FK -> examinations)
- parameter (enum: THC/AMP/MET/MOP/BZO/TRA/ALKOHOL)
- hasil (enum: negatif/positif/invalid)

correction_requests (pengajuan koreksi data)
- id
- examination_id (FK)
- diajukan_oleh (FK -> users, petugas)
- alasan (text)
- status (menunggu/disetujui/ditolak)
- ditinjau_oleh (FK -> users, pengawas_wilayah, nullable)
- catatan_peninjau (text, nullable)
- created_at, updated_at

audit_logs
- id
- user_id (FK)
- examination_id (FK, nullable)
- aksi (text, contoh: "submit_examination", "approve_correction")
- data_sebelum (json, nullable)
- data_sesudah (json, nullable)
- created_at

test_strip_stock (stok alat uji — modul "Stok Alat Uji")
- id
- petugas_id / school_id (FK)
- parameter
- jumlah_stok
- lot_code
- expiry_date
- updated_at
```

**Relasi kunci:**
- 1 school → banyak students, banyak screening_schedules
- 1 student → banyak examinations (riwayat dari waktu ke waktu)
- 1 examination → tepat 7 examination_results (satu per parameter)
- 1 examination → 0 atau banyak correction_requests

---

## 8. Aturan Bisnis & Validasi

1. **7 parameter wajib semua terisi** sebelum examination bisa disimpan/submit (tidak boleh ada yang kosong).
2. **Pendamping wajib diisi** — pemeriksaan tidak boleh disubmit tanpa nama pendamping dari pihak sekolah.
3. Jika **≥1 parameter = Positif** → field `rencana_tindak_lanjut` dan `sampel_disegel` menjadi **wajib**.
4. Jika **semua parameter Negatif** (atau ada Invalid tapi tidak ada Positif) → field tindak lanjut **tidak wajib/tidak ditampilkan**.
5. Parameter **Invalid** = strip gagal baca (garis kontrol tidak muncul) → pemeriksaan untuk parameter itu **wajib diulang dengan strip baru** sebelum bisa dianggap final; sistem harus bisa menandai status "diulang" di riwayat.
6. Setelah `status_kirim = terkirim`, examination menjadi **immutable** (`is_locked = true`) bagi petugas. Perubahan hanya lewat alur `correction_requests` yang disetujui pengawas wilayah — dan perubahan itu tercatat di `audit_logs`.
7. **Data reaktif dengan identitas siswa** hanya bisa dibaca oleh: petugas yang menginput + pengawas wilayah sekolah tersebut. Endpoint API untuk role `dinas_provinsi` **tidak boleh** mengembalikan field nama/NISN siswa — hanya angka agregat.
8. Kalkulasi **persentase reaktif** = (jumlah examination dengan ≥1 hasil positif) / (total examination) × 100, dihitung per scope (kab/kota, sekolah, gender, jenjang, kelas — sesuai konteks panel).
9. **Threshold badge tindak lanjut** di Daftar Sekolah (dari observasi mockup, boleh disesuaikan/dikonfirmasi ke pemilik produk):
   - `< 1.0%` → "Selesai"
   - `1.0% – 1.6%` → "Pantau rutin"
   - `1.7% – 2.3%` → "Skrining ulang"
   - `> 2.3%` → "Pendampingan"
10. Field terkunci di Langkah 1 (info sekolah) — hanya bisa diisi otomatis dari jadwal kunjungan yang sedang aktif untuk petugas yang login, tidak bisa dipilih bebas.

---

## 9. Alur Offline-First & Sinkronisasi (khusus Mobile, dan idealnya juga Web via local storage)

1. Semua input pada wizard (langkah 1–4) disimpan ke local storage device **secara realtime** (bukan hanya saat submit) supaya tidak hilang jika app tertutup/koneksi putus di tengah input.
2. Saat "Simpan & kirim" ditekan:
   - Coba kirim ke server via API.
   - Jika sukses → `status_kirim = terkirim`, hapus/tandai selesai di antrian lokal.
   - Jika gagal (timeout/offline) → simpan sebagai `status_kirim = pending_sync` di antrian lokal, tetap tampil di Riwayat Input dengan badge "Menunggu".
3. **Auto-retry**: begitu device kembali online (deteksi via `connectivity_plus` atau polling), sistem otomatis mengirim ulang semua entri `pending_sync` secara berurutan (FIFO berdasarkan waktu input).
4. Tombol manual "Kirim N data tertunda" di halaman Riwayat untuk memicu retry manual kapan saja.
5. **Waktu pemeriksaan yang dicatat** harus waktu device saat input dilakukan (bukan waktu server menerima), supaya urutan riwayat & data reaktif akurat meski terkirim belakangan.
6. Konflik: karena tiap examination dibuat oleh 1 petugas untuk 1 siswa di 1 sesi, risiko konflik data rendah — tapi tetap gunakan `client_generated_uuid` sebagai idempotency key saat submit ke server, supaya retry yang terkirim dobel tidak membuat data duplikat.

---

## 10. Keamanan & Privasi Data

- Autentikasi berbasis token (Sanctum) dengan expiry & refresh.
- Semua endpoint API wajib melalui middleware otorisasi berbasis role + wilayah scope (lihat Bagian 3).
- Data siswa (nama, NISN, tanggal lahir) **tidak boleh** ikut ter-return di response endpoint yang dipakai role `dinas_provinsi`.
- Enkripsi data sensitif saat disimpan di local storage mobile (misal pakai `Hive` dengan encryption box) — karena device petugas bisa hilang/dicuri.
- Audit log wajib mencatat setiap: submit pemeriksaan baru, pengajuan koreksi, persetujuan/penolakan koreksi, perubahan role/akses user.
- HTTPS wajib untuk semua komunikasi API.
- Rate limiting pada endpoint login untuk mencegah brute force.

---

## 11. API Endpoints (Draft)

```
Auth
POST   /api/login
POST   /api/logout
GET    /api/me

Jadwal & Sekolah
GET    /api/schedules?petugas_id=&tanggal=
GET    /api/schools
GET    /api/schools/{id}
GET    /api/schools/{id}/students?nisn=   (lookup untuk auto-fill)

Pemeriksaan
POST   /api/examinations                 (submit hasil pemeriksaan, idempotent via client_uuid)
GET    /api/examinations?petugas_id=&tanggal=   (untuk Riwayat Input)
GET    /api/examinations/{id}
POST   /api/examinations/{id}/correction-request

Dashboard & Analisis (role: dinas_provinsi, agregat saja)
GET    /api/dashboard/summary?periode=
GET    /api/dashboard/reactive-by-region?periode=
GET    /api/dashboard/reactive-by-parameter?periode=
GET    /api/dashboard/top-schools?periode=
GET    /api/demographics/by-gender?wilayah=&jenjang=
GET    /api/demographics/by-grade?wilayah=&jenjang=
GET    /api/demographics/cross-table?wilayah=&jenjang=

Daftar Sekolah (Dinas)
GET    /api/schools/status?kab_kota=&jenjang=&status=&tingkat_temuan=&search=

Pengelolaan (Dinas)
GET/POST/PUT/DELETE  /api/schedules
GET/POST/PUT/DELETE  /api/users

Koreksi (Pengawas Wilayah)
GET    /api/correction-requests?status=menunggu
POST   /api/correction-requests/{id}/approve
POST   /api/correction-requests/{id}/reject

Stok Alat Uji
GET/POST/PUT  /api/test-strip-stock
```

---

## 12. Non-Functional Requirements

- **Performa**: query agregat dashboard (63 ribu+ pemeriksaan) harus tetap cepat — gunakan indexing yang tepat di kolom `school_id`, `kab_kota`, `tanggal`, `parameter`, dan pertimbangkan materialized view/cache (Redis) untuk angka-angka dashboard yang tidak perlu realtime detik-per-detik.
- **Skalabilitas**: sistem harus bisa menangani 27 kab/kota, ratusan sekolah, puluhan ribu siswa per tahun ajaran.
- **Ketersediaan mobile offline**: aplikasi mobile harus tetap fungsional penuh (input + baca riwayat lokal) tanpa koneksi internet sama sekali.
- **Auditability**: semua perubahan data pasca-submit harus terlacak (siapa, kapan, apa yang berubah).
- **Lokalitas bahasa**: seluruh UI berbahasa Indonesia, format tanggal Indonesia (contoh: "12 September 2026").

---

## 13. Roadmap Pengembangan (Saran Urutan)

1. **Fase 1 — Fondasi**: skema database, auth + RBAC, API core (schools, students, schedules, examinations dasar tanpa offline sync dulu).
2. **Fase 2 — Web Petugas**: wizard Input Pemeriksaan lengkap + Riwayat Input (online-only dulu untuk validasi alur bisnis).
3. **Fase 3 — Web Dinas**: Dashboard + Analisis Demografi + Daftar Sekolah (query agregat & indexing).
4. **Fase 4 — Mobile + Offline Sync**: replikasi fitur petugas ke Flutter dengan local storage & auto-sync.
5. **Fase 5 — Hardening**: audit log lengkap, correction request flow, Pengguna & Akses, Stok Alat Uji, Ekspor/Laporan, Peta Wilayah.

---

## 14. Hal yang Perlu Diklarifikasi / Asumsi (Open Questions)

Bagian di bawah ini **belum ada di mockup** dan diisi dengan asumsi wajar — konfirmasi ulang ke pemilik produk sebelum implementasi final:

1. Opsi lengkap dropdown "Rencana tindak lanjut" — mockup baru menunjukkan 1 contoh ("Rujuk uji konfirmasi laboratorium"). Diasumsikan ada minimal: Rujuk uji konfirmasi laboratorium / Pantau rutin / Skrining ulang.
2. Definisi pasti threshold persentase untuk badge tindak lanjut di Daftar Sekolah (lihat Bagian 8 poin 9 — angka diestimasi dari data yang tertera di mockup).
3. Isi lengkap wilayah_group ("Bandung Raya", "Bodebek", "Pantura", "Priangan Timur") — mockup tidak merinci kab/kota mana masuk grup mana; perlu daftar resmi dari Dinas.
4. Desain layar "Peta Wilayah", "Laporan Periodik", "Ekspor Data", "Jadwal Skrining" (sisi Dinas), "Pengguna & Akses", dan "Stok Alat Uji" — belum ada mockup visual, diimplementasikan berdasarkan deskripsi fungsional di Bagian 5.2/5.1.
5. Mekanisme lookup NISN di Langkah 2 (Profil Siswa) — apakah data siswa sudah harus ada dari Dapodik/sistem lain sebelumnya, atau petugas boleh membuat data siswa baru langsung dari form ini.
6. Apakah 1 siswa bisa diperiksa lebih dari 1 kali dalam periode berbeda (untuk keperluan "skrining ulang") — jika ya, riwayat per siswa harus mendukung multiple examinations.

---

## 15. Spesifikasi Visual & Desain (UI/Design System)

> Bagian ini ditulis serinci mungkin dalam bentuk teks — termasuk kode warna hex hasil ekstraksi langsung dari file mockup — supaya bisa diimplementasikan tanpa perlu melihat gambar mockup sama sekali.

### 15.1 Design Tokens — Warna

| Token | Hex | Dipakai untuk |
|---|---|---|
| `--color-sidebar-bg` | `#0E2A47` | Background sidebar (navy tua) di semua halaman web |
| `--color-brand-accent` | `#0B6E6E` | Logo box "SL", nav item aktif di sidebar, link/teks aksen, tombol sekunder teal |
| `--color-brand-green` | `#1B7F4B` | Tombol primer (contoh: "Simpan & kirim", "Terapkan"), state terpilih hijau ("Negatif", badge "Selesai") |
| `--color-danger` | `#BE2B22` | Teks/angka reaktif, badge "Pendampingan" (teks), state terpilih merah ("Positif") |
| `--color-danger-strong` | `#E8544A` | Card heatmap tingkat reaktif tertinggi (≥1,6%) di Dashboard |
| `--color-danger-bg-soft` | `#FBE7E5` | Background badge "Pendampingan", background banner "N parameter reaktif" |
| `--color-warning-text` | `#A96A0C` | Teks badge "Skrining ulang" / "Menunggu" (status sinkronisasi) |
| `--color-warning-bg-soft` | `#FCF0DC` (web) / `#FBF0DE` (varian mobile) | Background badge "Skrining ulang", badge "Menunggu", heatmap tingkat menengah |
| `--color-success-bg-soft` | `#E4F2E9` / `#E3F1F0` | Background badge "Terkirim", badge "Pantau rutin", badge "Negatif semua" |
| `--color-page-bg` | `#EDF1F4` | Background utama konten (di luar card putih) |
| `--color-page-bg-alt` | `#F6F9FB` | Background alternatif area tabel/list (sedikit lebih terang dari page-bg) |
| `--color-card-bg` | `#FFFFFF` | Semua card/panel konten |
| `--color-border` | `#D3DDE5` / `#E7EDF1` | Border tabel, border card, garis pemisah |
| `--color-text-primary` | `#0E2A47` | Judul halaman, heading card (pakai warna navy yang sama dgn sidebar) |
| `--color-text-muted` | `#41607C` / `#4D6277` | Subjudul, label field, teks sekunder |
| `--color-text-muted-strong` | `#2A435D` / `#18334F` | Teks tabel body, angka |

**Prinsip warna status (konsisten di semua badge/indikator seluruh app):**
- **Merah** (`#BE2B22` teks / `#FBE7E5` bg) = reaktif/positif/butuh perhatian tinggi ("Pendampingan", parameter Positif)
- **Kuning/oranye** (`#A96A0C` teks / `#FCF0DC` bg) = menunggu/perlu tindakan ("Skrining ulang", "Menunggu" sinkron, heatmap menengah)
- **Hijau/teal** (`#1B7F4B` atau `#0B6E6E` teks / `#E4F2E9` bg) = aman/selesai/terkirim ("Negatif", "Terkirim", "Pantau rutin", "Selesai")

### 15.2 Tipografi

- Font: sans-serif modern (contoh: **Inter** atau setara — mockup pakai grotesque sans humanis, bukan sistem default seperti Arial). Gunakan Inter sebagai default kalau tidak ada preferensi lain.
- Heading halaman (contoh "Input Pemeriksaan", "Dashboard Skrining Provinsi"): bold, ukuran besar (~20–24px), warna `--color-text-primary`.
- Subjudul halaman (contoh "SMA Negeri 12 Bandung · Sesi pagi, 12 September 2026"): regular, ukuran kecil (~13–14px), warna `--color-text-muted`.
- Label field form: kecil (~12px), warna muted, di atas input.
- Angka besar di summary card (contoh "147", "63.531"): bold, sangat besar (~28–32px).
- Nama komponen/brand di sidebar ("SIMLAB Jabar"): bold putih ~16px. Tagline di bawahnya ("Sistem Informasi Skrining Kesehatan Siswa"): regular abu-abu terang ~11px, 2 baris.

### 15.3 Layout Umum (Web)

- **Sidebar kiri fixed**: lebar ~220–240px, background `#0E2A47`, isi dari atas ke bawah:
  1. Logo box: kotak rounded (radius ~8px) warna `#0B6E6E`, isi inisial putih bold ("SL")
  2. Nama app "SIMLAB Jabar" (bold putih) + tagline 2 baris (abu-abu muda)
  3. Garis pemisah tipis
  4. Grup navigasi berlabel kecil huruf abu-abu (contoh label grup: "Petugas Lapangan", "Data" / "Dinas Provinsi", "Pelaporan", "Pengelolaan") — **tidak ada icon**, hanya teks
  5. Item nav: teks putih/abu-abu terang; item yang sedang aktif dapat background rounded warna `#0B6E6E` dengan teks putih bold
  6. Di bagian paling bawah sidebar (menempel ke footer): kartu identitas user yang login — nama (bold putih) + role/wilayah (abu-abu kecil), contoh: "Rina Kusmawati / Petugas Lapangan / Wilayah Kota Bandung" atau "Drs. Hendra Gunawan / Dinas Pendidikan / Provinsi Jawa Barat"
- **Area konten kanan**: background `#EDF1F4`, dengan header halaman (judul + subjudul di kiri, tombol aksi di kanan — biasanya 1 tombol outline/secondary + 1 tombol solid primary hijau), lalu konten dalam card-card putih rounded (radius ~8–12px) dengan sedikit shadow tipis, disusun dalam grid/stack dengan gap konsisten (~16–24px).
- **Summary cards** (dipakai di Dashboard, Riwayat Input, Daftar Sekolah): grid horizontal 4–5 kolom sama lebar, tiap card berisi label kecil di atas, angka besar bold di tengah, keterangan kecil di bawah.
- **Tabel data**: header kolom abu-abu kecil uppercase/regular di atas, border bawah tipis per baris, hover state opsional, badge status di kolom kanan (rounded pill, padding horizontal, warna sesuai token status di atas).
- **Filter bar** (di Daftar Sekolah): baris horizontal berisi beberapa dropdown + 1 search input + 1 tombol solid di ujung kanan ("Terapkan").
- **Banner info/peringatan**: full-width dalam card, ada garis vertikal warna aksen di sisi kiri (border-left ~3–4px), background sangat soft sesuai jenis pesan (biru/teal untuk info netral, merah untuk peringatan reaktif), teks di dalamnya.

### 15.4 Komponen Spesifik

**Tombol:**
- Primary: background `#1B7F4B` atau `#0B6E6E` (hijau/teal solid), teks putih bold, rounded (~6–8px)
- Secondary/outline: border abu-abu, background putih/transparan, teks navy

**Toggle 3-pilihan (Negatif/Positif/Invalid) — dipakai di form Hasil Uji:**
- 3 tombol sejajar horizontal dalam 1 grup per parameter
- State terpilih "Negatif" → background `#1B7F4B` (hijau solid), teks putih
- State terpilih "Positif" → background `#BE2B22` atau merah solid senada, teks putih
- State terpilih "Invalid" → outline abu-abu netral (tidak berwarna solid)
- State belum terpilih → semua 3 tombol outline/abu-abu netral, teks abu-abu

**Badge/pill status** (dipakai di semua tabel & list):
- Bentuk pill penuh (border-radius besar/rounded-full), padding horizontal ~10-12px vertikal ~4px, teks kecil bold, warna sesuai kombinasi token status di 15.1

**Stepper/wizard progress** (dipakai di Input Pemeriksaan — web & mobile):
- 4 titik/segmen horizontal mewakili 4 langkah: "Pilih sekolah" → "Profil siswa" → "Hasil uji" → "Konfirmasi"
- Langkah selesai: bulatan hijau dengan centang (✓) + garis penghubung hijau solid
- Langkah aktif: bulatan/segmen teal terisi
- Langkah belum tercapai: bulatan/segmen abu-abu outline, teks abu-abu muda

**Form field:**
- Input text: border abu-abu tipis, background putih atau sangat abu-abu muda `#F6F9FB` kalau field read-only/terkunci (contoh: field sekolah di Langkah 1 yang "Terkunci dari jadwal")
- Field terkunci/read-only ditandai dengan background abu-abu muda + kadang label kecil "Terkunci dari jadwal" di pojok kanan atas card

**Card sekolah pada Heatmap Dashboard:**
- Grid kartu kecil, tiap kartu: nama wilayah (bold), persentase besar (bold, warna sesuai tingkat: hijau/kuning/merah), keterangan kecil "N dari M siswa" di bawah
- Background kartu mengikuti tingkat reaktif: **hijau/netral muda** untuk <0,7%, **kuning muda** (`#FCF0DC`) untuk 0,7–1,6%, **merah muda** (`#FBE7E5`) untuk ≥1,6% — dengan aksen warna lebih pekat (`#E8544A`) di angka/border untuk yang tertinggi

### 15.5 Layout Mobile (Flutter)

- **Header/app bar**: background navy `#0E2A47`, berisi tombol back (‹) di kiri, judul halaman bold putih + subjudul kecil abu-abu di bawahnya, avatar inisial user bulat warna teal `#0B6E6E` di kanan (contoh: "RK")
- **Progress bar step**: garis horizontal tipis di bawah app bar, terbagi 4 segmen sesuai step wizard, segmen terlewati/aktif berwarna teal-hijau, sisanya abu-abu
- **Body**: background putih/abu sangat muda, konten dalam card rounded per section (mirip web tapi full-width, stack vertikal — bukan grid)
- **Bottom navigation**: 3 tab tetap di bawah — **Input**, **Riwayat**, **Jadwal** — dengan ikon sederhana (pensil/pen untuk Input, list untuk Riwayat, jam/clock untuk Jadwal) + label teks kecil di bawah ikon; tab aktif diberi warna teal, tab lain abu-abu
- **Summary cards** di Riwayat mobile: 3 kartu sejajar horizontal (bukan 4 seperti web), tiap kartu angka besar di tengah + label di bawah
- Semua warna, badge, dan komponen (toggle 3-pilihan, banner, badge status) **identik dengan web** — mobile hanya beda arrangement (vertikal/stack) karena layar sempit, bukan beda skema warna.

### 15.6 Catatan untuk AI CLI

- Semua kode warna di atas diekstrak langsung dari file gambar mockup asli menggunakan color sampling, jadi akurat — bukan tebakan. Gunakan sebagai `design tokens` (CSS variables / Tailwind config) di awal setup project.
- Kalau butuh detail piksel yang lebih presisi dari yang dijabarkan di sini (misal border-radius pasti, shadow exact, spacing grid pasti), gunakan asumsi desain modern standar (radius 8px, shadow tipis `0 1px 3px rgba(0,0,0,0.08)`, spacing based on 4px/8px scale) — bagian ini tidak kritis terhadap fungsi sistem, cukup konsisten.
- Prioritaskan kebenaran **struktur data, field, dan aturan bisnis** (Bagian 5–11) di atas kesempurnaan visual — visual di bagian ini adalah panduan approksimasi terbaik dari mockup, bukan pixel-perfect spec.

---

*Dokumen ini disusun berdasarkan analisis 5 mockup web (Input Pemeriksaan, Riwayat Input, Dashboard, Analisis Demografi, Daftar Sekolah) dan 4 mockup mobile (Profil Siswa, Hasil Uji, Riwayat & Sinkronisasi) milik proyek SIMLAB Jabar. Semua data pada mockup bersifat ilustrasi, bukan data riil.*
