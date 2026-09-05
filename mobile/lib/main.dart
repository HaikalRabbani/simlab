import 'package:flutter/material.dart';

import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'services/api.dart';
import 'services/sync_service.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final loggedIn = await Api.restoreAuth();
  if (loggedIn) {
    SyncService.instance.startListening();
  }
  runApp(SimlabApp(initialLoggedIn: loggedIn));
}

class SimlabApp extends StatelessWidget {
  final bool initialLoggedIn;
  const SimlabApp({super.key, required this.initialLoggedIn});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SIMLAB Jabar',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      initialRoute: initialLoggedIn ? '/home' : '/login',
      routes: {
        '/login': (_) => const LoginScreen(),
        '/home': (_) => const HomeShell(),
      },
    );
  }
}