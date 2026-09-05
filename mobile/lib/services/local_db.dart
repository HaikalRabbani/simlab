import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../models.dart';

/// Penyimpanan lokal (sqflite) untuk mode offline-first (spec 9):
/// - antrian `pending_sync` yang dikirim ulang otomatis saat online
/// - draft wizard yang tersimpan realtime
/// - cache siswa & jadwal agar tetap bisa dibuka tanpa internet
class LocalDb {
  LocalDb._();
  static final LocalDb instance = LocalDb._();

  Database? _db;

  Future<Database> get db async {
    if (_db != null) return _db!;
    final path = p.join(await getDatabasesPath(), 'simlab.db');
    _db = await openDatabase(
      path,
      version: 1,
      onCreate: (db, version) async {
        await db.execute('''
          CREATE TABLE pending_exams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_uuid TEXT NOT NULL UNIQUE,
            payload TEXT NOT NULL,
            waktu_input TEXT NOT NULL,
            created_at TEXT NOT NULL
          )
        ''');
        await db.execute('''
          CREATE TABLE drafts (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            payload TEXT NOT NULL,
            updated_at TEXT NOT NULL
          )
        ''');
        await db.execute('''
          CREATE TABLE student_cache (
            id INTEGER PRIMARY KEY,
            school_id INTEGER NOT NULL,
            nisn TEXT NOT NULL,
            nama TEXT NOT NULL,
            kelas TEXT NOT NULL,
            jenis_kelamin TEXT NOT NULL,
            tanggal_lahir TEXT NOT NULL
          )
        ''');
        await db.execute('''
          CREATE INDEX idx_student_cache_nisn ON student_cache (nisn)
        ''');
      },
    );
    return _db!;
  }

  // ---- Antrian pending_sync ----
  Future<void> queuePending(ExaminationEntry entry) async {
    final database = await db;
    await database.insert('pending_exams', {
      'client_uuid': entry.clientUuid,
      'payload': jsonEncode(entry.toJson()),
      'waktu_input': entry.waktuInput,
      'created_at': DateTime.now().toIso8601String(),
    }, conflictAlgorithm: ConflictAlgorithm.ignore);
  }

  Future<List<ExaminationEntry>> pendingExams() async {
    final database = await db;
    final rows = await database.query('pending_exams', orderBy: 'created_at ASC');
    return rows
        .map((r) => ExaminationEntry.fromJson(jsonDecode(r['payload'] as String) as Map<String, dynamic>))
        .toList();
  }

  Future<void> removePending(String clientUuid) async {
    final database = await db;
    await database.delete('pending_exams', where: 'client_uuid = ?', whereArgs: [clientUuid]);
  }

  // ---- Draft wizard (realtime, spec 9.1) ----
  Future<void> saveDraft(ExaminationEntry? entry) async {
    final database = await db;
    if (entry == null) {
      await database.delete('drafts', where: 'id = 1');
      return;
    }
    await database.insert('drafts', {
      'id': 1,
      'payload': jsonEncode(entry.toJson()),
      'updated_at': DateTime.now().toIso8601String(),
    }, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  Future<ExaminationEntry?> loadDraft() async {
    final database = await db;
    final rows = await database.query('drafts', where: 'id = 1', limit: 1);
    if (rows.isEmpty) return null;
    return ExaminationEntry.fromJson(jsonDecode(rows.first['payload'] as String) as Map<String, dynamic>);
  }

  // ---- Cache siswa (lookup NISN offline) ----
  Future<void> cacheStudents(List<Student> students) async {
    final database = await db;
    final batch = database.batch();
    for (final s in students) {
      if (s.id == null) continue;
      batch.insert('student_cache', {
        'id': s.id,
        'school_id': 0, // lookup NISN bersifat global per device; kolom dicadangkan
        'nisn': s.nisn,
        'nama': s.nama,
        'kelas': s.kelas,
        'jenis_kelamin': s.jenisKelamin,
        'tanggal_lahir': s.tanggalLahir,
      }, conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await batch.commit(noResult: true);
  }

  Future<List<Student>> findCachedStudent(String nisn) async {
    final database = await db;
    final rows = await database.query('student_cache',
        where: 'nisn = ?', whereArgs: [nisn], limit: 1);
    return rows.map((r) => Student(
          id: r['id'] as int,
          nisn: r['nisn'] as String,
          nama: r['nama'] as String,
          kelas: r['kelas'] as String,
          jenisKelamin: r['jenis_kelamin'] as String,
          tanggalLahir: r['tanggal_lahir'] as String,
        )).toList();
  }
}