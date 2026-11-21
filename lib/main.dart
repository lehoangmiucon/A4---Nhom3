import 'package:flutter/material.dart';

import 'screens.dart';
import 'services/api_client.dart';
import 'services/app_state.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final api = ApiClient();
  final state = AppState(api);
  await state.initialize();
  AppScope.current = state;
  runApp(HelpdeskApp(state: state));
}

class HelpdeskApp extends StatelessWidget {
  const HelpdeskApp({super.key, required this.state});

  final AppState state;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: state,
      builder: (context, _) => MaterialApp(
        title: 'Helpdesk',
        debugShowCheckedModeBanner: false,
        themeMode: state.themeMode,
        theme: _buildTheme(Brightness.light),
        darkTheme: _buildTheme(Brightness.dark),
        scrollBehavior: const _NoStretchScrollBehavior(),
        home: state.signedIn ? AppShell(state: state) : AuthPage(state: state),
      ),
    );
  }
}

class _NoStretchScrollBehavior extends MaterialScrollBehavior {
  const _NoStretchScrollBehavior();

  @override
  Widget buildOverscrollIndicator(
    BuildContext context,
    Widget child,
    ScrollableDetails details,
  ) => child;
}

ThemeData _buildTheme(Brightness brightness) {
  final isLight = brightness == Brightness.light;
  final background = isLight
      ? const Color(0xFFF4F8FB)
      : const Color(0xFF10233F);
  final surface = isLight ? const Color(0xFFFFFFFF) : const Color(0xFF183354);
  final inputSurface = isLight
      ? const Color(0xFFF8FBFD)
      : const Color(0xFF102843);
  final border = isLight ? const Color(0xFFE4EDF4) : const Color(0xFF2B4966);
  final foreground = isLight
      ? const Color(0xFF18334E)
      : const Color(0xFFEAF3FA);
  final muted = isLight ? const Color(0xFF72859A) : const Color(0xFFA9C1D5);
  final accent = isLight ? const Color(0xFF1887D5) : const Color(0xFF49AEE5);

  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: ColorScheme.fromSeed(
      seedColor: const Color(0xFF1887D5),
      brightness: brightness,
      primary: accent,
      surface: surface,
      onSurface: foreground,
      onSurfaceVariant: muted,
      outlineVariant: border,
      secondary: isLight ? const Color(0xFF27835F) : const Color(0xFF49C69A),
      tertiary: isLight ? const Color(0xFF6957BF) : const Color(0xFFA99BEA),
    ),
    scaffoldBackgroundColor: background,
    appBarTheme: AppBarTheme(
      backgroundColor: background,
      foregroundColor: foreground,
      surfaceTintColor: Colors.transparent,
      centerTitle: false,
      toolbarHeight: 58,
      elevation: 0,
      scrolledUnderElevation: 0,
      shadowColor: Colors.transparent,
      shape: Border(bottom: BorderSide(color: border, width: .6)),
      titleTextStyle: TextStyle(
        fontSize: 19,
        fontWeight: FontWeight.w700,
        color: foreground,
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      height: 70,
      backgroundColor: surface,
      indicatorColor: isLight
          ? const Color(0xFFE3F2FB)
          : const Color(0xFF244B70),
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(
          color: states.contains(WidgetState.selected) ? accent : muted,
        ),
      ),
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontSize: 11,
          fontWeight: states.contains(WidgetState.selected)
              ? FontWeight.w700
              : FontWeight.w500,
          color: states.contains(WidgetState.selected) ? accent : muted,
        ),
      ),
    ),
    tabBarTheme: TabBarThemeData(
      labelColor: accent,
      unselectedLabelColor: muted,
      labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
      unselectedLabelStyle: const TextStyle(
        fontSize: 13,
        fontWeight: FontWeight.w500,
      ),
      indicatorColor: accent,
      dividerColor: border,
      indicatorSize: TabBarIndicatorSize.label,
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: border),
      ),
      margin: const EdgeInsets.symmetric(vertical: 6),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: inputSurface,
      labelStyle: TextStyle(color: muted, fontSize: 13),
      hintStyle: TextStyle(color: muted.withValues(alpha: .82), fontSize: 14),
      prefixIconColor: muted,
      suffixIconColor: muted,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: accent, width: 1.5),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 14),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(0, 50),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        side: BorderSide(color: border),
      ),
    ),
    listTileTheme: ListTileThemeData(
      iconColor: accent,
      textColor: foreground,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
    ),
    dividerTheme: DividerThemeData(color: border, thickness: 1),
    snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
    dialogTheme: DialogThemeData(
      backgroundColor: surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    ),
  );
}
