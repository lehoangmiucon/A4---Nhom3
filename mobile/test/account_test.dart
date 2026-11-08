import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:helpdesk_mobile/auth_api.dart';
import 'package:helpdesk_mobile/account_pages.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(
    () => FlutterSecureStorage.setMockInitialValues({'access_token': 'test'}),
  );
  test('Member labels reflect account role', () {
    expect(
      memberLabel({'role': 'student', 'member_type': 'lecturer'}),
      'Giảng viên',
    );
    expect(
      memberLabel({'role': 'student', 'member_type': 'student'}),
      'Sinh viên',
    );
    expect(memberLabel({'role': 'staff'}), 'Kỹ thuật viên');
  });
  testWidgets(
    'Lecturer account hides student code; staff hides password security',
    (tester) async {
      Map<String, dynamic> profile = {
        'id': 1,
        'name': 'Giảng viên',
        'email': 'gv@ut.edu.vn',
        'role': 'student',
        'member_type': 'lecturer',
        'member_code': null,
        'phone': null,
        'email_verified_at': '2026-11-08',
      };
      final api = AuthApi(
        client: MockClient(
          (_) async => http.Response(
            jsonEncode({'data': profile}),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          ),
        ),
      );
      addTearDown(api.client.close);
      Widget view() => MaterialApp(
        home: Scaffold(
          body: AccountTab(
            api: api,
            user: profile,
            onUpdated: (_) {},
            onPasswordChanged: () async {},
            onLogout: () {},
          ),
        ),
      );
      await tester.pumpWidget(view());
      await tester.pumpAndSettle();
      expect(find.text('Mã sinh viên'), findsNothing);
      expect(find.text('Bảo mật và mật khẩu'), findsOneWidget);
      await tester.pumpWidget(const SizedBox());
      profile = {...profile, 'role': 'staff'};
      await tester.pumpWidget(view());
      await tester.pumpAndSettle();
      expect(find.text('Bảo mật và mật khẩu'), findsNothing);
    },
  );
  testWidgets('Profile can save with empty optional phone', (tester) async {
    bool saved = false;
    final user = {
      'id': 1,
      'name': 'Old',
      'email': 'sv@ut.edu.vn',
      'role': 'student',
      'member_code': 'SV123',
    };
    final api = AuthApi(
      client: MockClient((request) async {
        saved = true;
        expect(jsonDecode(request.body), {'name': 'New', 'phone': null, 'member_code': 'SV123'});
        return http.Response(
          jsonEncode({
            'data': {...user, 'name': 'New', 'phone': null},
          }),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      }),
    );
    addTearDown(api.client.close);
    await tester.pumpWidget(
      MaterialApp(
        home: EditProfilePage(api: api, user: user),
      ),
    );
    await tester.enterText(find.byType(TextFormField).first, 'New');
    await tester.ensureVisible(find.text('Lưu thay đổi'));
    await tester.tap(find.text('Lưu thay đổi'));
    await tester.pumpAndSettle();
    expect(saved, true);
  });
}
