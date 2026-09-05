import 'package:flutter/material.dart';
import 'package:uuid/uuid.dart';

import '../models.dart';
import '../utils/format.dart' as fmt;
import '../services/api.dart';
import '../services/local_db.dart';
import '../services/sync_service.dart';
import '../theme.dart';

// Label parameter disamakan persis dengan web (kode + nama lengkap).
const _parameters = [
  ('THC', 'THC', 'Tetrahydrocannabinol (ganja)'),
  ('AMP', 'AMP', 'Amfetamin'),
  ('MET', 'MET', 'Metamfetamin (sabu)'),
  ('MOP', 'MOP', 'Morfin / opiat'),
  ('BZO', 'BZO', 'Benzodiazepin'),
  ('TRA', 'TRA', 'Tramadol'),
  ('ALKOHOL', 'Alkohol', 'Etanol, uji saliva'),
];

const _hasilLabel = {'negatif': 'Negatif', 'positif': 'Positif', 'invalid': 'Invalid'};
const _rencanaOptions = [
  ('rujuk_uji_konfirmasi', 'Rujuk uji konfirmasi laboratorium'),
  ('pantau_rutin', 'Pantau rutin'),
  ('skrining_ulang', 'Skrining ulang'),
];

const _stepTitles = ['Pilih sekolah', 'Profil siswa', 'Hasil uji', 'Konfirmasi'];

/// Info yang ditampilkan di AppBar Input:
/// baris 2 (konteks per langkah) + progress + "Langkah x dari 4 …".
class WizardHeaderInfo {
  final int step;
  final String stepTitle;

  /// Baris 2 AppBar: sekolah · sesi (langkah 1), nama siswa (langkah 2),
  /// "Hasil uji" / "Konfirmasi" (langkah 3/4).
  final String contextLine;

  /// Untuk "Peserta ke-N dari target sesi" (langkah 1).
  final String? schoolName;
  final String? sesi;
  final String? tanggal;
  final int targetSiswa;
  final int terkirimHariIni;

  /// Jumlah parameter yang sudah diisi (baris 4, langkah 3).
  final int paramTerisi;

  const WizardHeaderInfo({
    required this.step,
    required this.stepTitle,
    this.contextLine = '',
    this.schoolName,
    this.sesi,
    this.tanggal,
    this.targetSiswa = 0,
    this.terkirimHariIni = 0,
    this.paramTerisi = 0,
  });
}

class InputWizardScreen extends StatefulWidget {
  /// Dipanggil setiap kali info header berubah (langkah / sekolah / sesi),
  /// agar AppBar (HomeShell) menampilkan progress + sekolah + sesi + tanggal.
  final ValueChanged<WizardHeaderInfo>? onHeaderChanged;

  /// Dipanggil saat tombol kembali di AppBar ditekan.
  final VoidCallback? onBackRequested;

  const InputWizardScreen({super.key, this.onHeaderChanged, this.onBackRequested});

  @override
  State<InputWizardScreen> createState() => InputWizardScreenState();
}

class InputWizardScreenState extends State<InputWizardScreen> {
  int _step = 1;
  bool _loading = true;
  String? _error;

  List<Schedule> _schedules = [];
  int? _scheduleId;

  // profil siswa
  final _nisn = TextEditingController();
  final _nama = TextEditingController();
  final _kelas = TextEditingController();
  String _jenisKelamin = '';
  DateTime? _tanggalLahir;
  final _pendamping = TextEditingController();
  String _lookupState = ''; // '' | 'ditemukan' | 'baru'
  int? _studentId;

  // hasil uji
  final _stripLot = TextEditingController();
  DateTime? _stripExpiry;
  final Map<String, String> _hasil = {};
  String _rencana = '';
  bool? _sampelDisegel;
  final _kodeSegel = TextEditingController();
  final _catatan = TextEditingController();

  bool _submitting = false;
  int _terkirimHariIni = 0;

  Schedule? get _schedule =>
      _schedules.where((s) => s.id == _scheduleId).firstOrNull;

  bool get _adaReaktif => _hasil.values.contains('positif');
  int get _jumlahTerisi => _hasil.values.where((h) => h.isNotEmpty).length;

  @override
  void initState() {
    super.initState();
    // Update baris 2 header secara live saat nama siswa diketik.
    _nama.addListener(_notifyHeader);
    // Notifikasi pertama ditunda ke post-frame: saat initState, HomeShell
    // induk masih sedang build — memanggil setState-nya di sini memicu
    // "setState() called during build".
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _notifyHeader();
    });
    _muatJadwal();
  }

  /// Konteks baris 2 AppBar sesuai langkah aktif (sekolah · sesi untuk
  /// langkah 1, nama siswa untuk langkah 2, label langkah untuk 3/4).
  String _contextLine() {
    final schedule = _schedule;
    if (_step == 1) {
      if (schedule == null) return 'Pilih sekolah kunjungan';
      final sesi = schedule.sesi == 'pagi' ? 'Sesi pagi' : 'Sesi siang';
      final tgl =
          schedule.tanggal.isEmpty ? '' : fmt.tanggalLengkap(schedule.tanggal);
      return '${schedule.school.nama} · $sesi${tgl.isEmpty ? '' : ', $tgl'}';
    }
    if (_step == 2) {
      final nama = _nama.text.trim();
      if (nama.isEmpty) return 'Ketik NISN lalu Cari / isi manual';
      final kelas = _kelas.text.trim();
      return kelas.isEmpty ? nama : '$nama · $kelas';
    }
    if (_step == 4) {
      final nama = _nama.text.trim();
      return nama.isEmpty ? 'Konfirmasi' : 'Konfirmasi — $nama';
    }
    return _stepTitles[_step - 1];
  }

  void _notifyHeader() {
    final schedule = _schedule;
    widget.onHeaderChanged?.call(WizardHeaderInfo(
      step: _step,
      stepTitle: _stepTitles[_step - 1],
      contextLine: _contextLine(),
      schoolName: schedule?.school.nama,
      sesi: schedule?.sesi,
      tanggal: schedule?.tanggal,
      targetSiswa: schedule?.targetSiswa ?? 0,
      terkirimHariIni: _terkirimHariIni,
      paramTerisi: _jumlahTerisi,
    ));
  }

  /// Hitung ulang jumlah pemeriksaan tersimpan hari ini pada sesi ini
  /// ("Peserta ke-N"), lalu segarkan header.
  Future<void> _refreshPesertaKe() async {
    final schedule = _schedule;
    if (schedule == null) return;
    final tgl =
        '${DateTime.now().year}-${fmt.pad2(DateTime.now().month)}-${fmt.pad2(DateTime.now().day)}';
    try {
      final total = await Api.countExaminations(tgl, scheduleId: schedule.id);
      if (!mounted) return;
      setState(() => _terkirimHariIni = total);
      _notifyHeader();
    } catch (_) {
      // offline — biarkan 0
    }
  }

  void _ubahStep(int nilai) {
    setState(() => _step = nilai.clamp(1, 4));
    _notifyHeader();
  }

  @override
  void dispose() {
    _nama.removeListener(_notifyHeader);
    _nisn.dispose();
    _nama.dispose();
    _kelas.dispose();
    _pendamping.dispose();
    _stripLot.dispose();
    _kodeSegel.dispose();
    _catatan.dispose();
    super.dispose();
  }

  Future<void> _muatJadwal() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final today =
          '${DateTime.now().year}-${fmt.pad2(DateTime.now().month)}-${fmt.pad2(DateTime.now().day)}';
      final schedules = await Api.getSchedules(tanggal: today);
      if (!mounted) return;
      setState(() {
        _schedules = schedules;
        if (schedules.length == 1) _scheduleId = schedules.first.id;
        _loading = false;
      });
      // Muat jadwal juga selesai async (post-build), jadi aman di sini.
      _notifyHeader();
      _refreshPesertaKe();
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Gagal memuat jadwal.';
        _loading = false;
      });
    }
  }

  Future<void> _cariNisn() async {
    final nisn = _nisn.text.trim();
    final schedule = _schedule;
    if (nisn.isEmpty || schedule == null) return;
    try {
      final students = await Api.lookupStudents(schedule.school.id, nisn: nisn);
      if (!mounted) return;
      if (students.isNotEmpty) {
        final s = students.first;
        setState(() {
          _studentId = s.id;
          _nisn.text = s.nisn;
          _nama.text = s.nama;
          _kelas.text = s.kelas;
          _jenisKelamin = s.jenisKelamin;
          _tanggalLahir = DateTime.tryParse(s.tanggalLahir);
          _lookupState = 'ditemukan';
        });
        _notifyHeader();
      } else {
        setState(() {
          _studentId = null;
          _lookupState = 'baru';
        });
        _notifyHeader();
      }
    } catch (_) {
      // offline: coba cache lokal
      final cached = await LocalDb.instance.findCachedStudent(nisn);
      if (!mounted) return;
      if (cached.isNotEmpty) {
        final s = cached.first;
        setState(() {
          _studentId = s.id;
          _nisn.text = s.nisn;
          _nama.text = s.nama;
          _kelas.text = s.kelas;
          _jenisKelamin = s.jenisKelamin;
          _tanggalLahir = DateTime.tryParse(s.tanggalLahir);
          _lookupState = 'ditemukan';
        });
        _notifyHeader();
      } else {
        setState(() => _lookupState = 'baru');
        _notifyHeader();
      }
    }
  }

  ExaminationEntry _buildEntry({required String waktuInput}) {
    return ExaminationEntry(
      clientUuid: const Uuid().v4(),
      scheduleId: _scheduleId,
      schoolId: _schedule?.school.id,
      student: Student(
        id: _studentId,
        nisn: _nisn.text.trim(),
        nama: _nama.text.trim(),
        kelas: _kelas.text.trim(),
        jenisKelamin: _jenisKelamin,
        tanggalLahir: _tanggalLahir == null
            ? ''
            : '${_tanggalLahir!.year}-${fmt.pad2(_tanggalLahir!.month)}-${fmt.pad2(_tanggalLahir!.day)}',
      ),
      pendamping: _pendamping.text.trim(),
      catatanPetugas: _catatan.text.trim().isEmpty ? null : _catatan.text.trim(),
      stripLotCode: _stripLot.text.trim(),
      stripExpiryDate: _stripExpiry == null
          ? ''
          : '${_stripExpiry!.year}-${fmt.pad2(_stripExpiry!.month)}-${fmt.pad2(_stripExpiry!.day)}',
      rencanaTindakLanjut: _adaReaktif ? _rencana : null,
      sampelDisegel: _adaReaktif ? _sampelDisegel : null,
      kodeSegel: (_adaReaktif && _sampelDisegel == true && _kodeSegel.text.trim().isNotEmpty)
          ? _kodeSegel.text.trim()
          : null,
      hasil: _parameters
          .map((p) => ParameterResult(parameter: p.$1, hasil: _hasil[p.$1] ?? 'negatif'))
          .toList(),
      waktuInput: waktuInput,
    );
  }

  bool _validProfil() =>
      _nisn.text.trim().isNotEmpty &&
      _nama.text.trim().isNotEmpty &&
      _kelas.text.trim().isNotEmpty &&
      _jenisKelamin.isNotEmpty &&
      _tanggalLahir != null &&
      _pendamping.text.trim().isNotEmpty;

  bool _validHasil() {
    if (_stripLot.text.trim().isEmpty || _stripExpiry == null) return false;
    if (_jumlahTerisi < 7) return false;
    if (!_adaReaktif) return true;
    if (_rencana.isEmpty) return false;
    if (_sampelDisegel == null) return false;
    if (_sampelDisegel == true && _kodeSegel.text.trim().isEmpty) return false;
    return true;
  }

  void _lanjut() {
    setState(() => _error = null);
    if (_step == 2 && !_validProfil()) {
      setState(() => _error = 'Lengkapi profil siswa dan pendamping.');
      return;
    }
    if (_step == 3 && !_validHasil()) {
      setState(() => _error = 'Lengkapi 7 parameter dan bagian tindak lanjut.');
      return;
    }
    _ubahStep(_step + 1);
  }

  void _kembali() {
    setState(() => _error = null);
    _ubahStep(_step - 1);
  }

  /// Untuk tombol kembali di AppBar (HomeShell memanggil lewat GlobalKey).
  void goBack() {
    if (_step > 1) _kembali();
  }

  /// Kirim: coba langsung ke server; bila gagal/offline -> antrian pending_sync.
  Future<void> _simpan() async {
    if (!_validProfil() || !_validHasil()) {
      setState(() => _error = 'Data belum lengkap.');
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });

    final waktu = DateTime.now().toUtc().toIso8601String();
    final entry = _buildEntry(waktuInput: waktu);

    var terkirim = false;
    try {
      await Api.submitExamination(entry);
      terkirim = true;
    } catch (_) {
      // offline/gagal -> simpan ke antrian lokal
      await LocalDb.instance.queuePending(entry);
    }

    await LocalDb.instance.saveDraft(null);
    await SyncService.instance.syncPending();

    if (!mounted) return;
    setState(() {
      _submitting = false;
      _error = terkirim ? null : 'Tersimpan di perangkat — akan terkirim saat jaringan kembali.';
    });

    _resetForm();
    _ubahStep(1);
    _refreshPesertaKe();
  }

  void _resetForm() {
    _nisn.clear();
    _nama.clear();
    _kelas.clear();
    _jenisKelamin = '';
    _tanggalLahir = null;
    _pendamping.clear();
    _lookupState = '';
    _studentId = null;
    _stripLot.clear();
    _stripExpiry = null;
    _hasil.clear();
    _rencana = '';
    _sampelDisegel = null;
    _kodeSegel.clear();
    _catatan.clear();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_schedules.isEmpty) {
      return ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const SizedBox(height: 40),
          const Icon(Icons.event_busy, size: 48, color: AppColors.textMuted),
          const SizedBox(height: 12),
          const Center(
            child: Text('Belum ada jadwal kunjungan untuk hari ini.',
                style: AppType.meta),
          ),
        ],
      );
    }

    return ListView(
      padding: const EdgeInsets.only(bottom: 24),
      children: [
        if (_error != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: _Banner(
              message: _error!,
              color: AppColors.dangerBgSoft,
              borderColor: AppColors.danger,
              textColor: AppColors.danger,
            ),
          ),

        if (_step == 1) _buildStepSekolah(),
        if (_step == 2) _buildStepProfil(),
        if (_step == 3) _buildStepHasil(),
        if (_step == 4) _buildStepKonfirmasi(),
      ],
    );
  }

  Widget _buildStepSekolah() {
    final schedule = _schedule;
    return Column(
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_schedules.length > 1) ...[
                  const Text('Sesi kunjungan hari ini',
                      style: TextStyle(fontSize: 12, color: AppColors.textMuted)),
                  const SizedBox(height: 6),
                  DropdownButtonFormField<int>(
                    key: ValueKey('sesi-${_scheduleId ?? 'kosong'}'),
                    initialValue: _scheduleId,
                    isExpanded: true,
                    items: _schedules
                        .map((s) => DropdownMenuItem(
                              value: s.id,
                              child: Text(
                                '${s.school.nama} — ${s.sesi == 'pagi' ? 'pagi' : 'siang'}',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ))
                        .toList(),
                    onChanged: (v) => setState(() {
                      _scheduleId = v;
                      _notifyHeader();
                      _refreshPesertaKe();
                    }),
                  ),
                  const SizedBox(height: 12),
                ],
                if (schedule != null) ...[
                  // Info sekolah gaya "info-row" (label kecil di atas, nilai 14).
                  const _SchoolInfoRow(label: 'Nama sekolah'),
                  Text(schedule.school.nama,
                      style: AppType.bodyStrong),
                  const SizedBox(height: AppSpacing.lg),
                  const _SchoolInfoRow(label: 'NPSN'),
                  Text(schedule.school.npsn, style: AppType.body),
                  const SizedBox(height: AppSpacing.lg),
                  const _SchoolInfoRow(label: 'Kab/Kota'),
                  Text(schedule.school.kabKota, style: AppType.body),
                  const SizedBox(height: AppSpacing.lg),
                  const _SchoolInfoRow(label: 'Kecamatan'),
                  Text(schedule.school.kecamatan, style: AppType.body),
                ],
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _scheduleId == null ? null : _lanjut,
              child: Text('Lanjut ke ${_stepTitles[_step]}'),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildStepProfil() {
    return Column(
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 12),
          child: _Banner(
            message:
                'Data tersimpan otomatis di perangkat. Bila sinyal terputus, pemeriksaan tetap bisa dilanjutkan dan terkirim saat jaringan kembali.',
            color: AppColors.tealBgSoft,
            borderColor: AppColors.brandAccent,
            textColor: AppColors.textMutedStrong,
          ),
        ),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _nisn,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'NISN'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    OutlinedButton(
                      onPressed: _cariNisn,
                      child: const Text('Cari'),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                if (_lookupState == 'ditemukan')
                  const _StatusChip(
                    text: 'Siswa terdaftar — data terisi otomatis',
                    color: AppColors.brandGreen,
                    bg: AppColors.successBgSoft,
                  )
                else if (_lookupState == 'baru')
                  const _StatusChip(
                    text: 'NISN baru — isi manual, akan didaftarkan',
                    color: AppColors.warningText,
                    bg: AppColors.warningBgSoft,
                  ),
                const SizedBox(height: 12),
                TextField(
                    controller: _nama,
                    decoration: const InputDecoration(labelText: 'Nama lengkap')),
                const SizedBox(height: 12),
                TextField(
                    controller: _kelas,
                    decoration: const InputDecoration(
                        labelText: 'Kelas', hintText: 'Contoh: XI IPA 3')),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  key: const ValueKey('jenis-kelamin'),
                  initialValue: _jenisKelamin.isEmpty ? null : _jenisKelamin,
                  decoration: const InputDecoration(labelText: 'Jenis kelamin'),
                  items: const [
                    DropdownMenuItem(value: 'L', child: Text('Laki-laki')),
                    DropdownMenuItem(value: 'P', child: Text('Perempuan')),
                  ],
                  onChanged: (v) => setState(() => _jenisKelamin = v ?? ''),
                ),
                const SizedBox(height: 12),
                InkWell(
                  onTap: () => _pilihTanggalLahir(),
                  child: InputDecorator(
                    decoration: const InputDecoration(labelText: 'Tanggal lahir'),
                    child: Text(
                      _tanggalLahir == null
                          ? 'Pilih tanggal…'
                          : fmt.tanggalLengkap(
                              '${_tanggalLahir!.year}-${fmt.pad2(_tanggalLahir!.month)}-${fmt.pad2(_tanggalLahir!.day)}'),
                      style: TextStyle(
                        color: _tanggalLahir == null
                            ? AppColors.textMuted
                            : AppColors.textPrimary,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                    controller: _pendamping,
                    decoration: const InputDecoration(
                        labelText: 'Pendamping saat pemeriksaan *',
                        hintText: 'Contoh: Guru BK — Dra. Siti Rohmah')),
                const SizedBox(height: 6),
                const Text(
                  'Wajib diisi — pemeriksaan tidak boleh berlangsung tanpa pendamping dari pihak sekolah.',
                  style: AppType.caption,
                ),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(onPressed: _kembali, child: const Text('Kembali')),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton(
                    onPressed: _lanjut, child: Text('Lanjut ke ${_stepTitles[_step]}')),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _pilihTanggalLahir() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _tanggalLahir ?? DateTime(now.year - 16),
      firstDate: DateTime(now.year - 30),
      lastDate: now,
    );
    if (picked != null) setState(() => _tanggalLahir = picked);
  }

  Widget _buildStepHasil() {
    return Column(
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 12),
          child: _Banner(
            message:
                'Baca strip 5 menit setelah sampel diteteskan. Pilih Invalid bila garis kontrol tidak muncul — pemeriksaan wajib diulang dengan strip baru.',
            color: AppColors.tealBgSoft,
            borderColor: AppColors.brandAccent,
            textColor: AppColors.textMutedStrong,
          ),
        ),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                    controller: _stripLot,
                    decoration: const InputDecoration(
                        labelText: 'Kode lot strip', hintText: 'Contoh: RT-2609-A')),
                const SizedBox(height: 12),
                InkWell(
                  onTap: () => _pilihStripExpiry(),
                  child: InputDecorator(
                    decoration:
                        const InputDecoration(labelText: 'Tanggal kedaluwarsa strip'),
                    child: Text(
                      _stripExpiry == null
                          ? 'Pilih tanggal…'
                          : fmt.tanggalLengkap(
                              '${_stripExpiry!.year}-${fmt.pad2(_stripExpiry!.month)}-${fmt.pad2(_stripExpiry!.day)}'),
                      style: TextStyle(
                        color: _stripExpiry == null
                            ? AppColors.textMuted
                            : AppColors.textPrimary,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.neutralBg,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text('$_jumlahTerisi dari 7 parameter terisi — semua wajib diisi.',
                      style: AppType.metaStrong),
                ),
                const SizedBox(height: 8),
                ..._parameters.map((p) => _buildParameterRow(p.$1, p.$2, p.$3)),
                const SizedBox(height: 12),
              ],
            ),
          ),
        ),
        // Section tindak lanjut SELALU ada di tree, disembunyikan via
        // Offstage saat tidak reaktif. Ini mencegah dropdown (rencana)
        // dibuat-hancur berulang yang memicu error "GlobalKey used multiple
        // times" pada Flutter versi terbaru (FormField menyimpan key global).
        Offstage(
          offstage: !_adaReaktif,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Card(
              color: AppColors.cardBg,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
                side: const BorderSide(color: AppColors.danger, width: 1.2),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Header merah — hanya teks, latar card putih.
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                      decoration: BoxDecoration(
                        color: AppColors.dangerBgSoft,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        '${_hasil.values.where((h) => h == 'positif').length} parameter reaktif — tindak lanjut wajib',
                        style: const TextStyle(
                            color: AppColors.danger, fontWeight: FontWeight.w700, fontSize: 14),
                      ),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      key: const ValueKey('rencana-tindak-lanjut'),
                      initialValue: _rencana.isEmpty ? null : _rencana,
                      decoration: const InputDecoration(labelText: 'Rencana tindak lanjut *'),
                      items: _rencanaOptions
                          .map((r) => DropdownMenuItem(value: r.$1, child: Text(r.$2)))
                          .toList(),
                      onChanged: (v) => setState(() => _rencana = v ?? ''),
                    ),
                    const SizedBox(height: 12),
                    const Text('Sampel disegel *',
                        style: AppType.meta),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        _YaTidakButton(
                          label: 'Ya',
                          selected: _sampelDisegel == true,
                          onTap: () => setState(() =>
                              _sampelDisegel = _sampelDisegel == true ? null : true),
                        ),
                        const SizedBox(width: 8),
                        _YaTidakButton(
                          label: 'Tidak',
                          selected: _sampelDisegel == false,
                          onTap: () => setState(() =>
                              _sampelDisegel = _sampelDisegel == false ? null : false),
                        ),
                      ],
                    ),
                    if (_sampelDisegel == true) ...[
                      const SizedBox(height: 12),
                      TextField(
                          controller: _kodeSegel,
                          decoration: const InputDecoration(
                              labelText: 'Kode segel *', hintText: 'Contoh: SG-0412')),
                    ],
                    const SizedBox(height: 12),
                    TextField(
                      controller: _catatan,
                      maxLines: 3,
                      decoration:
                          const InputDecoration(labelText: 'Catatan petugas (opsional)'),
                    ),
                    const SizedBox(height: 12),
                    const _Banner(
                      message:
                          'Hasil ini bersifat rahasia. Identitas siswa hanya terbaca oleh Anda dan pengawas wilayah. Dinas menerima data ini dalam bentuk agregat tanpa nama.',
                      color: AppColors.tealBgSoft,
                      borderColor: AppColors.brandAccent,
                      textColor: AppColors.textMutedStrong,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(onPressed: _kembali, child: const Text('Kembali')),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton(
                    onPressed: _lanjut, child: Text('Lanjut ke ${_stepTitles[_step]}')),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _pilihStripExpiry() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _stripExpiry ?? DateTime.now().add(const Duration(days: 90)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (picked != null) setState(() => _stripExpiry = picked);
  }

  /// Baris parameter: baris 1 label + kode, baris 2 keterangan,
  /// baris 3 tombol Negatif/Positif/Invalid selebar card (kiri-kanan).
  Widget _buildParameterRow(String key, String label, String ket) {
    final nilai = _hasil[key] ?? '';
    return Container(          margin: const EdgeInsets.only(bottom: AppSpacing.md),
          padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: AppColors.neutralBg,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label == key ? key : '$label ($key)',
            style: AppType.bodyStrong,
          ),
          const SizedBox(height: 2),
          Text(ket, style: AppType.caption),
          const SizedBox(height: 8),
          // 3 tombol dari kiri sampai kanan (masing-masing selebar sepertiga)
          Row(
            children: ['negatif', 'positif', 'invalid'].map((h) {
              return Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 3),
                  child: _HasilButton(
                    label: _hasilLabel[h]!,
                    selected: nilai == h,
                    selectedColor: h == 'positif'
                        ? AppColors.danger
                        : h == 'negatif'
                            ? AppColors.brandGreen
                            : AppColors.textMuted,
                    onTap: () => setState(() {
                      _hasil[key] = nilai == h ? '' : h;
                      _notifyHeader();
                    }),
                  ),
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  Widget _buildStepKonfirmasi() {
    final schedule = _schedule;
    return Column(
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Sekolah',
                    style: AppType.cardTitle),
                const SizedBox(height: 8),
                _InfoRow(label: 'Sekolah', value: schedule?.school.nama ?? '—'),
                _InfoRow(label: 'NPSN', value: schedule?.school.npsn ?? '—'),
                _InfoRow(
                    label: 'Sesi',
                    value:
                        '${schedule?.sesi == 'pagi' ? 'Sesi pagi' : 'Sesi siang'}, ${schedule == null ? '—' : fmt.tanggalLengkap(schedule.tanggal)}'),
                const Divider(height: 20),
                const Text('Siswa',
                    style: AppType.cardTitle),
                const SizedBox(height: 8),
                _InfoRow(label: 'NISN', value: _nisn.text),
                _InfoRow(label: 'Nama', value: _nama.text),
                _InfoRow(label: 'Kelas', value: _kelas.text),
                _InfoRow(
                    label: 'Jenis kelamin',
                    value: _jenisKelamin == 'L' ? 'Laki-laki' : _jenisKelamin == 'P' ? 'Perempuan' : '—'),
                _InfoRow(
                    label: 'Tanggal lahir',
                    value: _tanggalLahir == null
                        ? '—'
                        : fmt.tanggalLengkap(
                            '${_tanggalLahir!.year}-${fmt.pad2(_tanggalLahir!.month)}-${fmt.pad2(_tanggalLahir!.day)}')),
                _InfoRow(label: 'Pendamping', value: _pendamping.text),
                const Divider(height: 20),
                const Text('Hasil uji',
                    style: AppType.cardTitle),
                const SizedBox(height: 8),
                _InfoRow(label: 'Lot strip', value: _stripLot.text),
                _InfoRow(
                    label: 'Kedaluwarsa',
                    value: _stripExpiry == null
                        ? '—'
                        : fmt.tanggalLengkap(
                            '${_stripExpiry!.year}-${fmt.pad2(_stripExpiry!.month)}-${fmt.pad2(_stripExpiry!.day)}')),
                ..._parameters.map((p) => _InfoRow(
                      label: p.$1 == p.$2 ? p.$1 : '${p.$2} (${p.$1})',
                      value: _hasil[p.$1] == null || _hasil[p.$1]!.isEmpty
                          ? '—'
                          : _hasilLabel[_hasil[p.$1]]!,
                      danger: _hasil[p.$1] == 'positif',
                    )),
                if (_adaReaktif) ...[
                  const Divider(height: 20),
                  const Text('Tindak lanjut',
                      style: AppType.cardTitle),
                  const SizedBox(height: 8),
                  _InfoRow(
                      label: 'Rencana',
                      value: _rencanaOptions
                          .where((r) => r.$1 == _rencana)
                          .map((r) => r.$2)
                          .firstOrNull ??
                          '—'),
                  _InfoRow(
                      label: 'Sampel disegel',
                      value: _sampelDisegel == null ? '—' : (_sampelDisegel! ? 'Ya' : 'Tidak')),
                  if (_kodeSegel.text.isNotEmpty)
                    _InfoRow(label: 'Kode segel', value: _kodeSegel.text),
                ],
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(
                    onPressed: _submitting ? null : _kembali,
                    child: const Text('Kembali')),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton(
                  onPressed: _submitting ? null : () => _simpan(),
                  child: Text(_submitting ? 'Mengirim…' : 'Simpan & kirim'),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Label kecil di atas nilai info sekolah (gaya info-row web).
class _SchoolInfoRow extends StatelessWidget {
  final String label;
  const _SchoolInfoRow({required this.label});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Text(label, style: AppType.captionStrong),
    );
  }
}

class _Banner extends StatelessWidget {
  final String message;
  final Color color;
  final Color borderColor;
  final Color textColor;
  const _Banner({
    required this.message,
    required this.color,
    required this.borderColor,
    required this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(8),
        border: Border(left: BorderSide(color: borderColor, width: 4)),
      ),
      child: Text(message,
          style: TextStyle(color: textColor, fontSize: 13, height: 1.4)),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String text;
  final Color color;
  final Color bg;
  const _StatusChip({required this.text, required this.color, required this.bg});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(text,
          style: TextStyle(
              color: color, fontSize: 12, fontWeight: FontWeight.w700)),
    );
  }
}

class _HasilButton extends StatelessWidget {
  final String label;
  final bool selected;
  final Color selectedColor;
  final VoidCallback onTap;
  const _HasilButton({
    required this.label,
    required this.selected,
    required this.selectedColor,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        // Lebar penuh agar 3 tombol sepertiga membentang kiri-kanan card.
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 8),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? selectedColor : AppColors.cardBg,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(
            color: selected ? selectedColor : AppColors.border,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? Colors.white : AppColors.textMutedStrong,
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class _YaTidakButton extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _YaTidakButton({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? AppColors.textMuted : AppColors.cardBg,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(
              color: selected ? AppColors.textMuted : AppColors.border),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? Colors.white : AppColors.textMutedStrong,
            fontWeight: FontWeight.w700,
            fontSize: 13,
          ),
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final bool danger;
  const _InfoRow({required this.label, required this.value, this.danger = false});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 118,
            child: Text(label, style: AppType.meta),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: danger ? AppColors.danger : AppColors.textPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}