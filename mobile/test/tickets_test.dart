import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:helpdesk_mobile/auth_api.dart';
import 'package:helpdesk_mobile/ticket_pages.dart';
import 'package:flutter/material.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(
    () => FlutterSecureStorage.setMockInitialValues({
      'access_token': 'test-token',
    }),
  );
  testWidgets('Empty ticket list and authenticated request', (tester) async {
    final api = AuthApi(
      client: MockClient((request) async {
        expect(request.headers['Authorization'], 'Bearer test-token');
        expect(request.url.path, '/api/tickets');
        return http.Response(
          jsonEncode({'data': [], 'current_page': 1, 'last_page': 1}),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      }),
    );
    addTearDown(api.client.close);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: TicketHome(
            api: api,
            user: const {'name': 'Sinh viên', 'role': 'student'},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Chưa có phiếu sự cố nào.'), findsOneWidget);
  });
  testWidgets('Ticket opens details with history', (tester) async {
    final ticket = {
      'id': 1,
      'title': 'Máy chiếu hỏng',
      'ticket_number': 'HD-1',
      'status': 'new',
      'description': 'Không lên hình',
      'logs': [
        {
          'note': 'Đã gửi báo cáo',
          'user': {'name': 'Sinh viên'},
        },
      ],
    };
    final api = AuthApi(
      client: MockClient(
        (request) async => http.Response(
          jsonEncode(
            request.url.path.endsWith('/1')
                ? {'data': ticket}
                : {
                    'data': [ticket],
                    'current_page': 1,
                    'last_page': 1,
                  },
          ),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        ),
      ),
    );
    addTearDown(api.client.close);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: TicketHome(
            api: api,
            user: const {'name': 'Sinh viên', 'role': 'student'},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Máy chiếu hỏng'),
      200,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await tester.tap(find.text('Máy chiếu hỏng'));
    await tester.pumpAndSettle();
    expect(find.text('Chi tiết phiếu'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Đã gửi báo cáo'),
      200,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .last,
    );
    expect(find.text('Đã gửi báo cáo'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Không lên hình'),
      200,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .last,
    );
    expect(find.text('Không lên hình'), findsOneWidget);
  });
  test('Expired token returns actionable error', () async {
    final api = AuthApi(
      client: MockClient((_) async => http.Response('{}', 401)),
    );
    addTearDown(api.client.close);
    await expectLater(
      api.get('tickets'),
      throwsA(
        isA<Exception>().having(
          (e) => e.toString(),
          'message',
          contains('Phiên đăng nhập đã hết hạn'),
        ),
      ),
    );
  });
}
