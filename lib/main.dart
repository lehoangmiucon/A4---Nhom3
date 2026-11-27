import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

import 'screens.dart';
import 'services/api_client.dart';
import 'services/app_state.dart';

final appNavigatorKey = GlobalKey<NavigatorState>();
final appMessengerKey = GlobalKey<ScaffoldMessengerState>();
const notificationSoundChannel = MethodChannel(
  'com.apphelpdesk/notification_sound',
);

void openPush(RemoteMessage message) {
  final navigator = appNavigatorKey.currentState;
  if (navigator == null) return;
  final type = message.data['type']?.toString();
  final ticketId = message.data['ticket_id']?.toString();
  navigator.push(
    MaterialPageRoute<void>(
      builder: (_) => type == 'chat'
          ? ChatPage(
              initialConversationId: message.data['conversation_id']?.toString(),
            )
          : type == 'ticket' && ticketId != null && ticketId.isNotEmpty
          ? TicketDetailPage(ticket: {'id': ticketId})
          : const NotificationsPage(),
    ),
  );
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final api = ApiClient();
  final state = AppState(api);
  await state.initialize();
  state.push.onOpen = openPush;
  state.push.onForeground = (message) {
    unawaited(
      notificationSoundChannel.invokeMethod<void>('play').catchError((_) {}),
    );
    state.refreshUnreadNotifications();
    state.refreshUnreadChats();
    final isChat = message.data['type'] == 'chat';
    final isTicket = message.data['type'] == 'ticket';
    final accent = isChat
        ? const Color(0xFF62C6F3)
        : isTicket
        ? const Color(0xFFFFC36C)
        : const Color(0xFF9CB6CF);
    appMessengerKey.currentState?.showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: Colors.transparent,
        elevation: 0,
        padding: EdgeInsets.zero,
        margin: const EdgeInsets.fromLTRB(14, 0, 14, 18),
        duration: const Duration(seconds: 6),
        content: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: () => openPush(message),
            child: Ink(
              padding: const EdgeInsets.fromLTRB(14, 13, 16, 13),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: isChat
                      ? const [Color(0xFF124E78), Color(0xFF123454)]
                      : isTicket
                      ? const [Color(0xFF77501D), Color(0xFF392B1B)]
                      : const [Color(0xFF334D68), Color(0xFF1D3147)],
                ),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: accent.withValues(alpha: .62)),
                boxShadow: [
                  BoxShadow(
                    color: accent.withValues(alpha: .18),
                    blurRadius: 22,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: accent.withValues(alpha: .18),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(
                      isChat
                          ? Icons.chat_bubble_rounded
                          : isTicket
                          ? Icons.assignment_late_rounded
                          : Icons.notifications_rounded,
                      color: accent,
                      size: 21,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          isChat
                              ? 'TIN NHẮN MỚI'
                              : isTicket
                              ? 'PHIẾU SỰ CỐ'
                              : 'THÔNG BÁO HELPDESK',
                          style: TextStyle(
                            color: accent,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: .8,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          message.notification?.title ??
                              (isChat
                                  ? 'Bạn có tin nhắn mới'
                                  : isTicket
                                  ? 'Có cập nhật phiếu sự cố'
                                  : 'Thông báo mới'),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                        if ((message.notification?.body ?? '').isNotEmpty) ...[
                          const SizedBox(height: 3),
                          Text(
                            message.notification!.body!,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Color(0xFFD7E4EF),
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Icon(Icons.chevron_right_rounded, color: accent),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  };
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
        navigatorKey: appNavigatorKey,
        scaffoldMessengerKey: appMessengerKey,
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
