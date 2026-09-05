import 'package:flutter/material.dart';

import '../models.dart';
import '../services/api.dart';
import '../theme.dart';
import '../utils/format.dart' as fmt;

class JadwalScreen extends StatefulWidget {
  const JadwalScreen({super.key});

  @override
  State<JadwalScreen> createState() => _JadwalScreenState();
}

class _JadwalScreenState extends State<JadwalScreen> {
  List<Schedule> _schedules = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _muat();
  }

  Future<void> _muat() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final schedules = await Api.getSchedules();
      if (!mounted) return;
      setState(() {
        _schedules = schedules;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Gagal memuat jadwal.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _muat,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(12),
        children: [
          const Text(
            'Sekolah yang harus Anda kunjungi',
            style: AppType.meta,
          ),
          const SizedBox(height: 10),
          if (_error != null)
            Text(_error!,
                style: const TextStyle(color: AppColors.danger, fontSize: 13)),
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
          if (!_loading && _schedules.isEmpty)
            const Padding(
              padding: EdgeInsets.all(32),
              child: Center(
                child: Text('Belum ada jadwal kunjungan untuk Anda.',
                    style: TextStyle(color: AppColors.textMuted)),
              ),
            ),
          // kelompokkan per tanggal
          ..._groupByTanggal().entries.map((entry) => Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(fmt.hariTanggal(entry.key),
                          style: AppType.bodyStrong),
                      const SizedBox(height: 6),
                      ...entry.value.map((s) => _ScheduleTile(schedule: s)),
                    ],
                  ),
                ),
              )),
        ],
      ),
    );
  }

  Map<String, List<Schedule>> _groupByTanggal() {
    final map = <String, List<Schedule>>{};
    for (final s in _schedules) {
      map.putIfAbsent(s.tanggal, () => []).add(s);
    }
    final keys = map.keys.toList()..sort((a, b) => b.compareTo(a));
    return {for (final k in keys) k: map[k]!};
  }
}

class _ScheduleTile extends StatelessWidget {
  final Schedule schedule;
  const _ScheduleTile({required this.schedule});

  @override
  Widget build(BuildContext context) {
    final sesi = schedule.sesi == 'pagi' ? 'Sesi pagi' : 'Sesi siang';
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
                Text(schedule.school.nama,
                    style: AppType.label),
                Text(
                    'NPSN ${schedule.school.npsn} · ${schedule.school.kabKota}',
                    style: AppType.caption),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(sesi, style: AppType.metaStrong),
              Text('${fmt.angka(schedule.targetSiswa)} siswa',
                  style: AppType.caption),
            ],
          ),
        ],
      ),
    );
  }
}