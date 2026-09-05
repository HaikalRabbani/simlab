import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models.dart';

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  ApiException(this.message, {this.statusCode});

  @override
  String toString() => message;
}

class Api {
  static const baseUrl = String.fromEnvironment(
    'API_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );

  static String? _token;
  static User? _user;

  static Future<void> saveAuth({required String token, required User user}) async {
    _token = token;
    _user = user;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('simlab_token', token);
    await prefs.setString('simlab_user', jsonEncode({
          'id': user.id,
          'nama': user.nama,
          'email': user.email,
          'role': user.role,
          'wilayah_scope': user.wilayahScope,
          'avatar_initial': user.avatarInitial,
        }));
  }

  static Future<bool> restoreAuth() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('simlab_token');
    final rawUser = prefs.getString('simlab_user');
    if (token == null || rawUser == null) return false;
    _token = token;
    _user = User.fromJson(jsonDecode(rawUser) as Map<String, dynamic>);
    return true;
  }

  static Future<void> clearAuth() async {
    _token = null;
    _user = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('simlab_token');
    await prefs.remove('simlab_user');
  }

  static User? get user => _user;

  static Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
  }) async {
    final uri = Uri.parse('$baseUrl$path').replace(
      queryParameters: query?.map((k, v) => MapEntry(k, v)),
    );
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (_token != null) 'Authorization': 'Bearer $_token',
    };

    late http.Response res;
    try {
      switch (method) {
        case 'POST':
          res = await http.post(uri, headers: headers, body: jsonEncode(body ?? {})).timeout(const Duration(seconds: 15));
        case 'GET':
          res = await http.get(uri, headers: headers).timeout(const Duration(seconds: 15));
        default:
          throw ApiException('Metode tidak didukung.');
      }
    } on SocketException {
      throw ApiException('Tidak ada koneksi ke server.');
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('Gagal terhubung ke server.');
    }

    if (res.statusCode == 401) {
      await clearAuth();
      throw ApiException('Sesi berakhir. Silakan login ulang.', statusCode: 401);
    }

    final decoded = res.body.isEmpty ? <String, dynamic>{} : jsonDecode(res.body) as Map<String, dynamic>;
    if (res.statusCode >= 400) {
      final errors = decoded['errors'];
      String msg = (decoded['message'] ?? 'Terjadi kesalahan.') as String;
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        msg = (first is List && first.isNotEmpty) ? first.first.toString() : msg;
      }
      throw ApiException(msg, statusCode: res.statusCode);
    }
    return decoded;
  }

  static Future<void> login(String email, String password) async {
    final data = await _request('POST', '/login', body: {
      'email': email,
      'password': password,
    });
    final token = data['token'] as String;
    final user = User.fromJson(data['user'] as Map<String, dynamic>);
    await saveAuth(token: token, user: user);
  }

  static Future<void> logout() async {
    try {
      await _request('POST', '/logout');
    } catch (_) {
      // tetap logout lokal
    }
    await clearAuth();
  }

  static Future<List<Schedule>> getSchedules({String? tanggal}) async {
    final data = await _request('GET', '/schedules', query: {
      'tanggal': ?tanggal,
      'per_page': '200',
    });
    return (data['data'] as List<dynamic>? ?? [])
        .map((e) => Schedule.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  static Future<List<Student>> lookupStudents(int schoolId, {String? nisn, String? search}) async {
    final data = await _request('GET', '/schools/$schoolId/students', query: {
      'nisn': ?nisn,
      'search': ?search,
    });
    return (data as List<dynamic>? ?? [])
        .map((e) => Student.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  static Future<Student> createStudent({
    required int schoolId,
    required String nisn,
    required String nama,
    required String kelas,
    required String jenisKelamin,
    required String tanggalLahir,
  }) async {
    final data = await _request('POST', '/students', body: {
      'school_id': schoolId,
      'nisn': nisn,
      'nama': nama,
      'kelas': kelas,
      'jenis_kelamin': jenisKelamin,
      'tanggal_lahir': tanggalLahir,
    });
    return Student.fromJson(data);
  }

  /// Submit pemeriksaan. Server idempoten via client_uuid (spec 9.6).
  static Future<Map<String, dynamic>> submitExamination(ExaminationEntry entry) async {
    return _request('POST', '/examinations', body: {
      ...entry.toJson(),
      // student dikirim terpisah; student_id diambil dari entry.student.id
      'student_id': entry.student.id,
    }..remove('student'));
  }

  /// Riwayat pemeriksaan milik petugas (spec 6.2) — daftar ringkas per entri.
  static Future<List<Map<String, dynamic>>> fetchExaminations(String tanggal) async {
    final data = await _request('GET', '/examinations', query: {
      'tanggal': tanggal,
      'per_page': '500',
    });
    return (data['data'] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();
  }

  /// Jumlah pemeriksaan yang sudah tersimpan pada tanggal + sesi tertentu
  /// (dipakai header wizard untuk "Peserta ke-N dari target sesi").
  static Future<int> countExaminations(String tanggal, {int? scheduleId}) async {
    final data = await _request('GET', '/examinations', query: {
      'tanggal': tanggal,
      'per_page': '1',
      if (scheduleId != null) 'schedule_id': '$scheduleId',
    });
    final total = data['total'];
    if (total is int) return total;
    final rows = data['data'] as List<dynamic>? ?? [];
    return rows.length;
  }
}