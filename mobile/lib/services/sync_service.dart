import 'package:connectivity_plus/connectivity_plus.dart';

import '../models.dart';
import 'api.dart';
import 'local_db.dart';

/// Sinkronisasi offline-to-online (spec 9.3): begitu device online, kirim ulang
/// semua antrian pending_sync secara berurutan (FIFO berdasarkan waktu input).
class SyncService {
  SyncService._();
  static final SyncService instance = SyncService._();

  bool _listening = false;
  int _pendingCount = 0;

  /// Jumlah entri yang masih menunggu sinkronisasi.
  int get pendingCount => _pendingCount;

  void startListening() {
    if (_listening) return;
    _listening = true;
    Connectivity().onConnectivityChanged.listen((results) {
      if (results.any((r) => r != ConnectivityResult.none)) {
        syncPending();
      }
    });
    // cek awal
    syncPending();
  }

  /// Kirim ulang semua entri pending secara FIFO. Idempoten via client_uuid.
  Future<void> syncPending() async {
    final entries = await LocalDb.instance.pendingExams();
    _pendingCount = entries.length;

    for (final entry in entries) {
      try {
        // Jika siswa belum punya id (dibuat offline), daftarkan dulu.
        int? studentId = entry.student.id;
        if (studentId == null) {
          final created = await Api.createStudent(
            schoolId: entry.schoolId ?? 0,
            nisn: entry.student.nisn,
            nama: entry.student.nama,
            kelas: entry.student.kelas,
            jenisKelamin: entry.student.jenisKelamin,
            tanggalLahir: entry.student.tanggalLahir,
          );
          studentId = created.id;
        }
        final updated = ExaminationEntry(
          clientUuid: entry.clientUuid,
          scheduleId: entry.scheduleId,
          schoolId: entry.schoolId,
          student: Student(
            id: studentId,
            nisn: entry.student.nisn,
            nama: entry.student.nama,
            kelas: entry.student.kelas,
            jenisKelamin: entry.student.jenisKelamin,
            tanggalLahir: entry.student.tanggalLahir,
          ),
          pendamping: entry.pendamping,
          catatanPetugas: entry.catatanPetugas,
          stripLotCode: entry.stripLotCode,
          stripExpiryDate: entry.stripExpiryDate,
          rencanaTindakLanjut: entry.rencanaTindakLanjut,
          sampelDisegel: entry.sampelDisegel,
          kodeSegel: entry.kodeSegel,
          hasil: entry.hasil,
          waktuInput: entry.waktuInput,
        );
        await Api.submitExamination(updated);
        await LocalDb.instance.removePending(entry.clientUuid);
      } catch (_) {
        // gagal satu entri -> lanjut; akan dicoba lagi di event berikutnya
        break;
      }
    }

    _pendingCount = (await LocalDb.instance.pendingExams()).length;
  }
}