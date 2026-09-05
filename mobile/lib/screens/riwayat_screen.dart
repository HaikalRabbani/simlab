import 'package:flutter/material.dart';

import '../models.dart';
import '../services/api.dart';
import '../services/local_db.dart';
import '../services/sync_service.dart';
import '../theme.dart';
import '../utils/format.dart' as fmt;

class RiwayatScreen extends StatefulWidget {
  const RiwayatScreen({super.key});

  @override
  State<RiwayatScreen> createState() => _RiwayatScreenState();
}

class _RiwayatScreenState extends State<RiwayatScreen> {
  List<Map<String, dynamic>> _terkirim = [];
  List<Map<String, dynamic>> _pending = [];
  bool _loading = true;
  bool _syncing = false;
  String? _error;
  String _tanggal = '';

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _tanggal =
        '${now.year}-${fmt.pad2(now.month)}-${fmt.pad2(now.day)}';
    _muat();
  }

  Future<void> _muat() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await Api.fetchExaminations(_tanggal);
      final pending = await LocalDb.instance.pendingExams();
      if (!mounted) return;
      setState(() {
        _terkirim = data;
        _pending = pending
            .map((e) => {
                  'nama': e.student.nama,
                  'nisn': e.student.nisn,
                  'kelas': e.student.kelas,
                  'jk': e.student.jenisKelamin,
                  'waktu_input': e.waktuInput,
                  'ringkasan': _ringkasan(e),
                })
            .toList();
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Gagal memuat riwayat.';
        _loading = false;
      });
    }
  }

  String _ringkasan(ExaminationEntry entry) {
    final reaktif = entry.hasil.where((h) => h.hasil == 'positif').toList();
    if (reaktif.isNotEmpty) {
      return '${reaktif.map((r) => r.parameter).join(', ')} reaktif';
    }
    if (entry.hasil.any((h) => h.hasil == 'invalid')) return 'Invalid — diulang';
    return 'Negatif semua';
  }

  Future<void> _kirimPending() async {
    setState(() => _syncing = true);
    await SyncService.instance.syncPending();
    await _muat();
    if (mounted) setState(() => _syncing = false);
  }

  @override
  Widget build(BuildContext context) {
    final total = _terkirim.length + _pending.length;

    return RefreshIndicator(
      onRefresh: _muat,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(12),
        children: [
          Text(
            'Hasil yang sudah Anda kirim hari ini · ${fmt.hariTanggal(_tanggal)}',
            style: AppType.meta,
          ),
          const SizedBox(height: 10),
          // Summary cards 3 kartu (spec 15.5)
          Row(
            children: [
              Expanded(
                  child: _StatCard(
                      label: 'Diperiksa',
                      value: fmt.angka(total),
                      hint: 'total hari ini')),
              const SizedBox(width: 8),
              Expanded(
                  child: _StatCard(
                      label: 'Terkirim',
                      value: fmt.angka(_terkirim.length),
                      hint: 'ke server',
                      valueColor: AppColors.brandGreen)),
              const SizedBox(width: 8),
              Expanded(
                  child: _StatCard(
                      label: 'Menunggu',
                      value: fmt.angka(_pending.length),
                      hint: 'tersimpan aman',
                      valueColor: AppColors.warningText)),
            ],
          ),
          const SizedBox(height: 8),
          if (_pending.isNotEmpty) ...[
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.warningBgSoft,
                borderRadius: BorderRadius.circular(8),
                border: const Border(
                    left: BorderSide(color: AppColors.warningText, width: 4)),
              ),
              child: Text(
                '${_pending.length} data belum terkirim. Tersimpan aman di perangkat dan akan dikirim otomatis saat jaringan stabil.',
                style: const TextStyle(fontSize: 12, color: AppColors.warningText),
              ),
            ),
            const SizedBox(height: 8),
          ],
          if (_error != null) ...[
            Text(_error!,
                style: const TextStyle(color: AppColors.danger, fontSize: 13)),
            const SizedBox(height: 8),
          ],
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
          if (!_loading && _pending.isEmpty && _terkirim.isEmpty)
            const Padding(
              padding: EdgeInsets.all(32),
              child: Center(
                child: Text('Belum ada data pemeriksaan hari ini.',
                    style: TextStyle(color: AppColors.textMuted)),
              ),
            ),
          if (_pending.isNotEmpty) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Menunggu sinkronisasi · ${_pending.length}',
                        style: const TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 14)),
                    const SizedBox(height: 6),
                    ..._pending.map((e) => _EntryTile(data: e, pending: true)),
                    const SizedBox(height: 8),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _syncing ? null : _kirimPending,
                        child: Text(_syncing
                            ? 'Mengirim…'
                            : 'Kirim ${_pending.length} data tertunda'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
          if (_terkirim.isNotEmpty) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Sudah terkirim · ${_terkirim.length} entri',
                        style: const TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 14)),
                    const SizedBox(height: 6),
                    ..._terkirim.map((e) => _EntryTile(data: e, pending: false)),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final String hint;
  final Color? valueColor;
  const _StatCard({
    required this.label,
    required this.value,
    required this.hint,
    this.valueColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: AppType.captionStrong),
          const SizedBox(height: 4),
          Text(value,
              style: TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w700,
                color: valueColor ?? AppColors.textPrimary,
              )),
          const SizedBox(height: 2),
          Text(hint, style: AppType.caption),
        ],
      ),
    );
  }
}

class _EntryTile extends StatelessWidget {
  final Map<String, dynamic> data;
  final bool pending;
  const _EntryTile({required this.data, required this.pending});

  @override
  Widget build(BuildContext context) {
    final ringkasan = (data['ringkasan'] ?? '') as String;
    final danger = ringkasan.contains('reaktif');
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.border)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(data['nama'] ?? '',
                    style: const TextStyle(
                        fontWeight: FontWeight.w600, fontSize: 13)),
                Text('${data['nisn']} · ${data['kelas']} · ${data['jk']}',
                    style: const TextStyle(
                        fontSize: 11, color: AppColors.textMuted)),
                Text(fmt.jamMenit(data['waktu_input'] ?? ''),
                    style: const TextStyle(
                        fontSize: 11, color: AppColors.textMuted)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              _Chip(
                text: ringkasan,
                color: danger ? AppColors.danger : AppColors.brandGreen,
                bg: danger ? AppColors.dangerBgSoft : AppColors.successBgSoft,
              ),
              const SizedBox(height: 4),
              _Chip(
                text: pending ? 'Menunggu' : 'Terkirim',
                color: pending ? AppColors.warningText : AppColors.brandGreen,
                bg: pending ? AppColors.warningBgSoft : AppColors.successBgSoft,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String text;
  final Color color;
  final Color bg;
  const _Chip({required this.text, required this.color, required this.bg});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(text,
          style: TextStyle(
              color: color, fontSize: 10, fontWeight: FontWeight.w700)),
    );
  }
}