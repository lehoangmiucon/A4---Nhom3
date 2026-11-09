import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http_parser/http_parser.dart';

class AuthApi {
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );
  final http.Client client;
  final FlutterSecureStorage storage;
  AuthApi({http.Client? client, this.storage = const FlutterSecureStorage()})
    : client = client ?? http.Client();

  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> body,
  ) async {
    final response = await client
        .post(
          Uri.parse('$baseUrl/$path'),
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: jsonEncode(body),
        )
        .timeout(const Duration(seconds: 25));
    Map<String, dynamic> data;
    try {
      data =
          jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw Exception(
        'Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại sau.',
      );
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = data['errors'];
      throw Exception(
        errors is Map && errors.isNotEmpty
            ? (errors.values.first as List).first
            : data['message'] ?? 'Không thể thực hiện yêu cầu.',
      );
    }
    return data;
  }

  Future<Map<String, dynamic>> get(String path) async {
    final token = await storage.read(key: 'access_token');
    final response = await client
        .get(
          Uri.parse('$baseUrl/$path'),
          headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer $token',
          },
        )
        .timeout(const Duration(seconds: 20));
    if (response.statusCode == 401) {
      throw Exception(
        'Phiên đăng nhập đã hết hạn. Vui lòng đăng xuất và đăng nhập lại.',
      );
    }
    if (response.statusCode == 403) {
      throw Exception('Bạn không có quyền xem nội dung này.');
    }
    if (response.statusCode != 200) {
      throw Exception('Không tải được dữ liệu. Vui lòng thử lại.');
    }
    return jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> patch(
    String path,
    Map<String, dynamic> body, {
    String method = 'PATCH',
  }) async {
    final token = await storage.read(key: 'access_token');
    final write = method == 'POST'
        ? client.post
        : method == 'DELETE'
        ? client.delete
        : method == 'PUT'
        ? client.put
        : client.patch;
    final response = await write(
      Uri.parse('$baseUrl/$path'),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode(body),
    ).timeout(const Duration(seconds: 25));
    Map<String, dynamic> data;
    try {
      data =
          jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw Exception('Không đọc được kết quả. Vui lòng tải lại để kiểm tra.');
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final errors = data['errors'];
      throw Exception(
        errors is Map && errors.isNotEmpty
            ? (errors.values.first as List).first
            : data['message'] ?? 'Không thể thực hiện yêu cầu.',
      );
    }
    return data;
  }

  Future<Map<String, dynamic>> createTicket(
    Map<String, String> fields,
    List<XFile> images, {
    String path = 'tickets',
    int successStatus = 201,
  }) async {
    final token = await storage.read(key: 'access_token');
    final request = http.MultipartRequest('POST', Uri.parse('$baseUrl/$path'))
      ..headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      })
      ..fields.addAll(fields);
    for (final image in images) {
      final extension = image.name.split('.').last.toLowerCase();
      final subtype = extension == 'png'
          ? 'png'
          : extension == 'webp'
          ? 'webp'
          : 'jpeg';
      request.files.add(
        http.MultipartFile.fromBytes(
          'attachments[]',
          await image.readAsBytes(),
          filename: image.name,
          contentType: MediaType('image', subtype),
        ),
      );
    }
    // No automatic retry: a timeout can occur after the server created a ticket.
    final response = await http.Response.fromStream(
      await client.send(request).timeout(const Duration(seconds: 60)),
    ).timeout(const Duration(seconds: 60));
    Map<String, dynamic> data;
    try {
      data =
          jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw Exception(
        'Chưa xác nhận được kết quả gửi. Kiểm tra danh sách phiếu trước khi gửi lại.',
      );
    }
    if (response.statusCode != successStatus) {
      final errors = data['errors'];
      throw Exception(
        errors is Map && errors.isNotEmpty
            ? (errors.values.first as List).first
            : data['message'] ?? 'Không thể gửi phiếu.',
      );
    }
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await client
        .post(
          Uri.parse('$baseUrl/auth/login'),
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: jsonEncode({
            'email': email.trim(),
            'password': password,
            'device_name': 'helpdesk-android',
          }),
        )
        .timeout(const Duration(seconds: 20));
    Map<String, dynamic> data;
    try {
      data =
          jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw Exception(
        'Máy chủ trả về dữ liệu không hợp lệ. Vui lòng kiểm tra địa chỉ API.',
      );
    }
    if (response.statusCode != 200) {
      final errors = data['errors'];
      final message = errors is Map && errors.isNotEmpty
          ? (errors.values.first as List).first.toString()
          : data['message']?.toString();
      throw Exception(message ?? 'Không thể đăng nhập. Vui lòng thử lại.');
    }
    final token = data['access_token'];
    if (token is! String || token.isEmpty || data['user'] is! Map) {
      throw Exception('Thông tin đăng nhập trả về không hợp lệ.');
    }
    await storage.write(key: 'access_token', value: token);
    return Map<String, dynamic>.from(data['user'] as Map);
  }

  Future<void> logout() async {
    final token = await storage.read(key: 'access_token');
    if (token != null) {
      final response = await client
          .post(
            Uri.parse('$baseUrl/auth/logout'),
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer $token',
            },
          )
          .timeout(const Duration(seconds: 20));
      if (response.statusCode != 200 && response.statusCode != 401) {
        throw Exception('Chưa thể đăng xuất. Vui lòng thử lại.');
      }
    }
    await storage.delete(key: 'access_token');
  }
}
