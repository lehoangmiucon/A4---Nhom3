import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:helpdesk_mobile/auth_api.dart';
import 'package:helpdesk_mobile/ticket_pages.dart';
import 'package:helpdesk_mobile/notifications_page.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(
    () => FlutterSecureStorage.setMockInitialValues({'access_token': 'test'}),
  );
  testWidgets(
    'Review requires selection then sends dissatisfaction and comment',
    (tester) async {
      int sent = 0;
      final api = AuthApi(
        client: MockClient((request) async {
          sent++;
          expect(request.method, 'PATCH');
          expect(request.url.path, '/api/tickets/1/review');
          expect(jsonDecode(request.body), {
            'satisfied': false,
            'comment': 'Cần nhanh hơn',
          });
          return http.Response('{}', 200);
        }),
      );
      addTearDown(api.client.close);
      tester.view.physicalSize = const Size(600, 1200);
      tester.view.devicePixelRatio = 1;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
      });
      await tester.pumpWidget(MaterialApp(home: ReviewPage(api: api, id: 1)));
      await tester.tap(find.text('Gửi đánh giá'));
      await tester.pump();
      expect(sent, 0);
      expect(find.text('Chọn mức độ hài lòng trước khi gửi.'), findsOneWidget);
      await tester.tap(find.text('Chưa hài lòng'));
      await tester.pump();
      await tester.enterText(find.byType(TextField), 'Cần nhanh hơn');
      await tester.tap(find.text('Gửi đánh giá'));
      await tester.pumpAndSettle();
      expect(sent, 1);
    },
  );
  testWidgets(
    'Notifications group by day and mark all through authenticated API',
    (tester) async {
      bool marked = false;
      final api = AuthApi(
        client: MockClient((request) async {
          if (request.method == 'PATCH') {
            marked = true;
            expect(request.url.path, '/api/notifications/read-all');
            expect(request.headers['Authorization'], 'Bearer test');
            return http.Response('{}', 200);
          }
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': 'a',
                  'title': 'Phiếu đang xử lý',
                  'body': 'Kỹ thuật viên đã nhận',
                  'created_at': DateTime.now().toIso8601String(),
                  'read_at': null,
                },
                {
                  'id': 'b',
                  'title': 'Phiếu cũ',
                  'body': 'Đã hoàn tất',
                  'created_at': DateTime.now()
                      .subtract(const Duration(days: 2))
                      .toIso8601String(),
                  'read_at': null,
                },
              ],
              'current_page': 1,
              'last_page': 1,
            }),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }),
      );
      addTearDown(api.client.close);
      await tester.pumpWidget(MaterialApp(home: NotificationsPage(api: api)));
      await tester.pumpAndSettle();
      expect(find.text('Hôm nay'), findsOneWidget);
      expect(find.text('Trước đó'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Đánh dấu tất cả đã đọc'),
        200,
        scrollable: find
            .descendant(
              of: find.byType(ListView),
              matching: find.byType(Scrollable),
            )
            .first,
      );
      await tester.tap(find.text('Đánh dấu tất cả đã đọc'));
      await tester.pumpAndSettle();
      expect(marked, true);
      expect(find.byIcon(Icons.circle), findsNothing);
    },
  );
}
