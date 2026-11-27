import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient();

  static const _storage = FlutterSecureStorage();
  static const _baseKey = 'api_base_url';
  static const _tokenKey = 'access_token';
  static const _appearanceKey = 'appearance_mode';
  static const defaultBaseUrl =
      'http://10.0.2.2:8888/Laravelpro/helpdesk/public/api';

  String _baseUrl = defaultBaseUrl;
  String? _token;

  String get baseUrl => _baseUrl;
  String? get token => _token;

  Future<void> initialize() async {
    _baseUrl = _normalize(await _storage.read(key: _baseKey) ?? defaultBaseUrl);
    _token = await _storage.read(key: _tokenKey);
  }

  Future<void> setBaseUrl(String value) async {
    _baseUrl = _normalize(value.trim());
    await _storage.write(key: _baseKey, value: _baseUrl);
  }

  Future<String> loadAppearance() async =>
      await _storage.read(key: _appearanceKey) ?? 'dark';

  Future<void> saveAppearance(String appearance) async {
    await _storage.write(key: _appearanceKey, value: appearance);
  }

  Future<void> saveToken(String token, {required bool remember}) async {
    _token = token;
    if (remember) {
      await _storage.write(key: _tokenKey, value: token);
    } else {
      await _storage.delete(key: _tokenKey);
    }
  }

  Future<void> clearToken() async {
    _token = null;
    await _storage.delete(key: _tokenKey);
  }

  String _normalize(String input) {
    var value = input.trim().replaceAll(RegExp(r'/+$'), '');
    if (!value.endsWith('/api')) value = '$value/api';
    return value;
  }

  String resolveServerUrl(String raw) {
    final base = Uri.parse(_baseUrl);
    final publicRoot = base.path.replaceFirst(RegExp(r'/api/?$'), '');
    final candidate = Uri.tryParse(raw);
    if (candidate != null && candidate.hasScheme) {
      if (candidate.host == 'localhost' ||
          candidate.host == '127.0.0.1' ||
          candidate.host == '10.0.2.2') {
        var path = candidate.path;
        if (publicRoot.isNotEmpty && !path.startsWith('$publicRoot/')) {
          path = publicRoot + (path.startsWith('/') ? path : '/$path');
        }
        return candidate
            .replace(
              scheme: base.scheme,
              host: base.host,
              port: base.hasPort ? base.port : null,
              path: path,
            )
            .toString();
      }
      return candidate.toString();
    }
    final path = raw.startsWith('/') ? raw : '/$raw';
    return base
        .replace(
          path: path.startsWith('$publicRoot/') ? path : publicRoot + path,
        )
        .toString();
  }

  String resolveAssetUrl(String raw) {
    final candidate = Uri.tryParse(raw);
    if (candidate != null && candidate.hasScheme) return resolveServerUrl(raw);
    if (raw.startsWith('/storage/')) return resolveServerUrl(raw);
    return resolveServerUrl('/storage/' + raw.replaceFirst(RegExp(r'^/+'), ''));
  }

  Uri _uri(String path, [Map<String, dynamic>? query]) {
    final cleanPath = path.replaceFirst(RegExp(r'^/+'), '');
    final uri = Uri.parse('$_baseUrl/$cleanPath');
    if (query == null || query.isEmpty) return uri;
    return uri.replace(
      queryParameters: {
        ...uri.queryParameters,
        ...query.map((key, value) => MapEntry(key, value.toString())),
      },
    );
  }

  Map<String, String> _headers({bool json = false}) => {
    'Accept': 'application/json',
    if (json) 'Content-Type': 'application/json',
    if (_token != null && _token!.isNotEmpty) 'Authorization': 'Bearer $_token',
  };

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _send('GET', path, query: query);
  Future<dynamic> post(String path, {Map<String, dynamic>? body}) =>
      _send('POST', path, body: body);
  Future<dynamic> patch(String path, {Map<String, dynamic>? body}) =>
      _send('PATCH', path, body: body);
  Future<dynamic> put(String path, {Map<String, dynamic>? body}) =>
      _send('PUT', path, body: body);
  Future<dynamic> delete(String path) => _send('DELETE', path);

  Future<dynamic> upload(
    String path, {
    required Map<String, String> fields,
    Map<String, List<XFile>> files = const {},
    String method = 'POST',
  }) async {
    final request = http.MultipartRequest(method, _uri(path))
      ..headers.addAll(_headers())
      ..fields.addAll(fields);
    for (final entry in files.entries) {
      for (final file in entry.value) {
        request.files.add(
          await http.MultipartFile.fromPath(entry.key, file.path),
        );
      }
    }
    final response = await http.Response.fromStream(
      await request.send().timeout(const Duration(seconds: 30)),
    );
    return _decode(response);
  }

  Future<dynamic> _send(
    String method,
    String path, {
    Map<String, dynamic>? query,
    Map<String, dynamic>? body,
  }) async {
    final uri = _uri(path, query);
    try {
      late http.Response response;
      final headers = _headers(json: body != null);
      switch (method) {
        case 'GET':
          response = await http
              .get(uri, headers: headers)
              .timeout(const Duration(seconds: 15));
          break;
        case 'POST':
          response = await http
              .post(uri, headers: headers, body: jsonEncode(body ?? {}))
              .timeout(const Duration(seconds: 15));
          break;
        case 'PATCH':
          response = await http
              .patch(uri, headers: headers, body: jsonEncode(body ?? {}))
              .timeout(const Duration(seconds: 15));
          break;
        case 'PUT':
          response = await http
              .put(uri, headers: headers, body: jsonEncode(body ?? {}))
              .timeout(const Duration(seconds: 15));
          break;
        case 'DELETE':
          response = await http
              .delete(uri, headers: headers)
              .timeout(const Duration(seconds: 15));
          break;
        default:
          throw ApiException('HTTP method không được hỗ trợ: $method');
      }
      return await _decode(response);
    } on TimeoutException {
      throw ApiException(
        'Máy chủ phản hồi quá lâu. Kiểm tra cấu hình mạng và thử lại.',
      );
    } on SocketException {
      throw ApiException(
        'Không kết nối được máy chủ. Kiểm tra URL API, mạng Wi-Fi và Apache/MySQL.',
      );
    } on http.ClientException {
      throw ApiException('Lỗi mạng khi kết nối máy chủ Helpdesk.');
    } on FormatException {
      throw ApiException('URL API không hợp lệ.');
    }
  }

  Future<dynamic> _decode(http.Response response) async {
    final raw = response.body;
    dynamic payload;
    try {
      payload = raw.isEmpty ? <String, dynamic>{} : jsonDecode(raw);
    } on FormatException {
      payload = <String, dynamic>{'message': raw};
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final rawMessage = payload is Map
          ? (payload['message']?.toString() ??
                (payload['errors'] is Map
                    ? (payload['errors'] as Map).values
                          .expand((e) => e is List ? e : [e])
                          .join(' ')
                    : 'Yêu cầu thất bại (${response.statusCode}).'))
          : 'Yêu cầu thất bại (${response.statusCode}).';
      final message = _localizeServerError(rawMessage, response.statusCode);
      throw ApiException(
        message,
        statusCode: response.statusCode,
        errors: payload is Map && payload['errors'] is Map
            ? Map<String, dynamic>.from(payload['errors'] as Map)
            : null,
      );
    }
    return payload;
  }

  String _localizeServerError(String message, int statusCode) {
    final normalized = message.trim().toLowerCase();
    if (normalized.contains('csrf token mismatch')) {
      return 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.';
    }
    if (normalized == 'unauthenticated.' ||
        normalized == 'unauthenticated') {
      return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
    }
    if (normalized == 'this action is unauthorized.') {
      return 'Bạn không có quyền thực hiện thao tác này.';
    }
    if (normalized == 'the given data was invalid.') {
      return 'Dữ liệu gửi lên chưa hợp lệ. Vui lòng kiểm tra lại.';
    }
    if (normalized == 'server error' ||
        normalized == 'internal server error') {
      return 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.';
    }

    if (statusCode == 401) {
      return 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.';
    }
    if (statusCode == 403) {
      return 'Bạn không có quyền thực hiện thao tác này.';
    }
    if (statusCode == 404) {
      return 'Không tìm thấy dữ liệu yêu cầu.';
    }
    if (statusCode == 419) {
      return 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.';
    }
    if (statusCode == 429) {
      return 'Bạn thao tác quá nhanh. Vui lòng chờ một lúc rồi thử lại.';
    }
    if (statusCode >= 500) {
      return 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.';
    }

    // Never show an untranslated server response (or raw HTML error page) in
    // the app. Laravel's Vietnamese messages contain Vietnamese diacritics.
    if (message.contains('<html') ||
        message.contains('<!doctype') ||
        !RegExp(r'[À-ỹ]').hasMatch(message)) {
      if (statusCode == 422) {
        return 'Dữ liệu gửi lên chưa hợp lệ. Vui lòng kiểm tra lại.';
      }
      return 'Yêu cầu không thành công. Vui lòng kiểm tra lại và thử lại.';
    }
    return message;
  }
}
