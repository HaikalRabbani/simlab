import 'package:flutter_test/flutter_test.dart';

import 'package:simlab_mobile/main.dart';

void main() {
  testWidgets('Aplikasi dapat dibangun (smoke test)', (WidgetTester tester) async {
    await tester.pumpWidget(const SimlabApp(initialLoggedIn: false));
    expect(find.text('SIMLAB Jabar'), findsOneWidget);
    expect(find.text('Masuk'), findsWidgets); // judul halaman + tombol
    expect(find.text('Gunakan akun yang diberikan Dinas.'), findsOneWidget);
  });
}