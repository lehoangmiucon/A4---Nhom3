import 'package:flutter_test/flutter_test.dart';

import 'package:apphelpdesk/main.dart';
import 'package:apphelpdesk/screens.dart';
import 'package:apphelpdesk/services/api_client.dart';
import 'package:apphelpdesk/services/app_state.dart';

void main() {
  testWidgets('shows Helpdesk sign-in screen', (WidgetTester tester) async {
    final state = AppState(ApiClient());
    AppScope.current = state;

    await tester.pumpWidget(HelpdeskApp(state: state));

    expect(find.textContaining('CAMPUS SUPPORT'), findsOneWidget);
    expect(find.text('Đăng nhập'), findsNWidgets(2));
  });
}
