import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart' show ThemeMode;

import 'api_client.dart';
import 'push_service.dart';

class AppState extends ChangeNotifier {
  AppState(this.api) : push = PushService(api);

  final ApiClient api;
  final PushService push;
  Map<String, dynamic>? user;
  bool initializing = true;
  int unreadNotificationCount = 0;
  Timer? _notificationRefreshTimer;
  ThemeMode themeMode = ThemeMode.dark;

  bool get signedIn => user != null;
  String get role => user?['role']?.toString() ?? 'student';

  Future<void> initialize() async {
    await api.initialize();
    await push.initialize();
    themeMode = await api.loadAppearance() == 'light'
        ? ThemeMode.light
        : ThemeMode.dark;
    if (api.token != null) {
      try {
        await refreshProfile();
        await push.sync();
        await refreshUnreadNotifications();
        _startNotificationRefresh();
      } on ApiException catch (error) {
        if (error.statusCode == 401) await api.clearToken();
      }
    }
    initializing = false;
    notifyListeners();
  }

  Future<Map<String, dynamic>> login(
    String email,
    String password,
    String deviceName,
    {required bool remember}
  ) async {
    final result = _map(
      await api.post(
        '/auth/login',
        body: {
          'email': email.trim(),
          'password': password,
          'device_name': deviceName,
        },
      ),
    );
    final token = result['access_token']?.toString();
    if (token == null || token.isEmpty) {
      throw ApiException('Máy chủ chưa trả access token.');
    }
    await api.saveToken(token, remember: remember);
    await refreshProfile();
    await push.sync();
    await refreshUnreadNotifications();
    _startNotificationRefresh();
    return result;
  }

  Future<void> refreshProfile() async {
    final result = _map(await api.get('/me'));
    user = _map(result['data']);
    notifyListeners();
  }

  Future<void> refreshUnreadNotifications() async {
    if (api.token == null) return;
    try {
      final result = _map(await api.get('/notifications', query: {'per_page': 1}));
      final count = int.tryParse(result['unread_count']?.toString() ?? '') ?? 0;
      if (count != unreadNotificationCount) {
        unreadNotificationCount = count;
        notifyListeners();
      }
    } catch (_) {
      // Keep the app usable while the API is temporarily unavailable.
    }
  }

  void _startNotificationRefresh() {
    _notificationRefreshTimer?.cancel();
    _notificationRefreshTimer = Timer.periodic(
      const Duration(seconds: 30),
      (_) => refreshUnreadNotifications(),
    );
  }

  Future<dynamic> register(Map<String, dynamic> body) =>
      api.post('/auth/register', body: body);
  Future<dynamic> verifyOtp(String email, String otp) => api.post(
    '/auth/email/verify-otp',
    body: {'email': email.trim(), 'otp': otp},
  );
  Future<dynamic> resendOtp(String email) =>
      api.post('/auth/email/resend-otp', body: {'email': email.trim()});
  Future<dynamic> forgotPassword(String email) =>
      api.post('/auth/password/forgot', body: {'email': email.trim()});
  Future<dynamic> resetPassword(
    String email,
    String otp,
    String password,
    String confirmation,
  ) => api.post(
    '/auth/password/reset',
    body: {
      'email': email.trim(),
      'otp': otp,
      'password': password,
      'password_confirmation': confirmation,
    },
  );

  Future<void> changePassword(
    String current,
    String next,
    String confirmation,
  ) async {
    await api.post(
      '/account/password',
      body: {
        'current_password': current,
        'password': next,
        'password_confirmation': confirmation,
      },
    );
  }

  Future<void> logout() async {
    try {
      await push.unregister();
      await api.post('/auth/logout');
    } finally {
      await api.clearToken();
      user = null;
      unreadNotificationCount = 0;
      _notificationRefreshTimer?.cancel();
      notifyListeners();
    }
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    themeMode = mode;
    notifyListeners();
    await api.saveAppearance(mode == ThemeMode.light ? 'light' : 'dark');
  }

  Future<void> setServerUrl(String url) async {
    await api.setBaseUrl(url);
    if (api.token != null) {
      try {
        await refreshProfile();
        await push.sync();
      } on ApiException catch (error) {
        if (error.statusCode == 401) {
          await api.clearToken();
          user = null;
        }
      }
    }
    notifyListeners();
  }

  static Map<String, dynamic> _map(dynamic value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
}
