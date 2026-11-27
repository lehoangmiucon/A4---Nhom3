import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:apphelpdesk/screens.dart';
import 'package:apphelpdesk/services/api_client.dart';

void main() {
  testWidgets(
    'avatar uses the backend asset URL and updates after profile changes',
    (tester) async {
      final api = ApiClient();
      final publicRoot = Uri.parse(
        api.baseUrl.replaceFirst(RegExp(r'/api$'), ''),
      );
      final serverRoot = publicRoot.replace(host: 'localhost');
      Future<void> show(String path) => tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: UserAvatar(
              user: {'name': 'Test', 'avatar_url': path},
              api: api,
            ),
          ),
        ),
      );
      await show('$serverRoot/storage/avatars/old.jpg');
      var image = tester.widget<Image>(find.byType(Image));
      expect(
        (image.image as NetworkImage).url,
        '$publicRoot/storage/avatars/old.jpg',
      );
      await show('$serverRoot/storage/avatars/new.jpg');
      image = tester.widget<Image>(find.byType(Image));
      expect((image.image as NetworkImage).url, endsWith('/avatars/new.jpg'));
      await tester.pumpAndSettle();
      expect(find.text('T'), findsOneWidget);
    },
  );

  testWidgets('missing avatar and empty name have a safe fallback', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: UserAvatar(user: const {'name': ''}, api: ApiClient()),
      ),
    );
    expect(find.text('?'), findsOneWidget);
    expect(find.byType(Image), findsNothing);
  });
}
