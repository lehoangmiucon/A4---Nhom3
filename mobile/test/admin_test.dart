import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:helpdesk_mobile/auth_api.dart';
import 'package:helpdesk_mobile/admin_pages.dart';
import 'package:helpdesk_mobile/admin_users_page.dart';
import 'package:helpdesk_mobile/main.dart';

http.Response jsonResponse(Object data) => http.Response(
  jsonEncode(data),
  200,
  headers: {'content-type': 'application/json; charset=utf-8'},
);
void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUpAll(() async {
    final font = FontLoader('Roboto')
      ..addFont(
        Future.value(
          ByteData.sublistView(
            File('test/assets/Roboto-Regular.ttf').readAsBytesSync(),
          ),
        ),
      );
    await font.load();
    final icons = FontLoader('MaterialIcons')
      ..addFont(
        Future.value(
          ByteData.sublistView(
            File('test/assets/MaterialIcons-Regular.otf').readAsBytesSync(),
          ),
        ),
      );
    await icons.load();
  });
  setUp(
    () => FlutterSecureStorage.setMockInitialValues({
      'access_token': 'admin-test',
    }),
  );
  testWidgets(
    'Admin overview and users render at phone width with real API shapes',
    (tester) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
      });
      final ticket = {
        'id': 8,
        'ticket_number': 'HD-QA-008',
        'title': 'Máy chiếu không lên hình',
        'status': 'new',
        'building_label': 'Khu giảng đường A',
        'room_label': 'Phòng A.201',
      };
      final users = [
        {
          'id': 2,
          'name': 'Sinh viên kiểm thử',
          'email': 'student@example.test',
          'role': 'student',
          'member_type': 'student',
          'is_deleted': false,
          'email_verified_at': '2026-11-08',
        },
        {
          'id': 3,
          'name': 'Giảng viên kiểm thử',
          'email': 'lecturer@example.test',
          'role': 'student',
          'member_type': 'lecturer',
          'is_deleted': false,
          'email_verified_at': '2026-11-08',
        },
        {
          'id': 4,
          'name': 'KTV kiểm thử',
          'email': 'staff@example.test',
          'role': 'staff',
          'is_deleted': true,
          'email_verified_at': '2026-11-08',
        },
      ];
      final api = AuthApi(
        client: MockClient((request) async {
          expect(request.headers['Authorization'], 'Bearer admin-test');
          if (request.url.path.endsWith('/summary')) {
            return jsonResponse({
              'data': {
                'pending_review': 2,
                'needs_assignment': 1,
                'pending_confirmation': 3,
                'total_tickets': 6,
              },
            });
          }
          if (request.url.path.endsWith('/users')) {
            return jsonResponse({
              'data': users,
              'total': 3,
              'current_page': 1,
              'last_page': 1,
            });
          }
          return jsonResponse({
            'data': [ticket],
            'current_page': 1,
            'last_page': 1,
          });
        }),
      );
      addTearDown(api.client.close);
      await tester.pumpWidget(const HelpdeskApp());
      final theme = tester.widget<MaterialApp>(find.byType(MaterialApp)).theme!;
      await tester.pumpWidget(
        RepaintBoundary(
          key: const ValueKey('admin-shot'),
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: theme.copyWith(
              textTheme: theme.textTheme.apply(fontFamily: 'Roboto'),
            ),
            home: AdminShell(
              api: api,
              user: const {'id': 1, 'name': 'Quản trị', 'role': 'admin'},
              onLogout: () {},
              onUpdated: (_) {},
              onPasswordChanged: () async {},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Chờ duyệt 2'), findsOneWidget);
      await expectLater(
        find.byKey(const ValueKey('admin-shot')),
        matchesGoldenFile('goldens/admin_overview_390.png'),
      );
      await tester.tap(find.text('Người dùng'));
      await tester.pumpAndSettle();
      expect(find.text('3 tài khoản trong danh sách'), findsOneWidget);
      expect(find.text('Giảng viên kiểm thử'), findsOneWidget);
      await expectLater(
        find.byKey(const ValueKey('admin-shot')),
        matchesGoldenFile('goldens/admin_users_390.png'),
      );
    },
  );
  testWidgets('Admin triage confirms action and reloads approved ticket', (
    tester,
  ) async {
    bool approved = false;
    final api = AuthApi(
      client: MockClient((request) async {
        if (request.method == 'POST') {
          expect(request.url.path, '/api/admin/tickets/8/triage');
          expect(jsonDecode(request.body)['decision'], 'approved');
          approved = true;
          return jsonResponse({'message': 'Đã duyệt phiếu.'});
        }
        return jsonResponse({
          'data': {
            'id': 8,
            'ticket_number': 'HD-8',
            'title': 'Mất mạng',
            'reporter_name': 'Sinh viên',
            'description': 'Không kết nối được',
            'status': 'new',
            'triage_status': approved ? 'approved' : null,
            'attachments': [],
          },
        });
      }),
    );
    addTearDown(api.client.close);
    await tester.pumpWidget(
      MaterialApp(home: AdminReviewPage(api: api, id: 8)),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.widgetWithText(FilledButton, 'Duyệt phiếu'),
      200,
      scrollable: find
          .descendant(
            of: find.byType(ListView),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Duyệt phiếu'));
    await tester.pumpAndSettle();
    expect(approved, false);
    await tester.tap(find.text('Xác nhận'));
    await tester.pumpAndSettle();
    expect(approved, true);
    expect(find.widgetWithText(FilledButton, 'Duyệt phiếu'), findsNothing);
  });
  testWidgets('Admin creates KTV with selected categories', (tester) async {
    bool created = false;
    final api = AuthApi(
      client: MockClient((request) async {
        if (request.method == 'POST') {
          final data = jsonDecode(request.body);
          expect(data['category_ids'], [1]);
          expect(data['email'], 'staff@example.test');
          created = true;
          return jsonResponse({'message': 'Đã tạo'});
        }
        return jsonResponse({
          'data': [
            {'id': 1, 'name': 'Mạng / Wi-Fi'},
          ],
        });
      }),
    );
    addTearDown(api.client.close);
    tester.view.physicalSize = const Size(600, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: StaffEditor(api: api)),
      ),
    );
    await tester.pumpAndSettle();
    final fields = find.byType(TextFormField);
    await tester.enterText(fields.at(0), 'KTV kiểm thử');
    await tester.enterText(fields.at(1), 'staff@example.test');
    await tester.enterText(fields.at(3), 'FixturePass123');
    await tester.tap(find.text('Mạng / Wi-Fi'));
    await tester.pump();
    await tester.ensureVisible(
      find.widgetWithText(FilledButton, 'Tạo tài khoản KTV'),
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Tạo tài khoản KTV'));
    await tester.pumpAndSettle();
    expect(created, true);
  });
}
