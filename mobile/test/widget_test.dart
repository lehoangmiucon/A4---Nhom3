import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:helpdesk_mobile/main.dart';

void main() {
  testWidgets('Lecturer registration hides student code; switching back restores it', (tester) async {
    await tester.pumpWidget(const HelpdeskApp());
    final register = find.widgetWithText(FilledButton, 'Đăng ký tài khoản');
    await tester.ensureVisible(register);
    await tester.tap(register); await tester.pumpAndSettle();
    final role = find.byType(DropdownButtonFormField<String>);
    await tester.ensureVisible(role); await tester.tap(role); await tester.pumpAndSettle();
    await tester.tap(find.text('Giảng viên').last); await tester.pumpAndSettle();
    expect(find.text('Tạo tài khoản giảng viên'), findsOneWidget);
    expect(find.text('Mã sinh viên'), findsNothing);
    await tester.tap(role); await tester.pumpAndSettle();
    await tester.tap(find.text('Sinh viên').last); await tester.pumpAndSettle();
    expect(find.text('Mã sinh viên'), findsOneWidget);
  });
  testWidgets('Login validates missing credentials', (tester) async {
    await tester.pumpWidget(const HelpdeskApp());
    final button = find.widgetWithText(FilledButton, 'Đăng nhập');
    await tester.ensureVisible(button);
    await tester.tap(button);
    await tester.pump();
    expect(find.text('Vui lòng nhập Email hoặc mã sinh viên.'), findsOneWidget);
    expect(find.text('Nhập mật khẩu của bạn.'), findsOneWidget);
  });
  testWidgets('Registration has member fields and password confirmation', (
    tester,
  ) async {
    await tester.pumpWidget(const HelpdeskApp());
    final button = find.widgetWithText(FilledButton, 'Đăng ký tài khoản');
    await tester.ensureVisible(button);
    await tester.tap(button);
    await tester.pumpAndSettle();
    expect(find.text('Tạo tài khoản sinh viên'), findsOneWidget);
    expect(find.text('Mã sinh viên'), findsOneWidget);
    final submit = find.widgetWithText(FilledButton, 'Tiếp tục');
    await tester.ensureVisible(submit);
    await tester.tap(submit);
    await tester.pump();
    expect(find.text('Mật khẩu xác nhận chưa khớp.'), findsOneWidget);
  });
}
