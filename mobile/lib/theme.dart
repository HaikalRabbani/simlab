import 'package:flutter/material.dart';

/// Design tokens SIMLAB Jabar (spec 15.1 & 15.5) — sama dengan web.
class AppColors {
  static const sidebarBg = Color(0xFF0E2A47);
  static const brandAccent = Color(0xFF0B6E6E);
  static const brandGreen = Color(0xFF1B7F4B);
  static const danger = Color(0xFFBE2B22);
  static const dangerStrong = Color(0xFFE8544A);
  static const dangerBgSoft = Color(0xFFFBE7E5);
  static const warningText = Color(0xFFA96A0C);
  static const warningBgSoft = Color(0xFFFCF0DC);
  static const successBgSoft = Color(0xFFE4F2E9);
  static const tealBgSoft = Color(0xFFE3F1F0);
  static const pageBg = Color(0xFFEDF1F4);
  static const pageBgAlt = Color(0xFFF6F9FB);
  static const cardBg = Colors.white;
  static const border = Color(0xFFD3DDE5);
  static const textPrimary = Color(0xFF0E2A47);
  static const textMuted = Color(0xFF41607C);
  static const textMutedStrong = Color(0xFF2A435D);
  static const neutralBg = Color(0xFFEDF1F4);
}

/// Skala spasi & radius — mengikuti ritme web (spec 15.1):
/// radius card 10, radius field/tombol 7-8, spasi 4/6/8/12/14/16/20.
class AppSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 20.0;
  static const cardRadius = 10.0;
  static const controlRadius = 8.0;
}

/// Skala tipografi (px) — konsisten dengan web: field 14, label 13,
/// teks kecil 12/11, judul 15-17.
class AppType {
  static const pageTitle = TextStyle(fontSize: 17, fontWeight: FontWeight.w700);
  static const cardTitle = TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary);
  static const body = TextStyle(fontSize: 14, color: AppColors.textPrimary);
  static const bodyStrong = TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textPrimary);
  static const fieldValue = TextStyle(fontSize: 14, color: AppColors.textPrimary);
  static const label = TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textPrimary);
  static const labelMuted = TextStyle(fontSize: 13, color: AppColors.textMuted);
  static const meta = TextStyle(fontSize: 12, color: AppColors.textMuted);
  static const metaStrong = TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.textMutedStrong);
  static const caption = TextStyle(fontSize: 11, color: AppColors.textMuted);
  static const captionStrong = TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.textMutedStrong);
}

ThemeData buildTheme() {
  final base = ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: AppColors.brandAccent,
      primary: AppColors.brandAccent,
      surface: AppColors.cardBg,
    ),
  );
  return base.copyWith(
    scaffoldBackgroundColor: AppColors.pageBg,
    appBarTheme: const AppBarTheme(
      backgroundColor: AppColors.sidebarBg,
      foregroundColor: Colors.white,
      elevation: 0,
      centerTitle: false,
    ),
    cardTheme: const CardThemeData(
      color: AppColors.cardBg,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      margin: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.all(Radius.circular(AppSpacing.cardRadius)),
        side: BorderSide(color: AppColors.border),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: AppColors.cardBg,
      // Proporsi form web: tinggi ~40px, font 14, radius 8.
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      isDense: true,
      labelStyle: const TextStyle(fontSize: 13, color: AppColors.textMuted),
      hintStyle: const TextStyle(fontSize: 13, color: AppColors.textMuted),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(AppSpacing.controlRadius),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(AppSpacing.controlRadius),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(AppSpacing.controlRadius),
        borderSide: const BorderSide(color: AppColors.brandAccent, width: 1.5),
      ),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.brandGreen,
        foregroundColor: Colors.white,
        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppSpacing.controlRadius)),
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
        minimumSize: const Size(44, 44),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.textPrimary,
        side: const BorderSide(color: AppColors.border),
        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppSpacing.controlRadius)),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        minimumSize: const Size(44, 44),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: AppColors.cardBg,
      indicatorColor: AppColors.tealBgSoft,
      labelTextStyle: WidgetStateProperty.all(
        const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
      ),
    ),
    textTheme: base.textTheme.copyWith(
      titleLarge: const TextStyle(
        fontWeight: FontWeight.w700,
        fontSize: 20,
        color: AppColors.textPrimary,
      ),
      titleMedium: const TextStyle(
        fontWeight: FontWeight.w700,
        fontSize: 15,
        color: AppColors.textPrimary,
      ),
    ),
  );
}