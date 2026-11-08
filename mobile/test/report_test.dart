import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:helpdesk_mobile/auth_api.dart';
import 'package:helpdesk_mobile/customer_pages.dart';

void main() {
  test(
    'QR reads existing room codes and links without opening arbitrary URLs',
    () {
      expect(roomCodeFromQr('A201'), 'A201');
      expect(
        roomCodeFromQr('https://school.test/helpdesk/public/r/ROOM-ABC'),
        'ROOM-ABC',
      );
      expect(roomCodeFromQr('https://school.test/report/A201'), 'A201');
      expect(
        roomCodeFromQr('https://school.test/customer/portal?room_code=A201'),
        'A201',
      );
      expect(roomCodeFromQr('https://unrelated.test/anything'), isNull);
      expect(roomCodeFromQr('javascript:alert(1)'), isNull);
    },
  );
  testWidgets(
    'Direct report opens step one; scanned report automatically confirms room',
    (tester) async {
      final room = {
        'id': 2,
        'building_id': 1,
        'code': 'A201',
        'name': 'Phòng A201',
        'building': {'name': 'Tòa A'},
      };
      final api = AuthApi(
        client: MockClient(
          (request) async => http.Response(
            jsonEncode({
              'data': request.url.path.endsWith('/A201')
                  ? room
                  : request.url.path.endsWith('/rooms')
                  ? [room]
                  : [
                      {'id': 3, 'name': 'Máy chiếu'},
                    ],
            }),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          ),
        ),
      );
      addTearDown(api.client.close);
      await tester.pumpWidget(MaterialApp(home: ReportFlow(api: api)));
      await tester.pumpAndSettle();
      expect(find.text('Báo sự cố (1/2)'), findsOneWidget);
      expect(find.text('Kiểm tra phòng'), findsNothing);
      await tester.pumpWidget(const SizedBox());
      await tester.pumpWidget(
        MaterialApp(
          home: ReportFlow(api: api, scannedCode: 'A201'),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Xác nhận vị trí'), findsOneWidget);
      expect(find.text('Tòa A'), findsOneWidget);
      expect(find.text('Phòng A201 · A201'), findsOneWidget);
      expect(find.byType(TextField), findsNothing);
      await tester.tap(find.text('Tạo báo cáo sự cố'));
      await tester.pumpAndSettle();
      expect(find.text('Báo sự cố (1/2)'), findsOneWidget);
      expect(find.text('Tòa A'), findsOneWidget);
    },
  );
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(
    () => FlutterSecureStorage.setMockInitialValues({
      'access_token': 'test-token',
    }),
  );
  testWidgets(
    'Manual room to real ticket success retains draft between steps',
    (tester) async {
      tester.view.physicalSize = const Size(600, 1200);
      tester.view.devicePixelRatio = 1;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
      });
      final room = {
        'id': 2,
        'building_id': 1,
        'code': 'A201',
        'name': 'Phòng A201',
        'building': {'id': 1, 'name': 'Tòa A'},
      };
      int sent = 0;
      final api = AuthApi(
        client: MockClient((request) async {
          Object data;
          int status = 200;
          if (request.method == 'POST') {
            sent++;
            expect(request.headers['Authorization'], 'Bearer test-token');
            expect(request.body, contains('Máy chiếu hỏng'));
            expect(request.body, contains('Không lên hình'));
            data = {
              'data': {
                'id': 9,
                'ticket_number': 'HD-REAL-9',
                'status': 'new',
                'room_label': 'A201',
                'building_label': 'Tòa A',
              },
            };
            status = 201;
          } else if (request.url.path.endsWith('categories')) {
            data = {
              'data': [
                {'id': 3, 'name': 'Máy chiếu'},
              ],
            };
          } else if (request.url.path.endsWith('/A201')) {
            data = {'data': room};
          } else {
            data = {
              'data': [room],
            };
          }
          return http.Response(
            jsonEncode(data),
            status,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }),
      );
      addTearDown(api.client.close);
      await tester.pumpWidget(
        MaterialApp(home: ReportFlow(api: api, manualLookup: true)),
      );
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).first, 'A201');
      await tester.tap(find.text('Kiểm tra phòng'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Tạo báo cáo sự cố'));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<int>).last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Máy chiếu').last);
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextFormField).last, 'Máy chiếu hỏng');
      await tester.tap(find.text('Tiếp tục'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextFormField).first,
        'Không lên hình',
      );
      await tester.tap(find.byIcon(Icons.arrow_back));
      await tester.pumpAndSettle();
      expect(find.text('Máy chiếu hỏng'), findsOneWidget);
      await tester.tap(find.text('Tiếp tục'));
      await tester.pumpAndSettle();
      expect(find.text('Không lên hình'), findsOneWidget);
      await tester.tap(find.text('Gửi phiếu sự cố'));
      await tester.pumpAndSettle();
      expect(find.text('Gửi báo cáo thành công'), findsOneWidget);
      expect(find.text('Mã: HD-REAL-9'), findsOneWidget);
      expect(sent, 1);
    },
  );
}
