import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import 'api_client.dart';
import '../firebase_options.dart';

@pragma('vm:entry-point')
Future<void> handleBackgroundPush(RemoteMessage message) async {
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
}

class PushService {
  PushService(this.api);

  final ApiClient api;
  bool enabled = false;
  String? _deviceId;
  void Function(RemoteMessage)? onOpen;
  void Function(RemoteMessage)? onForeground;
  StreamSubscription<String>? _tokenSubscription;

  Future<void> initialize() async {
    try {
      await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
      FirebaseMessaging.onBackgroundMessage(handleBackgroundPush);
      FirebaseMessaging.onMessageOpenedApp.listen((message) => onOpen?.call(message));
      FirebaseMessaging.onMessage.listen((message) => onForeground?.call(message));
      _tokenSubscription = FirebaseMessaging.instance.onRegistered.listen((deviceId) {
        _deviceId = deviceId;
        if (api.token != null) _register(deviceId);
      });
      enabled = true;
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) {
        Future<void>.delayed(const Duration(seconds: 1), () => onOpen?.call(initial));
      }
    } catch (error) {
      debugPrint('Firebase push unavailable: $error');
    }
  }

  Future<void> sync() async {
    if (!enabled || api.token == null) return;
    try {
      await FirebaseMessaging.instance.requestPermission();
      await FirebaseMessaging.instance.register();
      if (_deviceId != null) await _register(_deviceId!);
    } catch (error) {
      debugPrint('Push registration failed: $error');
    }
  }

  Future<void> unregister() async {
    if (!enabled || api.token == null) return;
    try {
      if (_deviceId != null) await api.delete('/push/devices?token=${Uri.encodeQueryComponent(_deviceId!)}');
    } catch (error) {
      debugPrint('Push unregister failed: $error');
    }
  }

  Future<void> _register(String token) async {
    try {
      await api.post('/push/devices', body: {'token': token, 'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android'});
    } catch (error) {
      debugPrint('Push token sync failed: $error');
    }
  }

  Future<void> dispose() async => _tokenSubscription?.cancel();
}
