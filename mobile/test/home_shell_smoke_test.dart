import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:simlab_mobile/screens/home_shell.dart';
import 'package:simlab_mobile/theme.dart';

void main() {
  testWidgets('HomeShell (tab Input) render tanpa exception', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: buildTheme(),
        home: HomeShell(),
      ),
    );
    await tester.pump(const Duration(milliseconds: 100));
    await tester.pump(const Duration(milliseconds: 100));

    expect(tester.takeException(), isNull,
        reason: 'Harusnya tidak ada exception saat render tab Input');
  });
}
