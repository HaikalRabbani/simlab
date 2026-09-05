class User {
  final int id;
  final String nama;
  final String email;
  final String role;
  final String? wilayahScope;
  final String? avatarInitial;

  User({
    required this.id,
    required this.nama,
    required this.email,
    required this.role,
    this.wilayahScope,
    this.avatarInitial,
  });

  factory User.fromJson(Map<String, dynamic> json) => User(
        id: json['id'] as int,
        nama: json['nama'] as String,
        email: json['email'] as String,
        role: json['role'] as String,
        wilayahScope: json['wilayah_scope'] as String?,
        avatarInitial: json['avatar_initial'] as String?,
      );
}

class School {
  final int id;
  final String npsn;
  final String nama;
  final String kabKota;
  final String kecamatan;

  School({
    required this.id,
    required this.npsn,
    required this.nama,
    required this.kabKota,
    required this.kecamatan,
  });

  factory School.fromJson(Map<String, dynamic> json) => School(
        id: json['id'] as int,
        npsn: (json['npsn'] ?? '') as String,
        nama: (json['nama'] ?? '') as String,
        kabKota: (json['kab_kota'] ?? '') as String,
        kecamatan: (json['kecamatan'] ?? '') as String,
      );
}

class Schedule {
  final int id;
  final School school;
  final String tanggal; // YYYY-MM-DD
  final String sesi; // pagi | siang
  final int targetSiswa;
  final String status;

  Schedule({
    required this.id,
    required this.school,
    required this.tanggal,
    required this.sesi,
    required this.targetSiswa,
    required this.status,
  });

  factory Schedule.fromJson(Map<String, dynamic> json) => Schedule(
        id: json['id'] as int,
        school: School.fromJson(json['school'] as Map<String, dynamic>),
        tanggal: (json['tanggal'] ?? '') as String,
        sesi: (json['sesi'] ?? 'pagi') as String,
        targetSiswa: (json['target_siswa'] ?? 0) as int,
        status: (json['status'] ?? 'terjadwal') as String,
      );
}

class Student {
  final int? id;
  final String nisn;
  final String nama;
  final String kelas;
  final String jenisKelamin; // L | P
  final String tanggalLahir; // YYYY-MM-DD

  Student({
    this.id,
    required this.nisn,
    required this.nama,
    required this.kelas,
    required this.jenisKelamin,
    required this.tanggalLahir,
  });

  factory Student.fromJson(Map<String, dynamic> json) => Student(
        id: json['id'] as int?,
        nisn: (json['nisn'] ?? '') as String,
        nama: (json['nama'] ?? '') as String,
        kelas: (json['kelas'] ?? '') as String,
        jenisKelamin: (json['jenis_kelamin'] ?? '') as String,
        tanggalLahir: (json['tanggal_lahir'] ?? '') as String,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'nisn': nisn,
        'nama': nama,
        'kelas': kelas,
        'jenis_kelamin': jenisKelamin,
        'tanggal_lahir': tanggalLahir,
      };
}

/// Hasil satu parameter uji.
class ParameterResult {
  final String parameter; // THC/AMP/MET/MOP/BZO/TRA/ALKOHOL
  final String hasil; // negatif | positif | invalid

  ParameterResult({required this.parameter, required this.hasil});

  Map<String, dynamic> toJson() => {'parameter': parameter, 'hasil': hasil};
}

/// Entri pemeriksaan (draft lokal / hasil submit / antrian pending).
class ExaminationEntry {
  final String clientUuid;
  final int? scheduleId;
  final int? schoolId;
  final Student student;
  final String pendamping;
  final String? catatanPetugas;
  final String stripLotCode;
  final String stripExpiryDate;
  final String? rencanaTindakLanjut;
  final bool? sampelDisegel;
  final String? kodeSegel;
  final List<ParameterResult> hasil;
  final String waktuInput; // ISO8601 dari device (spec 9.5)

  ExaminationEntry({
    required this.clientUuid,
    this.scheduleId,
    this.schoolId,
    required this.student,
    required this.pendamping,
    this.catatanPetugas,
    required this.stripLotCode,
    required this.stripExpiryDate,
    this.rencanaTindakLanjut,
    this.sampelDisegel,
    this.kodeSegel,
    required this.hasil,
    required this.waktuInput,
  });

  Map<String, dynamic> toJson() => {
        'client_uuid': clientUuid,
        'schedule_id': scheduleId,
        'school_id': schoolId,
        'student': student.toJson(),
        'pendamping': pendamping,
        'catatan_petugas': catatanPetugas,
        'strip_lot_code': stripLotCode,
        'strip_expiry_date': stripExpiryDate,
        'rencana_tindak_lanjut': rencanaTindakLanjut,
        'sampel_disegel': sampelDisegel,
        'kode_segel': kodeSegel,
        'hasil': hasil.map((h) => h.toJson()).toList(),
        'waktu_input': waktuInput,
      };

  factory ExaminationEntry.fromJson(Map<String, dynamic> json) =>
      ExaminationEntry(
        clientUuid: json['client_uuid'] as String,
        scheduleId: json['schedule_id'] as int?,
        schoolId: json['school_id'] as int?,
        student: Student.fromJson(json['student'] as Map<String, dynamic>),
        pendamping: (json['pendamping'] ?? '') as String,
        catatanPetugas: json['catatan_petugas'] as String?,
        stripLotCode: (json['strip_lot_code'] ?? '') as String,
        stripExpiryDate: (json['strip_expiry_date'] ?? '') as String,
        rencanaTindakLanjut: json['rencana_tindak_lanjut'] as String?,
        sampelDisegel: json['sampel_disegel'] as bool?,
        kodeSegel: json['kode_segel'] as String?,
        hasil: (json['hasil'] as List<dynamic>? ?? [])
            .map((h) => ParameterResult(
                parameter: h['parameter'] as String, hasil: h['hasil'] as String))
            .toList(),
        waktuInput: (json['waktu_input'] ?? '') as String,
      );
}