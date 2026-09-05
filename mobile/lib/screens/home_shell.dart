import 'package:flutter/material.dart';

import '../services/api.dart';
import '../theme.dart';
import 'input_wizard_screen.dart';
import 'jadwal_screen.dart';
import 'riwayat_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  // Info header wizard: langkah aktif + konteks (sekolah/siswa) + progres.
  WizardHeaderInfo _wizardHeader = const WizardHeaderInfo(
    step: 1,
    stepTitle: 'Pilih sekolah',
  );
  late final List<Widget> _pages;
  final _wizardKey = GlobalKey<InputWizardScreenState>();

  @override
  void initState() {
    super.initState();
    _pages = [
      InputWizardScreen(
        key: _wizardKey,
        onHeaderChanged: (info) => _setWizardHeader(info),
        onBackRequested: () =>
            _wizardKey.currentState?.goBack(),
      ),
      const RiwayatScreen(),
      const JadwalScreen(),
    ];
  }

  void _setWizardHeader(WizardHeaderInfo info) {
    if (_wizardHeader.step != info.step ||
        _wizardHeader.stepTitle != info.stepTitle ||
        _wizardHeader.contextLine != info.contextLine ||
        _wizardHeader.schoolName != info.schoolName ||
        _wizardHeader.sesi != info.sesi ||
        _wizardHeader.tanggal != info.tanggal ||
        _wizardHeader.targetSiswa != info.targetSiswa ||
        _wizardHeader.terkirimHariIni != info.terkirimHariIni ||
        _wizardHeader.paramTerisi != info.paramTerisi) {
      setState(() => _wizardHeader = info);
    }
  }

  // Baris 4: "Langkah x dari 4 — xxxx" + info dinamis.
  String get _statusLine {
    final h = _wizardHeader;
    final sisa = 'Langkah ${h.step} dari 4 — ${h.stepTitle}';
    if (h.step == 1) {
      if (h.targetSiswa > 0) {
        return '$sisa · Peserta ke-${h.terkirimHariIni + 1} dari target sesi: ${h.targetSiswa} siswa';
      }
    }
    if (h.step == 3) {
      return '$sisa · ${h.paramTerisi} dari 7 parameter terisi';
    }
    return sisa;
  }

  @override
  Widget build(BuildContext context) {
    final user = Api.user;
    final initial = user?.avatarInitial ??
        (user?.nama != null && user!.nama.isNotEmpty
            ? user.nama
                .split(' ')
                .take(2)
                .map((w) => w.isNotEmpty ? w[0].toUpperCase() : '')
                .join()
            : '??');

    // Judul utama mengikuti langkah aktif wizard (dinamis):
    // langkah 1 -> "Pemeriksaan Baru", langkah 2+ -> nama langkah.
    final input = _index == 0;
    final isStepOne = _wizardHeader.step <= 1;
    final title = input
        ? (isStepOne ? 'Pemeriksaan Baru' : _wizardHeader.stepTitle)
        : (['Riwayat', 'Jadwal'][_index - 1]);
    final showBack = input && !isStepOne;

    return Scaffold(
      appBar: AppBar(
        toolbarHeight: input ? 62 : kToolbarHeight,
        // Tombol kembali "‹" — hanya pada langkah 2+ wizard input.
        leading: showBack
            ? IconButton(
                icon: const Icon(Icons.arrow_back_ios_new, size: 20),
                tooltip: 'Kembali',
                onPressed: () => _wizardKey.currentState?.goBack(),
              )
            : null,
        automaticallyImplyLeading: false,
        title: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 17),
            ),
            const SizedBox(height: 2),
            if (input)
              Text(
                _wizardHeader.contextLine,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontWeight: FontWeight.w500,
                  fontSize: 11.5,
                  color: Colors.white.withValues(alpha: 0.85),
                ),
              ),
          ],
        ),
        actions: [
          // Avatar profil — mengikuti tinggi dua baris judul.
          Center(
            child: Container(
              margin: const EdgeInsets.only(right: 16),
              height: 40,
              width: 40,
              decoration: const BoxDecoration(
                color: AppColors.brandAccent,
                shape: BoxShape.circle,
              ),
              alignment: Alignment.center,
              child: Text(
                initial,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                ),
              ),
            ),
          ),
        ],
        bottom: input
            ? PreferredSize(
                // progress 5px + jarak 6px + teks ~15px + napas bawah 8px = 34px
                preferredSize: const Size.fromHeight(36),
                child: Container(
                  height: 36,
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // progress bar 4 segmen (spec 15.5)
                      Row(
                        children: List.generate(4, (i) {
                          final aktif = i + 1 <= _wizardHeader.step;
                          return Expanded(
                            child: Container(
                              height: 5,
                              margin: const EdgeInsets.symmetric(horizontal: 1.5),
                              decoration: BoxDecoration(
                                color:
                                    aktif ? AppColors.brandAccent : Colors.white24,
                                borderRadius: BorderRadius.circular(3),
                              ),
                            ),
                          );
                        }),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _statusLine,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w400,
                          height: 1.2,
                          color: Colors.white.withValues(alpha: 0.9),
                        ),
                      ),
                    ],
                  ),
                ),
              )
            : null,
      ),
      body: IndexedStack(index: _index, children: _pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.edit_outlined),
            selectedIcon: Icon(Icons.edit),
            label: 'Input',
          ),
          NavigationDestination(
            icon: Icon(Icons.list_alt_outlined),
            selectedIcon: Icon(Icons.list_alt),
            label: 'Riwayat',
          ),
          NavigationDestination(
            icon: Icon(Icons.schedule_outlined),
            selectedIcon: Icon(Icons.schedule),
            label: 'Jadwal',
          ),
        ],
      ),
    );
  }
}
