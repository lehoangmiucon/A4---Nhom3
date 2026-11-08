import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:helpdesk_mobile/auth_api.dart';
import 'package:helpdesk_mobile/staff_pages.dart';
import 'package:helpdesk_mobile/account_pages.dart';

void main() {
  testWidgets('Today schedule opens its ticket', (tester) async {
    int? opened;
    final api = AuthApi(client: MockClient((request) async => http.Response(jsonEncode(request.url.path.endsWith('/schedules') ? {'data': [{'id': 3, 'ticket_id': 8, 'starts_at': DateTime.now().toIso8601String(), 'ticket': {'ticket_number': 'HD-TODAY', 'title': 'Công việc hôm nay', 'status': 'assigned'}}]} : {'data': [], 'current_page': 1, 'last_page': 1}), 200, headers: {'content-type':'application/json; charset=utf-8'})));
    addTearDown(api.client.close);
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: StaffAssignments(api: api, onOpen: (id) { opened = id; }))));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.textContaining('HD-TODAY'), 200, scrollable: find.descendant(of: find.byType(ListView), matching: find.byType(Scrollable)).first);
    await tester.tap(find.textContaining('HD-TODAY'));
    expect(opened, 8);
  });
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(
    () => FlutterSecureStorage.setMockInitialValues({'access_token': 'test'}),
  );
  testWidgets('Staff acknowledges schedule before updating assigned ticket', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(600, 1600);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    bool acknowledged = false, updated = false;
    final api = AuthApi(
      client: MockClient((request) async {
        expect(request.headers['Authorization'], 'Bearer test');
        if (request.method == 'POST') {
          if (request.url.path.contains('acknowledge')) {
            acknowledged = true;
            return http.Response('{}', 200);
          }
          updated = true;
          expect(request.body, contains('Kiểm tra đường truyền'));
          return http.Response(
            jsonEncode({
              'data': {'status': 'in_progress'},
            }),
            200,
          );
        }
        return http.Response(
          jsonEncode({
            'data': {
              'id': 1,
              'ticket_number': 'HD-1',
              'title': 'Wi-Fi hỏng',
              'reporter_name': 'Student',
              'description': 'Mất mạng',
              'status': acknowledged ? 'in_progress' : 'assigned',
              'staff_schedules': [
                {
                  'id': 3,
                  'status': acknowledged ? 'acknowledged' : 'scheduled',
                  'starts_at': '2026-11-08T08:00:00Z',
                },
              ],
            },
          }),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      }),
    );
    addTearDown(api.client.close);
    await tester.pumpWidget(
      MaterialApp(home: StaffProcessPage(api: api, id: 1)),
    );
    await tester.pumpAndSettle();
    expect(find.text('Cập nhật tiến độ'), findsOneWidget);
    await tester.tap(find.text('Xác nhận lịch và bắt đầu xử lý'));
    await tester.pumpAndSettle();
    expect(acknowledged, true);
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Đang xử lý').last);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byType(TextFormField).last,
      'Kiểm tra đường truyền',
    );
    await tester.ensureVisible(find.text('Cập nhật tiến độ'));
    await tester.tap(find.text('Cập nhật tiến độ'));
    await tester.pumpAndSettle();
    expect(updated, true);
  });
  testWidgets('Staff password uses current password without email OTP', (
    tester,
  ) async {
    bool changed = false;
    final api = AuthApi(
      client: MockClient((request) async {
        expect(request.url.path, '/api/account/password');
        expect(request.method, 'POST');
        expect(jsonDecode(request.body), {
          'current_password': 'OldPassword123',
          'password': 'NewPassword123',
          'password_confirmation': 'NewPassword123',
        });
        return http.Response('{}', 200);
      }),
    );
    addTearDown(api.client.close);
    await tester.pumpWidget(
      MaterialApp(
        home: StaffPasswordPage(
          api: api,
          onChanged: () async {
            changed = true;
          },
        ),
      ),
    );
    final fields = find.byType(TextFormField);
    await tester.enterText(fields.at(0), 'OldPassword123');
    await tester.enterText(fields.at(1), 'NewPassword123');
    await tester.enterText(fields.at(2), 'NewPassword123');
    await tester.ensureVisible(
      find.widgetWithText(FilledButton, 'Đổi mật khẩu'),
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Đổi mật khẩu'));
    await tester.pumpAndSettle();
    expect(changed, true);
  });
}
