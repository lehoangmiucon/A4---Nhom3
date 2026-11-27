import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import 'services/api_client.dart';
import 'services/app_state.dart';

String roleLabel(dynamic role) => switch (role?.toString()) {
  'student' => 'Sinh viên',
  'teacher' => 'Giảng viên',
  'staff' => 'Kỹ thuật viên',
  'admin' => 'Quản trị viên',
  _ => '',
};

class UserAvatar extends StatelessWidget {
  const UserAvatar({
    super.key,
    required this.user,
    required this.api,
    this.radius = 20,
    this.preview,
  });

  final Map<String, dynamic> user;
  final ApiClient api;
  final double radius;
  final XFile? preview;

  @override
  Widget build(BuildContext context) {
    final name = user['name']?.toString().trim() ?? '';
    final initial = name.isEmpty ? '?' : name.characters.first.toUpperCase();
    final raw = (user['avatar_url'] ?? user['avatar_path'])?.toString() ?? '';
    final fallback = Center(child: Text(initial));
    final Widget image;
    if (preview != null) {
      image = Image.file(
        File(preview!.path),
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => fallback,
      );
    } else if (raw.isNotEmpty) {
      image = Image.network(
        api.resolveAssetUrl(raw),
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => fallback,
      );
    } else {
      image = fallback;
    }
    return CircleAvatar(
      radius: radius,
      child: ClipOval(
        child: SizedBox(width: radius * 2, height: radius * 2, child: image),
      ),
    );
  }
}

Map<String, dynamic> asMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> asRows(dynamic value) {
  if (value is Map && value['data'] is List) {
    return (value['data'] as List).map(asMap).toList();
  }
  if (value is List) return value.map(asMap).toList();
  return [];
}

int sumCounts(dynamic value) => asMap(value).values.fold<int>(
  0,
  (total, item) => total + (int.tryParse(item.toString()) ?? 0),
);

String readableError(Object error) =>
    error is ApiException ? error.message : error.toString();

String displayDateTime(dynamic value) {
  final raw = value?.toString() ?? '';
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) return raw;
  final date = parsed.toLocal();
  String twoDigits(int part) => part.toString().padLeft(2, '0');
  return '${twoDigits(date.day)}/${twoDigits(date.month)}/${date.year} '
      '${twoDigits(date.hour)}:${twoDigits(date.minute)}';
}

void toast(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

class AppScope {
  static AppState? current;
  static AppState stateOf(BuildContext context) => current!;
}

class AuthPage extends StatefulWidget {
  const AuthPage({super.key, required this.state});
  final AppState state;
  @override
  State<AuthPage> createState() => _AuthPageState();
}

class _AuthPageState extends State<AuthPage> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool busy = false;
  bool showPassword = false;
  bool rememberMe = false;

  Future<void> login() async {
    setState(() => busy = true);
    try {
      await widget.state.login(
        email.text,
        password.text,
        'apphelpdesk-mobile',
        remember: rememberMe,
      );
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> register() async {
    final name = TextEditingController();
    final newEmail = TextEditingController(text: email.text);
    final newPassword = TextEditingController();
    final confirmation = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Đăng ký tài khoản sinh viên'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                decoration: const InputDecoration(labelText: 'Họ và tên'),
              ),
              TextField(
                controller: newEmail,
                decoration: const InputDecoration(labelText: 'Email trường'),
              ),
              TextField(
                controller: newPassword,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Mật khẩu'),
              ),
              TextField(
                controller: confirmation,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Nhập lại mật khẩu',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Gửi OTP'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => busy = true);
    try {
      await widget.state.register({
        'name': name.text.trim(),
        'email': newEmail.text.trim(),
        'password': newPassword.text,
        'password_confirmation': confirmation.text,
      });
      if (mounted) await verifyEmail(newEmail.text.trim());
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> verifyEmail(String address) async {
    final otp = TextEditingController();
    await showDialog<void>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Xác thực email'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Nhập mã 6 số gửi tới ' + address),
            TextField(
              controller: otp,
              maxLength: 6,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Mã OTP'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () async {
              try {
                await widget.state.resendOtp(address);
                if (dialog.mounted) toast(dialog, 'Đã yêu cầu gửi lại mã.');
              } catch (error) {
                if (dialog.mounted) toast(dialog, readableError(error));
              }
            },
            child: const Text('Gửi lại'),
          ),
          FilledButton(
            onPressed: () async {
              try {
                await widget.state.verifyOtp(address, otp.text.trim());
                if (dialog.mounted) Navigator.pop(dialog);
                if (mounted)
                  toast(context, 'Email đã xác thực. Bạn có thể đăng nhập.');
              } catch (error) {
                if (dialog.mounted) toast(dialog, readableError(error));
              }
            },
            child: const Text('Xác thực'),
          ),
        ],
      ),
    );
  }

  Future<void> resetPassword() async {
    final address = TextEditingController(text: email.text);
    final otp = TextEditingController();
    final next = TextEditingController();
    final confirm = TextEditingController();
    var sent = false;
    await showDialog<void>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Quên mật khẩu'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: address,
                  decoration: const InputDecoration(labelText: 'Email'),
                ),
                if (sent) ...[
                  TextField(
                    controller: otp,
                    maxLength: 6,
                    decoration: const InputDecoration(labelText: 'Mã OTP'),
                  ),
                  TextField(
                    controller: next,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Mật khẩu mới',
                    ),
                  ),
                  TextField(
                    controller: confirm,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Nhập lại mật khẩu',
                    ),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog),
              child: const Text('Đóng'),
            ),
            FilledButton(
              onPressed: () async {
                try {
                  if (!sent) {
                    await widget.state.forgotPassword(address.text);
                    update(() => sent = true);
                    if (dialog.mounted)
                      toast(dialog, 'Nếu email hợp lệ, OTP sẽ được gửi.');
                  } else {
                    await widget.state.resetPassword(
                      address.text,
                      otp.text,
                      next.text,
                      confirm.text,
                    );
                    if (dialog.mounted) Navigator.pop(dialog);
                    if (mounted)
                      toast(
                        this.context,
                        'Đã đổi mật khẩu. Hãy đăng nhập lại.',
                      );
                  }
                } catch (error) {
                  if (dialog.mounted) toast(dialog, readableError(error));
                }
              },
              child: Text(sent ? 'Đặt lại mật khẩu' : 'Gửi OTP'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final isLight = Theme.of(context).brightness == Brightness.light;
    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: isLight
                ? [
                    const Color(0xFFE7F4FC),
                    const Color(0xFFF4F8FB),
                    const Color(0xFFEDF7F4),
                  ]
                : [
                    const Color(0xFF10243C),
                    const Color(0xFF174D72),
                    const Color(0xFF116982),
                  ],
          ),
        ),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) => SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 430),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Container(
                        width: 68,
                        height: 68,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFF21A1E4), Color(0xFF126EB0)],
                          ),
                          borderRadius: BorderRadius.circular(22),
                          boxShadow: [
                            BoxShadow(
                              color: colors.primary.withValues(alpha: .24),
                              blurRadius: 28,
                              offset: Offset(0, 10),
                            ),
                          ],
                        ),
                        child: const Icon(
                          Icons.support_agent_rounded,
                          size: 36,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 24),
                      Text(
                        'HELPDESK \u{00b7} CAMPUS SUPPORT',
                        style: TextStyle(
                          color: colors.primary,
                          fontSize: 11,
                          letterSpacing: 1.7,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 9),
                      Text(
                        '\u{0110}\u{0103}ng nh\u{1ead}p',
                        style: Theme.of(context).textTheme.headlineMedium
                            ?.copyWith(
                              color: colors.onSurface,
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.5,
                            ),
                      ),
                      const SizedBox(height: 25),
                      Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: colors.surface.withValues(alpha: .94),
                          borderRadius: BorderRadius.circular(24),
                          border: Border.all(color: colors.outlineVariant),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x33000000),
                              blurRadius: 30,
                              offset: Offset(0, 14),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            TextField(
                              controller: email,
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              decoration: const InputDecoration(
                                labelText: 'Email',
                                prefixIcon: Icon(Icons.alternate_email_rounded),
                              ),
                            ),
                            const SizedBox(height: 13),
                            TextField(
                              controller: password,
                              obscureText: !showPassword,
                              onSubmitted: (_) => login(),
                              decoration: InputDecoration(
                                labelText: 'M\u{1ead}t kh\u{1ea9}u',
                                prefixIcon: const Icon(
                                  Icons.lock_outline_rounded,
                                ),
                                suffixIcon: IconButton(
                                  onPressed: () => setState(
                                    () => showPassword = !showPassword,
                                  ),
                                  icon: Icon(
                                    showPassword
                                        ? Icons.visibility_off_rounded
                                        : Icons.visibility_rounded,
                                  ),
                                ),
                              ),
                            ),
                            Material(
                              type: MaterialType.transparency,
                              child: CheckboxListTile(
                                contentPadding: EdgeInsets.zero,
                                visualDensity: VisualDensity.compact,
                                controlAffinity:
                                    ListTileControlAffinity.leading,
                                value: rememberMe,
                                onChanged: busy
                                    ? null
                                    : (value) => setState(
                                        () => rememberMe = value ?? false,
                                      ),
                                title: const Text('Ghi nhớ đăng nhập'),
                              ),
                            ),
                            Align(
                              alignment: Alignment.centerRight,
                              child: TextButton(
                                onPressed: resetPassword,
                                child: const Text(
                                  'Qu\u{00ea}n m\u{1ead}t kh\u{1ea9}u?',
                                ),
                              ),
                            ),
                            const SizedBox(height: 4),
                            FilledButton.icon(
                              onPressed: busy ? null : login,
                              icon: busy
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(
                                      Icons.arrow_forward_rounded,
                                      size: 19,
                                    ),
                              label: Text(
                                busy
                                    ? '\u{0110}ang \u{0111}\u{0103}ng nh\u{1ead}p...'
                                    : '\u{0110}\u{0103}ng nh\u{1ead}p',
                              ),
                            ),
                            const SizedBox(height: 14),
                            OutlinedButton.icon(
                              onPressed: busy ? null : register,
                              icon: const Icon(
                                Icons.person_add_alt_1_rounded,
                                size: 19,
                              ),
                              label: const Text(
                                '\u{0110}\u{0103}ng k\u{00fd} sinh vi\u{00ea}n',
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 15),
                      const SizedBox(height: 10),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.verified_user_outlined,
                            size: 14,
                            color: colors.onSurfaceVariant,
                          ),
                          SizedBox(width: 7),
                          Flexible(
                            child: Text(
                              'H\u{1ed7} tr\u{1ee3} k\u{1ef9} thu\u{1ead}t trong khu\u{00f4}n vi\u{00ea}n tr\u{01b0}\u{1edd}ng',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: colors.onSurfaceVariant,
                                fontSize: 11,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class AppShell extends StatefulWidget {
  const AppShell({super.key, required this.state});
  final AppState state;
  @override
  State<AppShell> createState() => _AppShellState();
}

class ChatPage extends StatefulWidget {
  const ChatPage({super.key});

  @override
  State<ChatPage> createState() => _ChatPageState();
}

class _ChatPageState extends State<ChatPage> {
  final search = TextEditingController();
  final assistantDraft = TextEditingController();
  List<Map<String, dynamic>> conversations = [];
  List<Map<String, dynamic>> contacts = [];
  bool loading = true;
  bool searching = false;
  bool sendingDraft = false;
  String? error;
  Timer? searchTimer;
  Timer? conversationPollTimer;
  bool refreshingConversations = false;

  ApiClient get api => AppScope.stateOf(context).api;

  @override
  void initState() {
    super.initState();
    loadConversations();
    conversationPollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!loading) loadConversations();
    });
    search.addListener(onSearchChanged);
  }

  @override
  void dispose() {
    searchTimer?.cancel();
    conversationPollTimer?.cancel();
    search.removeListener(onSearchChanged);
    search.dispose();
    assistantDraft.dispose();
    super.dispose();
  }

  Future<void> loadConversations() async {
    if (refreshingConversations) return;
    refreshingConversations = true;
    try {
      final result = await api.get('/chat/conversations');
      if (!mounted) return;
      setState(() {
        conversations = asRows(result);
        loading = false;
        error = null;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        loading = false;
        error = readableError(exception);
      });
    } finally {
      refreshingConversations = false;
    }
  }

  void onSearchChanged() {
    searchTimer?.cancel();
    final value = search.text.trim();
    final canSearchContacts = [
      'admin',
      'staff',
    ].contains(AppScope.stateOf(context).user?['role']?.toString());
    if (!canSearchContacts) {
      setState(() => contacts = []);
      return;
    }
    if (value.length < 2) {
      setState(() => contacts = []);
      return;
    }
    searchTimer = Timer(const Duration(milliseconds: 350), () async {
      setState(() => searching = true);
      try {
        final result = await api.get('/chat/contacts', query: {'q': value});
        if (mounted && search.text.trim() == value) {
          setState(() => contacts = asRows(result));
        }
      } catch (exception) {
        if (mounted) toast(context, readableError(exception));
      } finally {
        if (mounted) setState(() => searching = false);
      }
    });
  }

  Future<void> startAssistant() async {
    try {
      final result = asMap(await api.post('/chat/assistant'));
      final conversation = asMap(result['data']);
      if (conversation.isNotEmpty && mounted)
        await openConversation(conversation);
    } catch (exception) {
      if (mounted) toast(context, readableError(exception));
    }
  }

  Future<void> sendAssistantPrompt() async {
    final message = assistantDraft.text.trim();
    if (message.isEmpty || sendingDraft) return;
    setState(() => sendingDraft = true);
    try {
      final result = asMap(await api.post('/chat/assistant'));
      final conversation = asMap(result['data']);
      if (conversation.isEmpty || !mounted) return;
      assistantDraft.clear();
      await openConversation(conversation, initialMessage: message);
    } catch (exception) {
      if (mounted) toast(context, readableError(exception));
    } finally {
      if (mounted) setState(() => sendingDraft = false);
    }
  }

  Future<void> startDirect(Map<String, dynamic> contact) async {
    try {
      final result = asMap(
        await api.post('/chat/conversations', body: {'user_id': contact['id']}),
      );
      search.clear();
      final conversation = asMap(result['data']);
      if (conversation.isNotEmpty && mounted)
        await openConversation(conversation);
    } catch (exception) {
      if (mounted) toast(context, readableError(exception));
    }
  }

  Future<void> openConversation(
    Map<String, dynamic> conversation, {
    String? initialMessage,
  }) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (_) => ChatConversationPage(
          conversation: conversation,
          initialMessage: initialMessage,
        ),
      ),
    );
    if (mounted) loadConversations();
  }

  String subtitle(Map<String, dynamic> conversation) {
    final ticket = asMap(conversation['ticket']);
    if (ticket.isNotEmpty) return 'Phiếu ${ticket['ticket_number'] ?? ''}';
    return conversation['type'] == 'assistant'
        ? 'Trợ lý AI'
        : 'Trò chuyện riêng';
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final canSearch = [
      'admin',
      'staff',
    ].contains(AppScope.stateOf(context).user?['role']?.toString());
    final searchQuery = search.text.trim().toLowerCase();
    final searchingForContact = canSearch && searchQuery.length >= 2;
    final visibleConversations = canSearch || searchQuery.isEmpty
        ? conversations
        : conversations.where((conversation) {
            final ticket = asMap(conversation['ticket']);
            final last = asMap(conversation['last_message']);
            final text = [
              conversation['title'],
              last['body'],
              ticket['title'],
              ticket['ticket_number'],
            ].whereType<Object>().join(' ').toLowerCase();
            return text.contains(searchQuery);
          }).toList();
    return Scaffold(
      appBar: AppBar(
        title: const Text('Tin nhắn'),
        actions: [
          IconButton(
            tooltip: 'Tải lại',
            onPressed: loading ? null : loadConversations,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Column(
              children: [
                InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: startAssistant,
                  child: Ink(
                    height: 50,
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    decoration: BoxDecoration(
                      color: colors.primaryContainer,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.auto_awesome_rounded, color: colors.primary),
                        const SizedBox(width: 10),
                        const Expanded(child: Text('Trợ lý AI')),
                        Icon(
                          Icons.chevron_right_rounded,
                          color: colors.primary,
                        ),
                      ],
                    ),
                  ),
                ),
                if (true) ...[
                  const SizedBox(height: 10),
                  TextField(
                    controller: search,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(
                      hintText: (canSearch
                          ? 'Tìm email hoặc số điện thoại'
                          : 'Tìm cuộc trò chuyện'),
                      prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: searching
                          ? const Padding(
                              padding: EdgeInsets.all(14),
                              child: SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              ),
                            )
                          : null,
                      isDense: true,
                      filled: true,
                      contentPadding: const EdgeInsets.symmetric(vertical: 11),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (searchingForContact && contacts.isNotEmpty)
            const Padding(
              padding: EdgeInsets.fromLTRB(20, 2, 20, 4),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text('Kết quả'),
              ),
            ),
          if (searchingForContact)
            Expanded(
              child: contacts.isEmpty && !searching
                  ? const Center(child: Text('Không tìm thấy người dùng'))
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(12, 2, 12, 12),
                      itemCount: contacts.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 2),
                      itemBuilder: (context, index) {
                        final contact = contacts[index];
                        final name =
                            contact['name']?.toString() ?? 'Người dùng';
                        return Card(
                          margin: EdgeInsets.zero,
                          child: ListTile(
                            dense: true,
                            visualDensity: VisualDensity.compact,
                            leading: UserAvatar(
                              user: contact,
                              api: api,
                              radius: 19,
                            ),
                            title: Text(
                              name,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            subtitle: Text(
                              contact['email']?.toString() ??
                                  contact['phone']?.toString() ??
                                  '',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            trailing: Text(
                              roleLabel(contact['role']),
                              style: TextStyle(
                                color: colors.primary,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            onTap: () => startDirect(contact),
                          ),
                        );
                      },
                    ),
            )
          else
            Expanded(
              child: RefreshIndicator(
                onRefresh: loadConversations,
                child: loading
                    ? ListView(
                        children: const [
                          SizedBox(height: 210),
                          Center(child: CircularProgressIndicator()),
                        ],
                      )
                    : error != null
                    ? ListView(
                        children: [
                          const SizedBox(height: 90),
                          Center(
                            child: Text(error!, textAlign: TextAlign.center),
                          ),
                          Center(
                            child: TextButton.icon(
                              onPressed: loadConversations,
                              icon: const Icon(Icons.refresh_rounded),
                              label: const Text('Thử lại'),
                            ),
                          ),
                        ],
                      )
                    : conversations.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 120),
                          Center(child: Icon(Icons.forum_outlined, size: 36)),
                          SizedBox(height: 8),
                          Center(child: Text('Chưa có tin nhắn')),
                        ],
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(12, 4, 12, 18),
                        itemCount: visibleConversations.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 3),
                        itemBuilder: (context, index) {
                          final conversation = visibleConversations[index];
                          final last = asMap(conversation['last_message']);
                          final unreadCount =
                              int.tryParse(
                                conversation['unread_count']?.toString() ?? '',
                              ) ??
                              0;
                          final title =
                              conversation['title']?.toString() ?? 'Tin nhắn';
                          return Card(
                            margin: EdgeInsets.zero,
                            clipBehavior: Clip.antiAlias,
                            color: unreadCount > 0
                                ? colors.errorContainer.withValues(alpha: .18)
                                : colors.surface,
                            child: ListTile(
                              dense: true,
                              visualDensity: VisualDensity.compact,
                              leading: conversation['type'] == 'direct'
                                  ? UserAvatar(
                                      user:
                                          asRows(conversation['participants'])
                                              .firstOrNull ??
                                          {'name': title},
                                      api: api,
                                    )
                                  : CircleAvatar(
                                      radius: 20,
                                      backgroundColor:
                                          colors.secondaryContainer,
                                      child: Icon(
                                        conversation['type'] == 'assistant'
                                            ? Icons.auto_awesome_rounded
                                            : conversation['type'] == 'ticket'
                                            ? Icons.confirmation_number_outlined
                                            : Icons.person_outline_rounded,
                                        size: 20,
                                      ),
                                    ),
                              title: Text(
                                title,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontWeight: unreadCount > 0
                                      ? FontWeight.w800
                                      : FontWeight.w500,
                                ),
                              ),
                              subtitle: Text(
                                last['body']?.toString() ??
                                    subtitle(conversation),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontWeight: unreadCount > 0
                                      ? FontWeight.w600
                                      : FontWeight.w400,
                                  color: unreadCount > 0
                                      ? colors.onSurface
                                      : colors.onSurfaceVariant,
                                ),
                              ),
                              trailing: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  if (unreadCount > 0)
                                    Container(
                                      constraints: const BoxConstraints(
                                        minWidth: 22,
                                        minHeight: 22,
                                      ),
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 6,
                                      ),
                                      alignment: Alignment.center,
                                      decoration: BoxDecoration(
                                        color: colors.error,
                                        borderRadius: BorderRadius.circular(20),
                                      ),
                                      child: Text(
                                        unreadCount > 99
                                            ? '99+'
                                            : '$unreadCount',
                                        style: const TextStyle(
                                          color: Colors.white,
                                          fontSize: 10,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ),
                                  const SizedBox(width: 5),
                                  const Icon(
                                    Icons.chevron_right_rounded,
                                    size: 20,
                                  ),
                                ],
                              ),
                              onTap: () => openConversation(conversation),
                            ),
                          );
                        },
                      ),
              ),
            ),
          if (!searchingForContact)
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
                child: Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: assistantDraft,
                        minLines: 1,
                        maxLines: 3,
                        textInputAction: TextInputAction.send,
                        onSubmitted: (_) => sendAssistantPrompt(),
                        decoration: InputDecoration(
                          hintText: 'Nhắn Trợ lý Helpdesk...',
                          prefixIcon: const Icon(Icons.auto_awesome_rounded),
                          filled: true,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(22),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filled(
                      tooltip: 'Gửi tin nhắn',
                      onPressed: sendingDraft ? null : sendAssistantPrompt,
                      icon: sendingDraft
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.send_rounded),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class ChatConversationPage extends StatefulWidget {
  const ChatConversationPage({
    super.key,
    required this.conversation,
    this.initialMessage,
  });
  final Map<String, dynamic> conversation;
  final String? initialMessage;

  @override
  State<ChatConversationPage> createState() => _ChatConversationPageState();
}

class _ChatConversationPageState extends State<ChatConversationPage> {
  final input = TextEditingController();
  final scroll = ScrollController();
  final List<Map<String, dynamic>> messages = [];
  Timer? pollTimer;
  bool loading = true;
  bool sending = false;

  ApiClient get api => AppScope.stateOf(context).api;
  int get lastId => messages.isEmpty
      ? 0
      : (int.tryParse(messages.last['id']?.toString() ?? '') ?? 0);

  @override
  void initState() {
    super.initState();
    fetchMessages().then((_) {
      if (!mounted || widget.initialMessage == null) return;
      input.text = widget.initialMessage!;
      send();
    });
    pollTimer = Timer.periodic(
      const Duration(seconds: 2),
      (_) => fetchMessages(),
    );
  }

  @override
  void dispose() {
    pollTimer?.cancel();
    input.dispose();
    scroll.dispose();
    super.dispose();
  }

  Future<void> fetchMessages() async {
    try {
      final id = widget.conversation['id'];
      final result = await api.get(
        '/chat/conversations/$id/messages',
        query: lastId > 0 ? {'after_id': lastId} : null,
      );
      final rows = asRows(result);
      if (!mounted) return;
      if (rows.isNotEmpty) {
        setState(() => messages.addAll(rows));
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (scroll.hasClients) {
            scroll.animateTo(
              scroll.position.maxScrollExtent,
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOut,
            );
          }
        });
      }
    } catch (exception) {
      if (mounted && loading) toast(context, readableError(exception));
    } finally {
      if (mounted && loading) setState(() => loading = false);
    }
  }

  Future<void> send() async {
    final body = input.text.trim();
    if (body.isEmpty || sending) return;
    setState(() => sending = true);
    input.clear();
    try {
      final result = asMap(
        await api.post(
          '/chat/conversations/${widget.conversation['id']}/messages',
          body: {'body': body},
        ),
      );
      final sent = asMap(result['data']);
      final assistant = asMap(result['assistant']);
      if (mounted) {
        setState(() {
          if (sent.isNotEmpty) messages.add(sent);
          if (assistant.isNotEmpty) messages.add(assistant);
        });
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (scroll.hasClients) {
            scroll.animateTo(
              scroll.position.maxScrollExtent,
              duration: const Duration(milliseconds: 180),
              curve: Curves.easeOut,
            );
          }
        });
      }
    } catch (exception) {
      input.text = body;
      if (mounted) toast(context, readableError(exception));
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  String get title => widget.conversation['title']?.toString() ?? 'Tin nhắn';

  @override
  Widget build(BuildContext context) {
    final myId = AppScope.stateOf(context).user?['id']?.toString();
    final colors = Theme.of(context).colorScheme;
    final ticket = asMap(widget.conversation['ticket']);
    String messageTime(dynamic value) {
      final date = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
      if (date == null) return '';
      return '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
    }

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            if (widget.conversation['type'] == 'direct') ...[
              UserAvatar(
                user:
                    asRows(widget.conversation['participants']).firstOrNull ??
                    {'name': title},
                api: api,
                radius: 18,
              ),
              const SizedBox(width: 10),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (ticket.isNotEmpty)
                    Text(
                      ticket['ticket_number']?.toString() ?? 'Phiếu hỗ trợ',
                      style: Theme.of(context).textTheme.labelSmall,
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: loading
                ? const Center(child: CircularProgressIndicator())
                : messages.isEmpty
                ? const Center(child: Text('Gửi tin nhắn đầu tiên'))
                : ListView.builder(
                    controller: scroll,
                    padding: const EdgeInsets.all(16),
                    itemCount: messages.length,
                    itemBuilder: (context, index) {
                      final message = messages[index];
                      final type = message['sender_type']?.toString();
                      final own = message['user_id']?.toString() == myId;
                      final system = type == 'system';
                      final assistant = type == 'assistant';
                      final alignment = system
                          ? Alignment.center
                          : own
                          ? Alignment.centerRight
                          : Alignment.centerLeft;
                      final background = system
                          ? colors.surfaceContainerHighest
                          : own
                          ? colors.primary
                          : assistant
                          ? colors.tertiaryContainer
                          : colors.secondaryContainer;
                      final foreground = system
                          ? colors.onSurfaceVariant
                          : own
                          ? colors.onPrimary
                          : assistant
                          ? colors.onTertiaryContainer
                          : colors.onSecondaryContainer;
                      return Align(
                        alignment: alignment,
                        child: Container(
                          constraints: BoxConstraints(
                            maxWidth: MediaQuery.sizeOf(context).width * .78,
                          ),
                          margin: const EdgeInsets.symmetric(vertical: 5),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 10,
                          ),
                          decoration: BoxDecoration(
                            color: background,
                            borderRadius: BorderRadius.circular(18),
                          ),
                          child: Column(
                            crossAxisAlignment: system
                                ? CrossAxisAlignment.center
                                : CrossAxisAlignment.start,
                            children: [
                              if (assistant)
                                Icon(
                                  Icons.auto_awesome_rounded,
                                  size: 14,
                                  color: foreground,
                                ),
                              Text(
                                message['body']?.toString() ?? '',
                                style: TextStyle(color: foreground),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                messageTime(message['created_at']),
                                style: TextStyle(
                                  color: foreground.withValues(alpha: .72),
                                  fontSize: 10,
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: TextField(
                      controller: input,
                      minLines: 1,
                      maxLines: 5,
                      maxLength: 5000,
                      onSubmitted: (_) => send(),
                      decoration: InputDecoration(
                        hintText: 'Nhập tin nhắn...',
                        counterText: '',
                        filled: true,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(22),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 12,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: sending ? null : send,
                    icon: sending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.send_rounded),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AppShellState extends State<AppShell> {
  int index = 0;
  List<_NavItem> get items {
    if (widget.state.role == 'admin')
      return [
        _NavItem('Tổng quan', Icons.dashboard_outlined, const DashboardPage()),
        _NavItem(
          'Phiếu',
          Icons.confirmation_number_outlined,
          const TicketListPage(mode: TicketMode.admin),
        ),
        _NavItem(
          'Lịch',
          Icons.calendar_month_outlined,
          const SchedulePage(isAdmin: true),
        ),
        _NavItem(
          'Quản lý',
          Icons.manage_accounts_outlined,
          const AdminManagePage(),
        ),
        _NavItem(
          'Cài đặt',
          Icons.settings_outlined,
          ProfilePage(state: widget.state),
        ),
      ];
    if (widget.state.role == 'staff')
      return [
        _NavItem('Tổng quan', Icons.dashboard_outlined, const DashboardPage()),
        _NavItem(
          'Công việc',
          Icons.build_outlined,
          const TicketListPage(mode: TicketMode.assigned),
        ),
        _NavItem(
          'Lịch',
          Icons.calendar_month_outlined,
          const SchedulePage(isAdmin: false),
        ),
        _NavItem(
          'Phiếu đã báo',
          Icons.assignment_outlined,
          const TicketListPage(mode: TicketMode.reported),
        ),
        _NavItem(
          'Cài đặt',
          Icons.settings_outlined,
          ProfilePage(state: widget.state),
        ),
      ];
    return [
      _NavItem('Tổng quan', Icons.dashboard_outlined, const DashboardPage()),
      _NavItem(
        'Phiếu của tôi',
        Icons.confirmation_number_outlined,
        const TicketListPage(mode: TicketMode.customer),
      ),
      _NavItem(
        'Báo sự cố',
        Icons.add_circle_outline,
        TicketCreatePage(api: widget.state.api, embedded: true),
      ),
      _NavItem('Tin nhắn', Icons.chat_bubble_outline_rounded, const ChatPage()),
      _NavItem(
        'Cài đặt',
        Icons.settings_outlined,
        ProfilePage(state: widget.state),
      ),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final pages = items;
    final selected = pages[index.clamp(0, pages.length - 1)];
    return Scaffold(
      appBar: AppBar(
        title: Text(
          selected.label,
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        actions: [
          IconButton.filledTonal(
            tooltip: 'Tin nhắn',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const ChatPage()),
            ),
            icon: const Icon(Icons.chat_bubble_outline_rounded),
          ),
          IconButton(
            tooltip: 'Thông báo',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const NotificationsPage()),
            ),
            icon: _NotificationBell(
              count: widget.state.unreadNotificationCount,
            ),
          ),
        ],
      ),
      body: IndexedStack(
        index: index.clamp(0, pages.length - 1),
        children: pages.map((e) => e.page).toList(),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index.clamp(0, pages.length - 1),
        onDestinationSelected: (value) => setState(() => index = value),
        destinations: pages
            .map(
              (e) => NavigationDestination(icon: Icon(e.icon), label: e.label),
            )
            .toList(),
      ),
    );
  }
}

class _NotificationBell extends StatelessWidget {
  const _NotificationBell({required this.count});
  final int count;

  @override
  Widget build(BuildContext context) => Stack(
    clipBehavior: Clip.none,
    children: [
      const Icon(Icons.notifications_none),
      if (count > 0)
        Positioned(
          right: -9,
          top: -7,
          child: Container(
            constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
            padding: const EdgeInsets.symmetric(horizontal: 4),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.error,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: Theme.of(context).colorScheme.surface,
                width: 1.5,
              ),
            ),
            alignment: Alignment.center,
            child: Text(
              count > 99 ? '99+' : '$count',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 10,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ),
    ],
  );
}

class _NavItem {
  const _NavItem(this.label, this.icon, this.page);
  final String label;
  final IconData icon;
  final Widget page;
}

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});
  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  bool loading = true;
  bool showDetails = false;
  String? error;
  Map<String, dynamic> data = {};
  String period = 'month';

  String get endpoint {
    final role = AppScope.stateOf(context).role;
    if (role == 'admin') return '/admin/dashboard';
    if (role == 'staff') return '/staff/dashboard';
    return '/customer/dashboard';
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = asMap(
        await AppScope.stateOf(context).api
            .get(endpoint, query: {'period': period}),
      );
      data = asMap(result['data']);
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) {
    final counts = asMap(data['counts']);
    final labels = asMap(data['status_labels']);
    final user = AppScope.stateOf(context).user ?? {};
    final role = AppScope.stateOf(context).role;
    final now = DateTime.now();
    final periodTitle = switch (period) {
      'day' => 'H\u{00f4}m nay',
      'week' => 'Tu\u{1ea7}n n\u{00e0}y',
      'month' => 'Th\u{00e1}ng ${now.month}/${now.year}',
      'year' => 'N\u{0103}m ${now.year}',
      _ => 'T\u{1ed5}ng quan',
    };
    final periodShort = switch (period) {
      'day' => 'h\u{00f4}m nay',
      'week' => 'tu\u{1ea7}n n\u{00e0}y',
      'month' => 'th\u{00e1}ng n\u{00e0}y',
      'year' => 'n\u{0103}m nay',
      _ => 'trong k\u{1ef3}',
    };
    final extraMetric = role == 'admin'
        ? MapEntry<String, dynamic>(
            'Báo cáo giáo viên',
            data['teacher_reports'] ?? 0,
          )
        : role == 'staff'
        ? MapEntry<String, dynamic>(
            'Lịch chờ xác nhận',
            data['pending_acknowledgements'] ?? 0,
          )
        : null;
    final colors = Theme.of(context).colorScheme;
    final isLight = Theme.of(context).brightness == Brightness.light;
    return RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 22),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: isLight
                    ? [
                        const Color(0xFFE7F4FC),
                        const Color(0xFFF4F8FB),
                        const Color(0xFFEDF7F4),
                      ]
                    : [
                        const Color(0xFF10243C),
                        const Color(0xFF174D72),
                        const Color(0xFF116982),
                      ],
              ),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: colors.outlineVariant),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        role == 'admin'
                            ? 'B\u{1ea2}NG \u{0110}I\u{1ec0}U H\u{00c0}NH'
                            : role == 'staff'
                            ? 'KH\u{00d4}NG GIAN C\u{00d4}NG VI\u{1ec6}C'
                            : 'TRUNG T\u{00c2}M H\u{1ed6} TR\u{1ee2}',
                        style: TextStyle(
                          color: colors.primary,
                          fontSize: 10,
                          letterSpacing: 1.35,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 9),
                      Text(
                        'Xin ch\u{00e0}o, ${user['name']?.toString() ?? ''}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: colors.onSurface,
                          fontSize: 19,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        periodTitle,
                        style: TextStyle(
                          color: colors.onSurfaceVariant,
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: colors.primary.withValues(alpha: .13),
                    borderRadius: BorderRadius.circular(15),
                  ),
                  child: Icon(
                    Icons.space_dashboard_rounded,
                    color: colors.primary,
                    size: 24,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 17),
          DropdownButtonFormField<String>(
            initialValue: period,
            decoration: const InputDecoration(
              labelText: 'K\u{1ef3} th\u{1ed1}ng k\u{00ea}',
              prefixIcon: Icon(Icons.date_range_rounded),
            ),
            items: const [
              DropdownMenuItem(value: 'day', child: Text('H\u{00f4}m nay')),
              DropdownMenuItem(
                value: 'week',
                child: Text('Tu\u{1ea7}n n\u{00e0}y'),
              ),
              DropdownMenuItem(
                value: 'month',
                child: Text('Th\u{00e1}ng n\u{00e0}y'),
              ),
              DropdownMenuItem(value: 'year', child: Text('N\u{0103}m nay')),
            ],
            onChanged: (value) {
              if (value != null) {
                setState(() => period = value);
                load();
              }
            },
          ),
          if (loading)
            const Padding(
              padding: EdgeInsets.only(top: 14),
              child: LinearProgressIndicator(minHeight: 3),
            ),
          if (error != null) ...[
            const SizedBox(height: 12),
            _ErrorCard(message: error!, retry: load),
          ],
          if (data.isNotEmpty) ...[
            const SizedBox(height: 14),
            const _SectionHeading(
              title: 'Ch\u{1ec9} s\u{1ed1} ho\u{1ea1}t \u{0111}\u{1ed9}ng',
              icon: Icons.insights_rounded,
            ),
            const SizedBox(height: 6),
            _MetricGrid(
              total: double.tryParse((data['total'] ?? 0).toString()) ?? 0,
              values: {
                'Phi\u{1ebf}u $periodShort': data['total'] ?? 0,
                'Ch\u{1edd} ph\u{00e2}n c\u{00f4}ng': data['unassigned'] ?? 0,
                'Ch\u{1edd} x\u{00e1}c nh\u{1ead}n':
                    data['awaiting_confirmation'] ?? counts['resolved'] ?? 0,
                '\u{0110}ang x\u{1eed} l\u{00fd}':
                    data['in_progress'] ?? counts['in_progress'] ?? 0,
                if (role == 'admin')
                  'B\u{00e1}o c\u{00e1}o gi\u{00e1}o vi\u{00ea}n':
                      data['teacher_reports'] ?? 0,
                if (role == 'staff')
                  'L\u{1ecb}ch ch\u{1edd} x\u{00e1}c nh\u{1ead}n':
                      data['pending_acknowledgements'] ?? 0,
              },
            ),
            if (counts.isNotEmpty ||
                (data['today_schedules'] is List &&
                    asRows(data['today_schedules']).isNotEmpty) ||
                (data['categories'] is List &&
                    asRows(data['categories']).isNotEmpty) ||
                (data['buildings'] is List &&
                    asRows(data['buildings']).isNotEmpty) ||
                extraMetric != null) ...[
              const SizedBox(height: 4),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: () => setState(() => showDetails = !showDetails),
                  icon: Icon(
                    showDetails
                        ? Icons.expand_less_rounded
                        : Icons.expand_more_rounded,
                  ),
                  label: Text(
                    showDetails
                        ? 'Ẩn thống kê chi tiết'
                        : 'Xem thống kê chi tiết',
                  ),
                ),
              ),
            ],
            if (showDetails && counts.isNotEmpty) ...[
              const SizedBox(height: 14),
              const _SectionHeading(
                title: 'T\u{00ec}nh tr\u{1ea1}ng phi\u{1ebf}u',
                icon: Icons.donut_small_rounded,
              ),
              const SizedBox(height: 4),
              ...counts.entries.map(
                (entry) => Card(
                  child: ListTile(
                    leading: Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: colors.primaryContainer,
                        borderRadius: BorderRadius.circular(13),
                      ),
                      child: Icon(
                        _statusIcon(entry.key),
                        color: colors.onPrimaryContainer,
                        size: 19,
                      ),
                    ),
                    title: Text(
                      labels[entry.key]?.toString() ?? entry.key,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    trailing: Text(
                      entry.value.toString(),
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                        color: colors.onSurface,
                      ),
                    ),
                    dense: true,
                  ),
                ),
              ),
            ],
            if (showDetails &&
                data['today_schedules'] is List &&
                asRows(data['today_schedules']).isNotEmpty) ...[
              const SizedBox(height: 14),
              const _SectionHeading(
                title: 'L\u{1ecb}ch h\u{00f4}m nay',
                icon: Icons.event_available_rounded,
              ),
              ...asRows(data['today_schedules'])
                  .map((row) => _ScheduleCard(schedule: row)),
            ],
            if (showDetails &&
                data['categories'] is List &&
                asRows(data['categories']).isNotEmpty) ...[
              const SizedBox(height: 14),
              const _SectionHeading(
                title: 'Theo danh m\u{1ee5}c',
                icon: Icons.category_outlined,
              ),
              ...asRows(data['categories']).map(
                (row) => Card(
                  child: ListTile(
                    title: Text(row['name']?.toString() ?? ''),
                    trailing: Text(
                      row['tickets_count']?.toString() ?? '0',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: colors.primary,
                      ),
                    ),
                  ),
                ),
              ),
            ],
            if (showDetails &&
                data['buildings'] is List &&
                asRows(data['buildings']).isNotEmpty) ...[
              const SizedBox(height: 14),
              const _SectionHeading(
                title: 'Phi\u{1ebf}u theo t\u{00f2}a nh\u{00e0}',
                icon: Icons.apartment_rounded,
              ),
              ...asRows(data['buildings']).map(
                (building) => Card(
                  child: ListTile(
                    title: Text(building['name']?.toString() ?? ''),
                    trailing: Text(
                      building['open_tickets_count']?.toString() ?? '0',
                    ),
                  ),
                ),
              ),
            ],
            if (data['recent_tickets'] is List &&
                asRows(data['recent_tickets']).isNotEmpty) ...[
              const SizedBox(height: 14),
              const _SectionHeading(
                title: 'Phi\u{1ebf}u g\u{1ea7}n \u{0111}\u{00e2}y',
                icon: Icons.history_rounded,
              ),
              ...asRows(data['recent_tickets'])
                  .take(3)
                  .map((ticket) => _TicketTile(ticket: ticket)),
            ],
          ],
        ],
      ),
    );
  }
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.values, this.total = 0});
  final Map<String, dynamic> values;
  final double total;

  @override
  Widget build(BuildContext context) {
    final entries = values.entries.toList();
    final effectiveTotal = total > 0
        ? total
        : entries.fold<double>(
            0,
            (sum, entry) =>
                sum + (double.tryParse(entry.value.toString()) ?? 0),
          );
    return LayoutBuilder(
      builder: (context, constraints) {
        final itemWidth = (constraints.maxWidth - 10) / 2;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (var index = 0; index < entries.length; index++)
              SizedBox(
                width: entries.length.isOdd && index == entries.length - 1
                    ? constraints.maxWidth
                    : itemWidth,
                height: 138,
                child: _WebMetricCard(
                  entry: entries[index],
                  total: effectiveTotal,
                ),
              ),
          ],
        );
      },
    );
  }
}

class _WebMetricCard extends StatelessWidget {
  const _WebMetricCard({required this.entry, required this.total});
  final MapEntry<String, dynamic> entry;
  final double total;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final isDark = colors.brightness == Brightness.dark;
    final label = entry.key.toLowerCase();
    final isTeacher = label.contains('giáo viên');
    final isWaitingAssign = label.contains('phân công');
    final isWaitingConfirm = label.contains('xác nhận');
    final isInProgress = label.contains('đang xử lý');
    final isPeriodTotal = label.startsWith('phiếu');
    final accent = isTeacher
        ? (isDark ? const Color(0xFFFFB46E) : const Color(0xFFC46B1A))
        : isWaitingConfirm
        ? (isDark ? const Color(0xFF55C5C4) : const Color(0xFF087F85))
        : isInProgress
        ? (isDark ? const Color(0xFFB29AFF) : const Color(0xFF7049C6))
        : isWaitingAssign
        ? (isDark ? const Color(0xFFF0B44D) : const Color(0xFF9A6100))
        : isPeriodTotal
        ? (isDark ? const Color(0xFFF0B44D) : const Color(0xFFD89732))
        : colors.primary;
    final icon = isTeacher
        ? Icons.school_outlined
        : isWaitingAssign
        ? Icons.person_add_alt_1_rounded
        : isWaitingConfirm
        ? Icons.assignment_turned_in_outlined
        : isInProgress
        ? Icons.handyman_outlined
        : isPeriodTotal
        ? Icons.calendar_month_outlined
        : Icons.confirmation_number_outlined;
    final note = isTeacher
        ? 'Phiếu ưu tiên cao từ giáo viên trong kỳ'
        : isWaitingAssign
        ? 'Cần quản lý tiếp nhận và giao việc'
        : isWaitingConfirm
        ? 'Nhân viên đã hoàn thành, chờ quản lý duyệt'
        : isInProgress
        ? 'Nhân viên đang khắc phục sự cố'
        : isPeriodTotal
        ? 'Báo cáo sự cố trong khoảng thời gian đã chọn'
        : 'Tổng số phiếu đang được theo dõi';
    final value = double.tryParse(entry.value.toString()) ?? 0;
    final progress = total <= 0
        ? 0.0
        : (value / total).clamp(0.0, 1.0).toDouble();

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: colors.outlineVariant),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? .08 : .035),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  entry.key,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: colors.onSurfaceVariant,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Container(
                width: 31,
                height: 31,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: isDark ? .2 : .12),
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Icon(icon, size: 17, color: accent),
              ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            entry.value.toString(),
            style: TextStyle(
              color: colors.onSurface,
              fontSize: 27,
              height: 1.05,
              fontWeight: FontWeight.w700,
              letterSpacing: -.5,
            ),
          ),
          const SizedBox(height: 4),
          Expanded(
            child: Align(
              alignment: Alignment.topLeft,
              child: Text(
                note,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: colors.onSurfaceVariant,
                  fontSize: 10,
                  height: 1.3,
                ),
              ),
            ),
          ),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 4,
              backgroundColor: isDark
                  ? const Color(0xFF263849)
                  : const Color(0xFFF0F3F7),
              valueColor: AlwaysStoppedAnimation<Color>(accent),
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.entry, required this.colors});
  final MapEntry<String, dynamic> entry;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) {
    final warning = entry.key.toLowerCase().contains('ch\u{1edd}');
    final accent = warning
        ? (colors.brightness == Brightness.light
              ? const Color(0xFFA96C12)
              : const Color(0xFFF0AD47))
        : colors.primary;
    final icon = warning
        ? Icons.hourglass_top_rounded
        : Icons.confirmation_number_rounded;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: colors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: .13),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 18, color: accent),
          ),
          const Spacer(),
          Text(
            entry.value.toString(),
            style: TextStyle(
              color: accent,
              fontSize: 23,
              height: 1,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            entry.key,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: colors.onSurfaceVariant,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.icon});
  final String title;
  final IconData icon;
  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Icon(icon, size: 18, color: colors.primary),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: colors.onSurface,
                letterSpacing: -.15,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.retry});
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      leading: Icon(
        Icons.cloud_off_outlined,
        color: Theme.of(context).colorScheme.error,
      ),
      title: Text(message),
      trailing: IconButton(onPressed: retry, icon: const Icon(Icons.refresh)),
    ),
  );
}

enum TicketMode { admin, assigned, customer, reported }

class TicketListPage extends StatefulWidget {
  const TicketListPage({super.key, required this.mode});
  final TicketMode mode;
  @override
  State<TicketListPage> createState() => _TicketListPageState();
}

class _TicketListPageState extends State<TicketListPage> {
  bool loading = true;
  bool loadingMore = false;
  bool hasMore = false;
  String? error;
  String search = '';
  String? status;
  String? categoryId;
  String? buildingCode;
  String? priority;
  String? period;
  String? reporterRole;
  List<Map<String, dynamic>> categories = [];
  List<Map<String, dynamic>> buildings = [];
  int page = 1;
  List<Map<String, dynamic>> tickets = [];

  String get endpoint => switch (widget.mode) {
    TicketMode.admin => '/admin/tickets',
    TicketMode.assigned => '/staff/tickets',
    TicketMode.customer => '/tickets',
    TicketMode.reported => '/customer/tickets',
  };

  Future<void> loadFilters() async {
    final api = AppScope.stateOf(context).api;
    try {
      final results = await Future.wait([
        api.get('/categories'),
        api.get('/buildings'),
      ]);
      categories = asRows(results[0]);
      buildings = asRows(results[1]);
    } catch (_) {}
  }

  Map<String, dynamic> get filterQuery => {
    if (search.trim().isNotEmpty) 'search': search.trim(),
    if (status != null) 'status': status!,
    if (categoryId != null) 'category_id': categoryId!,
    if (buildingCode != null) 'building': buildingCode!,
    if (priority != null) 'priority': priority!,
    if (period != null) 'period': period!,
    if (reporterRole != null) 'reporter_role': reporterRole!,
  };

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
      page = 1;
    });
    try {
      final payload = asMap(
        await AppScope.stateOf(context).api.get(
          endpoint,
          query: {'per_page': 50, 'page': page, ...filterQuery},
        ),
      );
      tickets = asRows(payload);
      hasMore = payload['next_page_url'] != null;
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> loadMore() async {
    if (!hasMore || loadingMore) return;
    setState(() => loadingMore = true);
    try {
      page += 1;
      final payload = asMap(
        await AppScope.stateOf(context).api.get(
          endpoint,
          query: {'per_page': 50, 'page': page, ...filterQuery},
        ),
      );
      tickets.addAll(asRows(payload));
      hasMore = payload['next_page_url'] != null;
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    } finally {
      if (mounted) setState(() => loadingMore = false);
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await loadFilters();
      if (mounted) load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final canCreate = widget.mode == TicketMode.reported;
    const statuses = <String, String>{
      'new': 'Chờ tiếp nhận',
      'assigned': 'Đã phân công',
      'in_progress': 'Đang xử lý',
      'resolved': 'Chờ xác nhận',
      'closed': 'Đã đóng',
      'reopened': 'Mở lại',
    };
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
            child: TextField(
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search),
                hintText: 'Tìm mã phiếu, tiêu đề, phòng...',
                suffixIcon: IconButton(
                  onPressed: load,
                  icon: const Icon(Icons.refresh),
                ),
              ),
              onChanged: (value) => search = value,
              onSubmitted: (_) => load(),
            ),
          ),
          SizedBox(
            height: 88,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              children: [
                SizedBox(
                  width: 190,
                  child: DropdownButtonFormField<String>(
                    isExpanded: true,
                    initialValue: categoryId,
                    decoration: const InputDecoration(labelText: 'Danh mục'),
                    items: [
                      const DropdownMenuItem<String>(
                        value: null,
                        child: Text('Tất cả danh mục'),
                      ),
                      ...categories.map(
                        (item) => DropdownMenuItem(
                          value: item['id'].toString(),
                          child: Text(item['name']?.toString() ?? ''),
                        ),
                      ),
                    ],
                    onChanged: (value) {
                      setState(() => categoryId = value);
                      load();
                    },
                  ),
                ),
                if (widget.mode == TicketMode.admin) ...[
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 210,
                    child: DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: buildingCode,
                      decoration: const InputDecoration(labelText: 'Khu nhà'),
                      items: [
                        const DropdownMenuItem<String>(
                          value: null,
                          child: Text('Tất cả khu nhà'),
                        ),
                        ...buildings.map(
                          (item) => DropdownMenuItem(
                            value: item['code']?.toString(),
                            child: Text(item['name']?.toString() ?? ''),
                          ),
                        ),
                      ],
                      onChanged: (value) {
                        setState(() => buildingCode = value);
                        load();
                      },
                    ),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 160,
                    child: DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: priority,
                      decoration: const InputDecoration(labelText: 'Ưu tiên'),
                      items: const [
                        DropdownMenuItem<String>(
                          value: null,
                          child: Text('Mọi mức'),
                        ),
                        DropdownMenuItem(value: 'low', child: Text('Thấp')),
                        DropdownMenuItem(
                          value: 'normal',
                          child: Text('Bình thường'),
                        ),
                        DropdownMenuItem(value: 'high', child: Text('Cao')),
                        DropdownMenuItem(
                          value: 'urgent',
                          child: Text('Khẩn cấp'),
                        ),
                      ],
                      onChanged: (value) {
                        setState(() => priority = value);
                        load();
                      },
                    ),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 160,
                    child: DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: period,
                      decoration: const InputDecoration(labelText: 'Thời gian'),
                      items: const [
                        DropdownMenuItem<String>(
                          value: null,
                          child: Text('Mọi thời gian'),
                        ),
                        DropdownMenuItem(value: 'day', child: Text('Hôm nay')),
                        DropdownMenuItem(
                          value: 'week',
                          child: Text('Tuần này'),
                        ),
                        DropdownMenuItem(
                          value: 'month',
                          child: Text('Tháng này'),
                        ),
                        DropdownMenuItem(value: 'year', child: Text('Năm nay')),
                      ],
                      onChanged: (value) {
                        setState(() => period = value);
                        load();
                      },
                    ),
                  ),
                  const SizedBox(width: 8),
                  SizedBox(
                    width: 180,
                    child: DropdownButtonFormField<String>(
                      isExpanded: true,
                      initialValue: reporterRole,
                      decoration: const InputDecoration(labelText: 'Người báo'),
                      items: const [
                        DropdownMenuItem<String>(
                          value: null,
                          child: Text('Mọi vai trò'),
                        ),
                        DropdownMenuItem(
                          value: 'student',
                          child: Text('Sinh viên'),
                        ),
                        DropdownMenuItem(
                          value: 'teacher',
                          child: Text('Giáo viên'),
                        ),
                        DropdownMenuItem(
                          value: 'staff',
                          child: Text('Nhân viên'),
                        ),
                        DropdownMenuItem(
                          value: 'admin',
                          child: Text('Quản lý'),
                        ),
                      ],
                      onChanged: (value) {
                        setState(() => reporterRole = value);
                        load();
                      },
                    ),
                  ),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: DropdownButtonFormField<String>(
              isExpanded: true,
              initialValue: status,
              decoration: const InputDecoration(labelText: 'Lọc trạng thái'),
              items: [
                const DropdownMenuItem<String>(
                  value: null,
                  child: Text('Tất cả trạng thái'),
                ),
                ...statuses.entries.map(
                  (e) => DropdownMenuItem(value: e.key, child: Text(e.value)),
                ),
              ],
              onChanged: (value) {
                setState(() => status = value);
                load();
              },
            ),
          ),
          if (loading) const LinearProgressIndicator(),
          if (error != null) _ErrorCard(message: error!, retry: load),
          Expanded(
            child: RefreshIndicator(
              onRefresh: load,
              child: tickets.isEmpty && !loading
                  ? ListView(
                      children: const [
                        SizedBox(height: 120),
                        Center(child: Text('Chưa có phiếu phù hợp.')),
                      ],
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.all(12),
                      itemCount: tickets.length + (hasMore ? 1 : 0),
                      itemBuilder: (_, i) {
                        if (i == tickets.length) {
                          return Center(
                            child: TextButton(
                              onPressed: loadingMore ? null : loadMore,
                              child: Text(
                                loadingMore ? 'Đang tải...' : 'Tải thêm phiếu',
                              ),
                            ),
                          );
                        }
                        return _TicketTile(ticket: tickets[i], onReturn: load);
                      },
                    ),
            ),
          ),
        ],
      ),
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) =>
                      TicketCreatePage(api: AppScope.stateOf(context).api),
                ),
              ).then((_) => load()),
              icon: const Icon(Icons.add),
              label: const Text('Báo sự cố'),
            )
          : null,
    );
  }
}

class _TicketTile extends StatelessWidget {
  const _TicketTile({required this.ticket, this.onReturn});
  final Map<String, dynamic> ticket;
  final VoidCallback? onReturn;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final category = asMap(ticket['category']);
    final room = asMap(ticket['room']);
    final status = ticket['status']?.toString() ?? '';
    final place =
        room['name']?.toString() ??
        ticket['room_label']?.toString() ??
        'Ch\u{01b0}a r\u{00f5} ph\u{00f2}ng';
    final type =
        category['name']?.toString() ??
        ticket['equipment_label']?.toString() ??
        'Y\u{00ea}u c\u{1ea7}u h\u{1ed7} tr\u{1ee3}';
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => TicketDetailPage(ticket: ticket)),
        ).then((_) => onReturn?.call()),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: colors.primaryContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  _statusIcon(status),
                  color: colors.onPrimaryContainer,
                  size: 22,
                ),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ticket['title']?.toString() ??
                          'Phi\u{1ebf}u s\u{1ef1} c\u{1ed1}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: colors.onSurface,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${ticket['ticket_number'] ?? ''}  ·  $type',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 11,
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        Icon(
                          Icons.location_on_outlined,
                          size: 13,
                          color: colors.onSurfaceVariant,
                        ),
                        const SizedBox(width: 3),
                        Expanded(
                          child: Text(
                            place,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11,
                              color: colors.onSurfaceVariant,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  _StatusChip(status: status),
                  const SizedBox(height: 5),
                  Icon(
                    Icons.chevron_right_rounded,
                    size: 18,
                    color: colors.onSurfaceVariant,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

IconData _statusIcon(String? status) => switch (status) {
  'new' => Icons.fiber_new,
  'assigned' => Icons.assignment_ind_outlined,
  'in_progress' => Icons.build_circle_outlined,
  'resolved' => Icons.task_alt,
  'closed' => Icons.check_circle_outline,
  'reopened' => Icons.replay,
  _ => Icons.confirmation_number_outlined,
};

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});
  final String status;
  @override
  Widget build(BuildContext context) {
    const labels = {
      'new': 'M\u{1edb}i',
      'assigned': '\u{0110}\u{00e3} giao',
      'in_progress': '\u{0110}ang x\u{1eed} l\u{00fd}',
      'resolved': 'Ch\u{1edd} duy\u{1ec7}t',
      'closed': '\u{0110}\u{00e3} \u{0111}\u{00f3}ng',
      'reopened': 'M\u{1edf} l\u{1ea1}i',
    };
    final isLight = Theme.of(context).brightness == Brightness.light;
    final color = switch (status) {
      'new' => isLight ? const Color(0xFF1887D5) : const Color(0xFF49AEE5),
      'assigned' => isLight ? const Color(0xFF6957BF) : const Color(0xFFA99BEA),
      'in_progress' =>
        isLight ? const Color(0xFFA96C12) : const Color(0xFFF0AD47),
      'resolved' => isLight ? const Color(0xFF27835F) : const Color(0xFF49C69A),
      'closed' => isLight ? const Color(0xFF72859A) : const Color(0xFFA9C1D5),
      'reopened' => isLight ? const Color(0xFFB84D43) : const Color(0xFFFF8A7A),
      _ => isLight ? const Color(0xFF72859A) : const Color(0xFFA9C1D5),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .13),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(color: color.withValues(alpha: .24)),
      ),
      child: Text(
        labels[status] ?? status,
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w700,
          color: color,
        ),
      ),
    );
  }
}

enum _PhotoSource { camera, gallery }

class TicketCreatePage extends StatefulWidget {
  const TicketCreatePage({super.key, required this.api, this.embedded = false});
  final ApiClient api;
  final bool embedded;
  @override
  State<TicketCreatePage> createState() => _TicketCreatePageState();
}

class _TicketCreatePageState extends State<TicketCreatePage> {
  final title = TextEditingController();
  final description = TextEditingController();
  final phone = TextEditingController();
  List<Map<String, dynamic>> buildings = [];
  List<Map<String, dynamic>> categories = [];
  final List<XFile> photos = [];
  String? buildingCode;
  String? roomCode;
  String? roomId;
  String? categoryId;
  String priority = 'normal';
  bool loading = true;
  bool saving = false;
  int currentStep = 0;

  List<Map<String, dynamic>> get rooms {
    for (final building in buildings) {
      if (building['code']?.toString() == buildingCode) {
        return asRows(building['rooms']);
      }
    }
    return [];
  }

  @override
  void dispose() {
    title.dispose();
    description.dispose();
    phone.dispose();
    super.dispose();
  }

  Future<void> loadOptions() async {
    try {
      final results = await Future.wait([
        widget.api.get('/buildings'),
        widget.api.get('/categories'),
      ]);
      buildings = asRows(asMap(results[0])['data']);
      categories = asRows(asMap(results[1])['data']);
      if (buildings.isNotEmpty)
        buildingCode ??= buildings.first['code']?.toString();
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> scanRoom() async {
    final code = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (_) => const QrScanPage()),
    );
    if (code == null || code.trim().isEmpty) return;
    try {
      final result = asMap(
        await widget.api.get('/rooms/${Uri.encodeComponent(code)}'),
      );
      final room = asMap(result['data']);
      final building = asMap(room['building']);
      if (!mounted) return;
      setState(() {
        roomCode =
            room['qr_code']?.toString() ?? room['code']?.toString() ?? code;
        roomId = room['id']?.toString();
        buildingCode = building['code']?.toString() ?? buildingCode;
      });
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    }
  }

  Future<void> addPhoto() async {
    if (photos.length >= 5) {
      toast(context, 'Tối đa 5 ảnh cho mỗi phiếu.');
      return;
    }
    final source = await showModalBottomSheet<_PhotoSource>(
      context: context,
      showDragHandle: true,
      builder: (sheet) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.photo_camera_outlined),
                title: const Text('Chụp ảnh'),
                onTap: () => Navigator.pop(sheet, _PhotoSource.camera),
              ),
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: const Text('Chọn từ thư viện'),
                onTap: () => Navigator.pop(sheet, _PhotoSource.gallery),
              ),
            ],
          ),
        ),
      ),
    );
    if (source == null || !mounted) return;
    try {
      final picker = ImagePicker();
      if (source == _PhotoSource.camera) {
        final photo = await picker.pickImage(
          source: ImageSource.camera,
          imageQuality: 85,
        );
        if (photo != null && mounted) setState(() => photos.add(photo));
      } else {
        final selected = await picker.pickMultiImage(
          imageQuality: 85,
          limit: 5 - photos.length,
        );
        if (selected.isNotEmpty && mounted) {
          setState(() => photos.addAll(selected.take(5 - photos.length)));
        }
      }
    } catch (error) {
      if (mounted)
        toast(
          context,
          'Không mở được camera/thư viện: ${readableError(error)}',
        );
    }
  }

  void removePhoto(XFile photo) => setState(() => photos.remove(photo));

  bool validateStep() {
    if (currentStep == 0 && (roomId == null || categoryId == null)) {
      toast(context, 'Chọn phòng và danh mục sự cố trước nhé.');
      return false;
    }
    if (currentStep == 1 &&
        (title.text.trim().isEmpty || description.text.trim().isEmpty)) {
      toast(context, 'Nhập tiêu đề và mô tả sự cố trước nhé.');
      return false;
    }
    return true;
  }

  void continueStep() {
    if (!validateStep()) return;
    FocusScope.of(context).unfocus();
    setState(() => currentStep = (currentStep + 1).clamp(0, 2));
  }

  Future<void> submit() async {
    if (roomId == null ||
        categoryId == null ||
        title.text.trim().isEmpty ||
        description.text.trim().isEmpty) {
      toast(context, 'Kiểm tra lại địa điểm và nội dung phiếu.');
      setState(
        () => currentStep = roomId == null || categoryId == null ? 0 : 1,
      );
      return;
    }
    FocusScope.of(context).unfocus();
    setState(() => saving = true);
    try {
      final result = asMap(
        await widget.api.upload(
          '/tickets',
          fields: {
            'room_code': roomCode ?? '',
            'room_id': roomId!,
            'building_code': buildingCode ?? '',
            'category_id': categoryId!,
            'title': title.text.trim(),
            'description': description.text.trim(),
            'phone': phone.text.trim(),
            'priority': priority,
          },
          files: {'attachments[]': photos},
        ),
      );
      if (!mounted) return;
      final data = asMap(result['data']);
      toast(
        context,
        (result['message']?.toString() ?? 'Đã gửi báo cáo.') +
            (data['ticket_number'] == null
                ? ''
                : ' Mã phiếu: ${data['ticket_number']}'),
      );
      if (Navigator.of(context).canPop()) {
        Navigator.pop(context, true);
      } else {
        setState(() {
          title.clear();
          description.clear();
          phone.clear();
          roomId = null;
          roomCode = null;
          categoryId = null;
          priority = 'normal';
          photos.clear();
          currentStep = 0;
        });
      }
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Widget stepIndicator(ColorScheme colors) {
    const labels = ['Địa điểm', 'Chi tiết', 'Gửi phiếu'];
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
      child: Row(
        children: List.generate(labels.length, (index) {
          final complete = index < currentStep;
          final active = index == currentStep;
          return Expanded(
            child: InkWell(
              onTap: index < currentStep
                  ? () => setState(() => currentStep = index)
                  : null,
              borderRadius: BorderRadius.circular(12),
              child: Column(
                children: [
                  Row(
                    children: [
                      if (index > 0)
                        Expanded(
                          child: Container(
                            height: 2,
                            color: complete || active
                                ? colors.primary
                                : colors.outlineVariant,
                          ),
                        ),
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 180),
                        width: active ? 30 : 25,
                        height: active ? 30 : 25,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: complete || active
                              ? colors.primary
                              : colors.surfaceContainerHighest,
                        ),
                        child: Icon(
                          complete
                              ? Icons.check_rounded
                              : [
                                  Icons.place_outlined,
                                  Icons.edit_note_rounded,
                                  Icons.send_rounded,
                                ][index],
                          size: 15,
                          color: complete || active
                              ? colors.onPrimary
                              : colors.onSurfaceVariant,
                        ),
                      ),
                      if (index < labels.length - 1)
                        Expanded(
                          child: Container(
                            height: 2,
                            color: complete
                                ? colors.primary
                                : colors.outlineVariant,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  Text(
                    labels[index],
                    maxLines: 1,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: active ? FontWeight.w700 : FontWeight.w500,
                      color: active ? colors.primary : colors.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget sectionCard({
    required IconData icon,
    required String title,
    required Widget child,
  }) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  icon,
                  size: 18,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(width: 8),
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ],
            ),
            const SizedBox(height: 12),
            child,
          ],
        ),
      ),
    );
  }

  Widget locationStep(List<Map<String, dynamic>> roomOptions) {
    return Column(
      children: [
        sectionCard(
          icon: Icons.location_on_outlined,
          title: 'Nơi xảy ra sự cố',
          child: Column(
            children: [
              DropdownButtonFormField<String>(
                initialValue:
                    buildings.any(
                      (item) => item['code']?.toString() == buildingCode,
                    )
                    ? buildingCode
                    : null,
                decoration: const InputDecoration(labelText: 'Tòa nhà'),
                items: buildings
                    .map(
                      (building) => DropdownMenuItem(
                        value: building['code']?.toString(),
                        child: Text(
                          building['name']?.toString() ??
                              building['code']?.toString() ??
                              '',
                        ),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() {
                  buildingCode = value;
                  roomCode = null;
                  roomId = null;
                }),
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                initialValue:
                    roomOptions.any((room) => room['id']?.toString() == roomId)
                    ? roomId
                    : null,
                decoration: const InputDecoration(labelText: 'Phòng'),
                items: roomOptions
                    .map(
                      (room) => DropdownMenuItem(
                        value: room['id']?.toString(),
                        child: Text(
                          room['name']?.toString() ??
                              room['code']?.toString() ??
                              '',
                        ),
                      ),
                    )
                    .toList(),
                onChanged: (value) {
                  final room = roomOptions
                      .where((item) => item['id']?.toString() == value)
                      .firstOrNull;
                  setState(() {
                    roomId = value;
                    roomCode =
                        room?['qr_code']?.toString() ??
                        room?['code']?.toString();
                  });
                },
              ),
              const SizedBox(height: 8),
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: scanRoom,
                  icon: const Icon(Icons.qr_code_scanner_rounded, size: 18),
                  label: const Text('Quét mã phòng'),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        sectionCard(
          icon: Icons.category_outlined,
          title: 'Danh mục',
          child: DropdownButtonFormField<String>(
            initialValue:
                categories.any((item) => item['id']?.toString() == categoryId)
                ? categoryId
                : null,
            decoration: const InputDecoration(labelText: 'Loại sự cố'),
            items: categories
                .map(
                  (category) => DropdownMenuItem(
                    value: category['id']?.toString(),
                    child: Text(category['name']?.toString() ?? ''),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => categoryId = value),
          ),
        ),
      ],
    );
  }

  Widget detailsStep() {
    return Column(
      children: [
        sectionCard(
          icon: Icons.edit_note_rounded,
          title: 'Mô tả sự cố',
          child: Column(
            children: [
              TextField(
                controller: title,
                maxLength: 180,
                decoration: const InputDecoration(labelText: 'Tiêu đề'),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: description,
                minLines: 4,
                maxLines: 7,
                maxLength: 5000,
                decoration: const InputDecoration(labelText: 'Mô tả chi tiết'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        sectionCard(
          icon: Icons.tune_rounded,
          title: 'Thông tin thêm',
          child: Column(
            children: [
              TextField(
                controller: phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(
                  labelText: 'Số điện thoại (tùy chọn)',
                  prefixIcon: Icon(Icons.phone_outlined),
                ),
              ),
              const SizedBox(height: 8),
              DropdownButtonFormField<String>(
                initialValue: priority,
                decoration: const InputDecoration(labelText: 'Mức ưu tiên'),
                items: const [
                  DropdownMenuItem(value: 'low', child: Text('Thấp')),
                  DropdownMenuItem(value: 'normal', child: Text('Bình thường')),
                  DropdownMenuItem(value: 'high', child: Text('Cao')),
                  DropdownMenuItem(value: 'urgent', child: Text('Khẩn cấp')),
                ],
                onChanged: (value) =>
                    setState(() => priority = value ?? 'normal'),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget reviewStep() {
    final colors = Theme.of(context).colorScheme;
    final building = buildings
        .where((item) => item['code']?.toString() == buildingCode)
        .firstOrNull;
    final room = rooms
        .where((item) => item['id']?.toString() == roomId)
        .firstOrNull;
    final category = categories
        .where((item) => item['id']?.toString() == categoryId)
        .firstOrNull;
    return Column(
      children: [
        sectionCard(
          icon: Icons.fact_check_outlined,
          title: 'Kiểm tra phiếu',
          child: Column(
            children: [
              _ReviewRow(
                label: 'Địa điểm',
                value:
                    '${building?['name'] ?? ''} · ${room?['name'] ?? roomCode ?? ''}',
              ),
              _ReviewRow(
                label: 'Danh mục',
                value: category?['name']?.toString() ?? '',
              ),
              _ReviewRow(label: 'Tiêu đề', value: title.text.trim()),
              _ReviewRow(
                label: 'Ưu tiên',
                value: priority == 'normal' ? 'Bình thường' : priority,
              ),
              if (phone.text.trim().isNotEmpty)
                _ReviewRow(label: 'Điện thoại', value: phone.text.trim()),
              const Divider(height: 18),
              Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  description.text.trim(),
                  maxLines: 5,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        sectionCard(
          icon: Icons.photo_library_outlined,
          title: 'Ảnh minh chứng',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${photos.length}/5 ảnh',
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  OutlinedButton.icon(
                    onPressed: addPhoto,
                    icon: const Icon(
                      Icons.add_photo_alternate_outlined,
                      size: 18,
                    ),
                    label: const Text('Thêm ảnh'),
                  ),
                ],
              ),
              if (photos.isNotEmpty) ...[
                const SizedBox(height: 8),
                GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 3,
                    crossAxisSpacing: 8,
                    mainAxisSpacing: 8,
                  ),
                  itemCount: photos.length,
                  itemBuilder: (context, index) {
                    final photo = photos[index];
                    return ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          Image.file(
                            File(photo.path),
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => ColoredBox(
                              color: colors.surfaceContainerHighest,
                              child: const Icon(Icons.image_outlined),
                            ),
                          ),
                          Positioned(
                            top: 4,
                            right: 4,
                            child: Material(
                              color: Colors.black54,
                              shape: const CircleBorder(),
                              child: InkWell(
                                onTap: () => removePhoto(photo),
                                customBorder: const CircleBorder(),
                                child: const Padding(
                                  padding: EdgeInsets.all(5),
                                  child: Icon(
                                    Icons.close_rounded,
                                    color: Colors.white,
                                    size: 16,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  @override
  void initState() {
    super.initState();
    loadOptions();
  }

  @override
  Widget build(BuildContext context) {
    final roomOptions = rooms;
    final colors = Theme.of(context).colorScheme;
    final steps = [locationStep(roomOptions), detailsStep(), reviewStep()];
    return Scaffold(
      appBar: widget.embedded ? null : AppBar(title: const Text('Báo sự cố')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                stepIndicator(colors),
                Expanded(
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 200),
                    child: SingleChildScrollView(
                      key: ValueKey(currentStep),
                      padding: const EdgeInsets.fromLTRB(14, 4, 14, 14),
                      child: steps[currentStep],
                    ),
                  ),
                ),
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
                    child: Row(
                      children: [
                        if (currentStep > 0) ...[
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: saving
                                  ? null
                                  : () => setState(() => currentStep--),
                              icon: const Icon(
                                Icons.arrow_back_rounded,
                                size: 18,
                              ),
                              label: const Text('Quay lại'),
                            ),
                          ),
                          const SizedBox(width: 10),
                        ],
                        Expanded(
                          flex: currentStep == 0 ? 1 : 2,
                          child: FilledButton.icon(
                            onPressed: saving
                                ? null
                                : (currentStep == 2 ? submit : continueStep),
                            icon: saving
                                ? const SizedBox(
                                    width: 17,
                                    height: 17,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : Icon(
                                    currentStep == 2
                                        ? Icons.send_rounded
                                        : Icons.arrow_forward_rounded,
                                    size: 18,
                                  ),
                            label: Text(
                              saving
                                  ? 'Đang gửi...'
                                  : currentStep == 2
                                  ? 'Gửi phiếu'
                                  : 'Tiếp tục',
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}

class _ReviewRow extends StatelessWidget {
  const _ReviewRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 86,
          child: Text(
            label,
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontSize: 12,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class QrScanPage extends StatefulWidget {
  const QrScanPage({super.key});
  @override
  State<QrScanPage> createState() => _QrScanPageState();
}

class _QrScanPageState extends State<QrScanPage> {
  bool handled = false;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Quét QR phòng')),
    body: MobileScanner(
      onDetect: (capture) {
        if (handled || capture.barcodes.isEmpty) return;
        final value = capture.barcodes.first.rawValue;
        if (value == null || value.isEmpty) return;
        handled = true;
        final uri = Uri.tryParse(value);
        final code = uri != null && uri.pathSegments.isNotEmpty
            ? uri.pathSegments.last
            : value;
        Navigator.pop(context, code);
      },
    ),
  );
}

class TicketDetailPage extends StatefulWidget {
  const TicketDetailPage({super.key, required this.ticket});
  final Map<String, dynamic> ticket;
  @override
  State<TicketDetailPage> createState() => _TicketDetailPageState();
}

class _TicketDetailPageState extends State<TicketDetailPage> {
  bool loading = true;
  bool busy = false;
  Map<String, dynamic> ticket = {};
  List<Map<String, dynamic>> eligibleStaff = [];

  int get id =>
      int.tryParse((ticket['id'] ?? widget.ticket['id']).toString()) ?? 0;
  AppState get app => AppScope.stateOf(context);

  Future<void> load() async {
    setState(() => loading = true);
    try {
      final result = asMap(await app.api.get('/tickets/' + id.toString()));
      ticket = asMap(result['data']);
      eligibleStaff = asRows(ticket['eligible_staff']);
    } catch (e) {
      if (mounted) toast(context, readableError(e));
      ticket = widget.ticket;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> act(Future<dynamic> Function() action) async {
    setState(() => busy = true);
    try {
      final result = asMap(await action());
      if (mounted)
        toast(context, result['message']?.toString() ?? 'Đã cập nhật.');
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> updateProgress(bool resolved) async {
    final note = TextEditingController();
    final files = <XFile>[];
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: Text(resolved ? 'Gửi kết quả sửa chữa' : 'Bắt đầu xử lý'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: note,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Ghi chú xử lý'),
              ),
              if (resolved)
                OutlinedButton.icon(
                  onPressed: () async {
                    final selected = await ImagePicker().pickMultiImage(
                      imageQuality: 85,
                      limit: 5,
                    );
                    update(() {
                      files
                        ..clear()
                        ..addAll(selected.take(5));
                    });
                  },
                  icon: const Icon(Icons.add_a_photo),
                  label: Text(
                    'Ảnh minh chứng (' + files.length.toString() + ')',
                  ),
                ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Gửi'),
            ),
          ],
        ),
      ),
    );
    if (ok != true) return;
    if (note.text.trim().isEmpty) {
      toast(context, 'Nhập ghi chú tiến độ trước khi lưu.');
      return;
    }
    if (resolved && files.isEmpty) {
      toast(context, 'Thêm ít nhất một ảnh sau sửa chữa.');
      return;
    }
    await act(
      () => app.api.upload(
        '/staff/tickets/' + id.toString() + '/status',
        fields: {
          'status': resolved ? 'resolved' : 'in_progress',
          'note': note.text.trim(),
        },
        files: {'attachments[]': files},
      ),
    );
  }

  Future<void> assign() async {
    if (eligibleStaff.isEmpty) {
      toast(context, 'Không có nhân viên phù hợp danh mục phiếu này.');
      return;
    }
    String? staffId = ticket['assigned_to_id']?.toString();
    final note = TextEditingController();
    DateTime selected = DateTime.now().add(const Duration(hours: 1));
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Phân công xử lý'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                value: eligibleStaff.any((s) => s['id']?.toString() == staffId)
                    ? staffId
                    : null,
                decoration: const InputDecoration(
                  labelText: 'Nhân viên phù hợp',
                ),
                items: eligibleStaff
                    .map(
                      (s) => DropdownMenuItem(
                        value: s['id']?.toString(),
                        child: Text(s['name']?.toString() ?? ''),
                      ),
                    )
                    .toList(),
                onChanged: (v) => update(() => staffId = v),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Thời gian hẹn'),
                subtitle: Text(selected.toLocal().toString().substring(0, 16)),
                trailing: const Icon(Icons.calendar_month),
                onTap: () async {
                  final date = await showDatePicker(
                    context: dialog,
                    initialDate: selected,
                    firstDate: DateTime.now(),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                  );
                  if (date == null || !dialog.mounted) return;
                  final time = await showTimePicker(
                    context: dialog,
                    initialTime: TimeOfDay.fromDateTime(selected),
                  );
                  if (time == null) return;
                  update(
                    () => selected = DateTime(
                      date.year,
                      date.month,
                      date.day,
                      time.hour,
                      time.minute,
                    ),
                  );
                },
              ),
              TextField(
                controller: note,
                maxLines: 2,
                decoration: const InputDecoration(
                  labelText: 'Ghi chú phân công',
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Phân công'),
            ),
          ],
        ),
      ),
    );
    if (ok != true || staffId == null) return;
    if (note.text.trim().isEmpty) {
      toast(context, 'Nhập ghi chú phân công.');
      return;
    }
    await act(
      () => app.api.post(
        '/admin/tickets/' + id.toString() + '/assign',
        body: {
          'staff_id': staffId,
          'scheduled_at': selected.toIso8601String(),
          'note': note.text.trim(),
        },
      ),
    );
  }

  Future<void> closeOrReopen(bool reopen) async {
    final note = TextEditingController();
    if (reopen) {
      final accepted = await showDialog<bool>(
        context: context,
        builder: (dialog) => AlertDialog(
          title: const Text('Yêu cầu xử lý lại'),
          content: TextField(
            controller: note,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Lý do (bắt buộc)'),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Gửi yêu cầu'),
            ),
          ],
        ),
      );
      if (accepted != true) return;
    } else {
      final accepted = await showDialog<bool>(
        context: context,
        builder: (dialog) => AlertDialog(
          title: const Text('Xác nhận hoàn thành?'),
          content: const Text(
            'Đóng phiếu sau khi đã kiểm tra kết quả và ảnh sửa chữa.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Đóng phiếu'),
            ),
          ],
        ),
      );
      if (accepted != true) return;
    }
    if (reopen && note.text.trim().isEmpty) {
      toast(context, 'Nhập lý do yêu cầu xử lý lại.');
      return;
    }
    await act(
      () => app.api.post(
        '/admin/tickets/' + id.toString() + (reopen ? '/reopen' : '/close'),
        body: {'note': note.text.trim()},
      ),
    );
  }

  Future<void> acknowledge(int scheduleId) => act(
    () => app.api.post(
      '/staff/schedules/' + scheduleId.toString() + '/acknowledge',
    ),
  );

  Future<void> editReport() async {
    final room = asMap(ticket['room']);
    final building = asMap(room['building']);
    final category = asMap(ticket['category']);
    final title = TextEditingController(
      text: ticket['title']?.toString() ?? '',
    );
    final description = TextEditingController(
      text: ticket['description']?.toString() ?? '',
    );
    final phone = TextEditingController(
      text: ticket['reporter_phone']?.toString() ?? '',
    );
    var categoryId = (ticket['category_id'] ?? category['id'])?.toString();
    var priority = ticket['priority']?.toString() ?? 'normal';
    final photos = <XFile>[];
    var removePhotos = false;
    final categories = asRows(asMap(await app.api.get('/categories'))['data']);
    final buildings = asRows(asMap(await app.api.get('/buildings'))['data']);
    var buildingCode = building['code']?.toString() ?? '';
    var roomId = room['id']?.toString() ?? ticket['room_id']?.toString() ?? '';
    var roomCode =
        room['qr_code']?.toString() ?? room['code']?.toString() ?? '';
    List<Map<String, dynamic>> roomChoices() {
      for (final item in buildings) {
        if (item['code']?.toString() == buildingCode)
          return asRows(item['rooms']);
      }
      return [];
    }

    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Sửa phiếu đang chờ'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: title,
                  decoration: const InputDecoration(labelText: 'Tiêu đề'),
                ),
                TextField(
                  controller: description,
                  maxLines: 4,
                  decoration: const InputDecoration(labelText: 'Mô tả'),
                ),
                TextField(
                  controller: phone,
                  decoration: const InputDecoration(labelText: 'Điện thoại'),
                ),
                DropdownButtonFormField<String>(
                  initialValue: buildingCode,
                  decoration: const InputDecoration(labelText: 'Tòa nhà'),
                  items: buildings
                      .map(
                        (item) => DropdownMenuItem(
                          value: item['code']?.toString(),
                          child: Text(item['name']?.toString() ?? ''),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => update(() {
                    buildingCode = value ?? '';
                    roomId = '';
                    roomCode = '';
                  }),
                ),
                DropdownButtonFormField<String>(
                  key: ValueKey(buildingCode),
                  initialValue:
                      roomChoices().any(
                        (item) => item['id']?.toString() == roomId,
                      )
                      ? roomId
                      : null,
                  decoration: const InputDecoration(labelText: 'Phòng'),
                  items: roomChoices()
                      .map(
                        (item) => DropdownMenuItem(
                          value: item['id']?.toString(),
                          child: Text(item['name']?.toString() ?? ''),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    if (value == null) return;
                    final selected = roomChoices().firstWhere(
                      (item) => item['id']?.toString() == value,
                    );
                    update(() {
                      roomId = value;
                      roomCode =
                          selected['qr_code']?.toString() ??
                          selected['code']?.toString() ??
                          '';
                    });
                  },
                ),
                DropdownButtonFormField<String>(
                  value:
                      categories.any((c) => c['id']?.toString() == categoryId)
                      ? categoryId
                      : null,
                  decoration: const InputDecoration(labelText: 'Danh mục'),
                  items: categories
                      .map(
                        (c) => DropdownMenuItem(
                          value: c['id']?.toString(),
                          child: Text(c['name']?.toString() ?? ''),
                        ),
                      )
                      .toList(),
                  onChanged: (v) => update(() => categoryId = v),
                ),
                DropdownButtonFormField<String>(
                  initialValue: priority,
                  decoration: const InputDecoration(labelText: 'Mức ưu tiên'),
                  items: const [
                    DropdownMenuItem(value: 'low', child: Text('Thấp')),
                    DropdownMenuItem(
                      value: 'normal',
                      child: Text('Bình thường'),
                    ),
                    DropdownMenuItem(value: 'high', child: Text('Cao')),
                    DropdownMenuItem(value: 'urgent', child: Text('Khẩn cấp')),
                  ],
                  onChanged: (value) =>
                      update(() => priority = value ?? 'normal'),
                ),
                OutlinedButton.icon(
                  onPressed: () async {
                    final picked = await ImagePicker().pickMultiImage(
                      imageQuality: 85,
                      limit: 5,
                    );
                    if (dialog.mounted)
                      update(() {
                        photos
                          ..clear()
                          ..addAll(picked.take(5));
                      });
                  },
                  icon: const Icon(Icons.add_a_photo_outlined),
                  label: Text(
                    'Thay ảnh báo cáo (' + photos.length.toString() + '/5)',
                  ),
                ),
                if (asRows(ticket['attachments']).isNotEmpty)
                  CheckboxListTile(
                    title: const Text('Xóa ảnh báo cáo hiện tại'),
                    value: removePhotos,
                    onChanged: (value) =>
                        update(() => removePhotos = value ?? false),
                  ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Lưu'),
            ),
          ],
        ),
      ),
    );
    if (ok != true || categoryId == null) return;
    if (roomId.isEmpty || buildingCode.isEmpty || roomCode.isEmpty) {
      toast(context, 'Chọn tòa nhà và phòng.');
      return;
    }
    await act(
      () => app.api.upload(
        '/tickets/' + id.toString(),
        fields: {
          'phone': phone.text.trim(),
          'room_code': roomCode,
          'room_id': roomId,
          'building_code': buildingCode,
          'title': title.text.trim(),
          'description': description.text.trim(),
          'category_id': categoryId ?? '',
          'priority': priority,
          if (removePhotos && photos.isEmpty) 'remove_attachments': '1',
        },
        files: {'attachments[]': photos},
      ),
    );
  }

  @override
  void initState() {
    super.initState();
    ticket = widget.ticket;
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) {
    final role = app.role;
    final schedules = asRows(ticket['schedules']);
    final logs = asRows(ticket['logs']);
    final attachments = asRows(ticket['attachments']);
    final colors = Theme.of(context).colorScheme;
    final status = ticket['status']?.toString() ?? '';
    final isReporter =
        ticket['reporter_id']?.toString() == app.user?['id']?.toString();
    return Scaffold(
      appBar: AppBar(
        title: Text(ticket['ticket_number']?.toString() ?? 'Chi tiết phiếu'),
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Text(
                                ticket['title']?.toString() ?? '',
                                style: Theme.of(context).textTheme.titleLarge
                                    ?.copyWith(
                                      color: colors.onSurface,
                                      fontWeight: FontWeight.w800,
                                    ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            _StatusChip(status: status),
                          ],
                        ),
                        if ((ticket['description']?.toString() ?? '')
                            .trim()
                            .isNotEmpty) ...[
                          const SizedBox(height: 14),
                          Text(
                            ticket['description'].toString(),
                            style: Theme.of(context).textTheme.bodyLarge
                                ?.copyWith(color: colors.onSurfaceVariant),
                          ),
                        ],
                        const Divider(height: 24),
                        _detailRow(
                          'Phòng',
                          ticket['room_label']?.toString() ??
                              asMap(ticket['room'])['name']?.toString(),
                        ),
                        _detailRow(
                          'Tòa nhà',
                          ticket['building_label']?.toString(),
                        ),
                        _detailRow(
                          'Danh mục',
                          asMap(ticket['category'])['name']?.toString() ??
                              ticket['equipment_label']?.toString(),
                        ),
                        _detailRow(
                          'Ưu tiên',
                          ticket['priority_label']?.toString() ??
                              ticket['priority']?.toString(),
                        ),
                        _detailRow(
                          'Người báo',
                          asMap(ticket['reporter'])['name']?.toString() ??
                              ticket['reporter_name']?.toString(),
                        ),
                        if (ticket['resolution_note'] != null)
                          _detailRow(
                            'Kết quả sửa chữa',
                            ticket['resolution_note'].toString(),
                          ),
                      ],
                    ),
                  ),
                ),
                if (isReporter && status == 'new')
                  OutlinedButton.icon(
                    onPressed: busy ? null : editReport,
                    icon: const Icon(Icons.edit_outlined),
                    label: const Text('Sửa phiếu'),
                  ),
                if (attachments.isNotEmpty) ...[
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const _SectionHeading(
                            title: '\u{1ea2}nh \u{0111}\u{00ed}nh k\u{00e8}m',
                            icon: Icons.photo_library_outlined,
                          ),
                          Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: attachments.map((attachment) {
                              final asset =
                                  (attachment['url'] ?? attachment['path'])
                                      ?.toString() ??
                                  '';
                              final imageUrl = asset.isEmpty
                                  ? ''
                                  : AppScope.stateOf(context).api
                                        .resolveAssetUrl(asset);
                              return InkWell(
                                borderRadius: BorderRadius.circular(14),
                                onTap: imageUrl.isEmpty
                                    ? null
                                    : () => showDialog<void>(
                                        context: context,
                                        builder: (_) => Dialog(
                                          backgroundColor: Colors.black,
                                          insetPadding: const EdgeInsets.all(
                                            18,
                                          ),
                                          child: InteractiveViewer(
                                            child: Image.network(
                                              imageUrl,
                                              fit: BoxFit.contain,
                                            ),
                                          ),
                                        ),
                                      ),
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(14),
                                  child: Container(
                                    width: 116,
                                    height: 116,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .surfaceContainerHighest,
                                    child: imageUrl.isEmpty
                                        ? const Icon(
                                            Icons.image_not_supported_outlined,
                                          )
                                        : Image.network(
                                            imageUrl,
                                            width: 116,
                                            height: 116,
                                            fit: BoxFit.cover,
                                            errorBuilder: (_, __, ___) =>
                                                const Icon(
                                                  Icons.broken_image_outlined,
                                                ),
                                          ),
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
                if (schedules.isNotEmpty) ...[
                  const _SectionHeading(
                    title: 'L\u{1ecb}ch x\u{1eed} l\u{00fd}',
                    icon: Icons.event_available_outlined,
                  ),
                  ...schedules.map(
                    (s) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.event_available_outlined),
                        title: Text(displayDateTime(s['starts_at'])),
                        subtitle: Text(
                          (asMap(s['staff'])['name']?.toString() ?? '') +
                              ' · ' +
                              (s['status']?.toString() ?? ''),
                        ),
                        trailing: role == 'staff' && s['status'] == 'scheduled'
                            ? IconButton(
                                onPressed: busy
                                    ? null
                                    : () => acknowledge(
                                        int.tryParse(s['id'].toString()) ?? 0,
                                      ),
                                icon: const Icon(Icons.check_circle_outline),
                              )
                            : null,
                      ),
                    ),
                  ),
                ],
                if (logs.isNotEmpty) ...[
                  const _SectionHeading(
                    title: 'L\u{1ecb}ch s\u{1eed}',
                    icon: Icons.history_rounded,
                  ),
                  Card(
                    child: Column(
                      children: [
                        for (var index = 0; index < logs.length; index++) ...[
                          if (index > 0) const Divider(height: 1, indent: 58),
                          ListTile(
                            leading: const Icon(Icons.history_rounded),
                            title: Text(logs[index]['note']?.toString() ?? ''),
                            subtitle: Text(
                              asMap(logs[index]['user'])['name']?.toString() ??
                                  '',
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
                if (role == 'admin') ...[
                  if (['new', 'reopened', 'assigned'].contains(status))
                    FilledButton.icon(
                      onPressed: busy ? null : assign,
                      icon: const Icon(Icons.assignment_ind),
                      label: const Text('Phân công / đổi lịch'),
                    ),
                  if (status == 'resolved')
                    FilledButton.icon(
                      onPressed: busy ? null : () => closeOrReopen(false),
                      icon: const Icon(Icons.check),
                      label: const Text('Xác nhận hoàn thành'),
                    ),
                  if (['resolved', 'closed'].contains(status))
                    OutlinedButton.icon(
                      onPressed: busy ? null : () => closeOrReopen(true),
                      icon: const Icon(Icons.replay),
                      label: const Text('Yêu cầu xử lý lại'),
                    ),
                ],
                if (role == 'staff' &&
                    ticket['assigned_to_id']?.toString() ==
                        app.user?['id']?.toString()) ...[
                  if (['assigned', 'reopened'].contains(status))
                    FilledButton.icon(
                      onPressed: busy ? null : () => updateProgress(false),
                      icon: const Icon(Icons.play_arrow),
                      label: const Text('Bắt đầu xử lý'),
                    ),
                  if (status == 'in_progress')
                    OutlinedButton.icon(
                      onPressed: busy ? null : () => updateProgress(false),
                      icon: const Icon(Icons.note_add_outlined),
                      label: const Text('Lưu tiến độ'),
                    ),
                  if (status == 'in_progress')
                    FilledButton.icon(
                      onPressed: busy ? null : () => updateProgress(true),
                      icon: const Icon(Icons.task_alt),
                      label: const Text('Gửi kết quả và ảnh sửa chữa'),
                    ),
                ],
                if (busy) const Center(child: CircularProgressIndicator()),
              ],
            ),
    );
  }

  Widget _detailRow(String label, String? value) {
    final colors = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 125,
            child: Text(
              label,
              style: TextStyle(
                color: isDark
                    ? const Color(0xFFC6D8E8)
                    : const Color(0xFF607B94),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value ?? '\u2014',
              style: TextStyle(
                color: colors.onSurface,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class SchedulePage extends StatefulWidget {
  const SchedulePage({super.key, required this.isAdmin});
  final bool isAdmin;
  @override
  State<SchedulePage> createState() => _SchedulePageState();
}

class _SchedulePageState extends State<SchedulePage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> schedules = [];
  DateTime anchor = DateTime.now();
  String range = 'month';
  DateTime get rangeStart {
    if (range == 'day') return DateTime(anchor.year, anchor.month, anchor.day);
    if (range == 'week') {
      final day = DateTime(anchor.year, anchor.month, anchor.day);
      return day.subtract(Duration(days: day.weekday - DateTime.monday));
    }
    return DateTime(anchor.year, anchor.month);
  }

  DateTime get rangeEnd {
    if (range == 'day') return rangeStart;
    if (range == 'week') return rangeStart.add(const Duration(days: 6));
    return DateTime(anchor.year, anchor.month + 1, 0);
  }

  String get rangeLabel {
    if (range == 'day') return dayString(anchor);
    if (range == 'week')
      return '${dayString(rangeStart)} - ${dayString(rangeEnd)}';
    return 'Tháng ${anchor.month}/${anchor.year}';
  }

  String dayString(DateTime date) =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  void changeRange(int offset) {
    setState(() {
      if (range == 'day')
        anchor = anchor.add(Duration(days: offset));
      else if (range == 'week')
        anchor = anchor.add(Duration(days: 7 * offset));
      else
        anchor = DateTime(anchor.year, anchor.month + offset, 1);
    });
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final path = widget.isAdmin ? '/admin/schedules' : '/staff/schedules';
      schedules = asRows(
        await AppScope.stateOf(context).api.get(
          path,
          query: {
            'per_page': 100,
            'include_cancelled': 1,
            'from': dayString(rangeStart),
            'to': dayString(rangeEnd),
          },
        ),
      );
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> cancel(Map<String, dynamic> schedule) async {
    final id = schedule['id'].toString();
    final note = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Hủy lịch'),
        content: TextField(
          controller: note,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Lý do (tùy chọn)'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Đóng'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Hủy lịch'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await AppScope.stateOf(context).api.post(
        '/admin/schedules/' + id + '/cancel',
        body: {'note': note.text},
      );
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: load,
    child: ListView(
      padding: const EdgeInsets.all(12),
      children: [
        DropdownButtonFormField<String>(
          initialValue: range,
          decoration: const InputDecoration(labelText: 'Khoảng thời gian'),
          items: const [
            DropdownMenuItem(value: 'day', child: Text('Ngày')),
            DropdownMenuItem(value: 'week', child: Text('Tuần')),
            DropdownMenuItem(value: 'month', child: Text('Tháng')),
          ],
          onChanged: (value) {
            if (value != null) {
              setState(() => range = value);
              load();
            }
          },
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            IconButton(
              onPressed: () => changeRange(-1),
              icon: const Icon(Icons.chevron_left),
            ),
            Expanded(
              child: Text(
                rangeLabel,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
            IconButton(
              onPressed: () => changeRange(1),
              icon: const Icon(Icons.chevron_right),
            ),
          ],
        ),
        if (widget.isAdmin)
          OutlinedButton.icon(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => const TicketListPage(mode: TicketMode.admin),
              ),
            ).then((_) => load()),
            icon: const Icon(Icons.assignment_add),
            label: const Text('Phân công phiếu'),
          ),

        if (loading) const LinearProgressIndicator(),
        if (error != null) _ErrorCard(message: error!, retry: load),
        if (schedules.isEmpty && !loading)
          const Padding(
            padding: EdgeInsets.all(32),
            child: Center(child: Text('Chưa có lịch xử lý.')),
          ),
        ...schedules.map(
          (s) => Card(
            child: ListTile(
              leading: const Icon(Icons.event_note),
              title: Text(
                asMap(s['ticket'])['ticket_number']?.toString() ?? 'Lịch xử lý',
              ),
              subtitle: Text(
                displayDateTime(s['starts_at']) +
                    '\n' +
                    (asMap(s['staff'])['name']?.toString() ?? '') +
                    ' · ' +
                    (s['status']?.toString() ?? ''),
              ),
              isThreeLine: true,
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => TicketDetailPage(ticket: asMap(s['ticket'])),
                ),
              ).then((_) => load()),
              trailing: widget.isAdmin && s['status'] != 'cancelled'
                  ? IconButton(
                      onPressed: () => cancel(s),
                      icon: const Icon(Icons.event_busy_outlined),
                    )
                  : null,
            ),
          ),
        ),
      ],
    ),
  );
}

class NotificationsPage extends StatefulWidget {
  const NotificationsPage({super.key});
  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> notifications = [];

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final state = AppScope.stateOf(context);
      final response = asMap(
        await state.api.get('/notifications', query: {'per_page': 50}),
      );
      notifications = asRows(response['data']);
      final unread =
          int.tryParse(response['unread_count']?.toString() ?? '') ?? 0;
      if (state.unreadNotificationCount != unread) {
        state.unreadNotificationCount = unread;
        state.notifyListeners();
      }
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> markRead(String id) async {
    try {
      await AppScope.stateOf(context).api
          .patch('/notifications/' + id + '/read');
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> openNotification(Map<String, dynamic> notification) async {
    try {
      if (notification['read_at'] == null) {
        await AppScope.stateOf(context).api
            .patch('/notifications/' + notification['id'].toString() + '/read');
      }
      if (!mounted) return;
      final ticketId = notification['ticket_id'];
      if (ticketId != null) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => TicketDetailPage(ticket: {'id': ticketId}),
          ),
        );
      }
      if (mounted) await load();
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    }
  }

  Future<void> markAll() async {
    try {
      await AppScope.stateOf(context).api.patch('/notifications/read-all');
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Thông báo'),
      actions: [TextButton(onPressed: markAll, child: const Text('Đọc hết'))],
    ),
    body: RefreshIndicator(
      onRefresh: load,
      child: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          if (loading) const LinearProgressIndicator(),
          if (error != null) _ErrorCard(message: error!, retry: load),
          if (notifications.isEmpty && !loading)
            const Padding(
              padding: EdgeInsets.all(30),
              child: Center(child: Text('Chưa có thông báo.')),
            ),
          ...notifications.map((n) {
            final unread = n['read_at'] == null;
            final colors = Theme.of(context).colorScheme;
            return Card(
              color: unread
                  ? colors.primaryContainer.withValues(alpha: .42)
                  : colors.surface,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: BorderSide(
                  color: unread
                      ? colors.primary.withValues(alpha: .55)
                      : colors.outlineVariant,
                  width: unread ? 1.2 : .7,
                ),
              ),
              child: ListTile(
                leading: Icon(
                  unread
                      ? Icons.mark_email_unread_rounded
                      : Icons.mark_email_read_outlined,
                  color: unread ? colors.primary : colors.onSurfaceVariant,
                ),
                title: Text(
                  n['title']?.toString() ?? 'Cập nhật Helpdesk',
                  style: TextStyle(
                    fontWeight: unread ? FontWeight.w800 : FontWeight.w500,
                  ),
                ),
                subtitle: Text(
                  (n['body']?.toString() ?? '') +
                      '\n' +
                      (n['created_at']?.toString() ?? ''),
                  style: TextStyle(
                    fontWeight: unread ? FontWeight.w600 : FontWeight.w400,
                    color: unread ? colors.onSurface : colors.onSurfaceVariant,
                  ),
                ),
                isThreeLine: true,
                onTap: () => openNotification(n),
              ),
            );
          }),
        ],
      ),
    ),
  );
}

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key, required this.state});
  final AppState state;
  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  final name = TextEditingController();
  final currentPassword = TextEditingController();
  final newPassword = TextEditingController();
  final confirmPassword = TextEditingController();
  XFile? avatar;
  bool saving = false;

  Future<void> saveProfile() async {
    setState(() => saving = true);
    try {
      if (avatar != null) {
        await widget.state.api.upload(
          '/account/profile',
          fields: {'name': name.text.trim()},
          files: {
            'avatar': [avatar!],
          },
        );
      } else {
        await widget.state.api.patch(
          '/account/profile',
          body: {'name': name.text.trim()},
        );
      }
      await widget.state.refreshProfile();
      if (mounted) {
        setState(() => avatar = null);
        toast(context, 'Đã cập nhật hồ sơ.');
      }
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> changePassword() async {
    currentPassword.clear();
    newPassword.clear();
    confirmPassword.clear();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Đổi mật khẩu'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: currentPassword,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Mật khẩu hiện tại'),
            ),
            TextField(
              controller: newPassword,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Mật khẩu mới'),
            ),
            TextField(
              controller: confirmPassword,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Nhập lại mật khẩu'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Đổi mật khẩu'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await widget.state.changePassword(
        currentPassword.text,
        newPassword.text,
        confirmPassword.text,
      );
      if (mounted)
        toast(context, 'Đã đổi mật khẩu. Các token khác đã bị thu hồi.');
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    name.text = widget.state.user?['name']?.toString() ?? '';
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.state.user ?? {};
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              children: [
                UserAvatar(
                  user: user,
                  api: widget.state.api,
                  radius: 36,
                  preview: avatar,
                ),
                const SizedBox(height: 12),
                Text(
                  user['email']?.toString() ?? '',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                Text('Vai trò: ${roleLabel(user['role'])}'),
                const SizedBox(height: 16),
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Tên hiển thị'),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: saving
                      ? null
                      : () async {
                          final selected = await ImagePicker().pickImage(
                            source: ImageSource.gallery,
                            imageQuality: 85,
                          );
                          if (mounted && selected != null)
                            setState(() => avatar = selected);
                        },
                  icon: const Icon(Icons.account_circle_outlined),
                  label: Text(avatar?.name ?? 'Chọn ảnh đại diện'),
                ),
                const SizedBox(height: 10),
                FilledButton(
                  onPressed: saving ? null : saveProfile,
                  child: Text(saving ? 'Đang lưu...' : 'Lưu hồ sơ'),
                ),
              ],
            ),
          ),
        ),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Giao diện',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 5),
                Text(
                  'Chọn giao diện phù hợp với bạn',
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 14),
                SegmentedButton<ThemeMode>(
                  segments: const [
                    ButtonSegment(
                      value: ThemeMode.light,
                      label: Text('Sáng'),
                      icon: Icon(Icons.light_mode_outlined),
                    ),
                    ButtonSegment(
                      value: ThemeMode.dark,
                      label: Text('Tối'),
                      icon: Icon(Icons.dark_mode_outlined),
                    ),
                  ],
                  selected: {widget.state.themeMode},
                  onSelectionChanged: (selection) =>
                      widget.state.setThemeMode(selection.first),
                ),
              ],
            ),
          ),
        ),
        Card(
          child: Column(
            children: [
              ListTile(
                leading: const Icon(Icons.lock_outline),
                title: const Text('Đổi mật khẩu'),
                trailing: const Icon(Icons.chevron_right),
                onTap: changePassword,
              ),
              ListTile(
                leading: Icon(
                  Icons.logout,
                  color: Theme.of(context).colorScheme.error,
                ),
                title: const Text('Đăng xuất'),
                onTap: () async {
                  try {
                    await widget.state.logout();
                  } catch (_) {}
                },
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class AdminManagePage extends StatelessWidget {
  const AdminManagePage({super.key});
  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 6,
    child: Column(
      children: [
        const TabBar(
          isScrollable: true,
          tabs: [
            Tab(text: 'Danh mục'),
            Tab(text: 'Người dùng'),
            Tab(text: 'Đội ngũ'),
            Tab(text: 'Địa điểm'),
            Tab(text: 'Báo cáo'),
            Tab(text: 'Nhật ký'),
          ],
        ),
        Expanded(
          child: TabBarView(
            children: [
              _AdminList(kind: 'categories'),
              _AdminList(kind: 'users'),
              _AdminList(kind: 'team'),
              _AdminList(kind: 'buildings'),
              const AdminReportsPage(),
              const AdminActivityLogPage(),
            ],
          ),
        ),
      ],
    ),
  );
}

class AdminReportsPage extends StatefulWidget {
  const AdminReportsPage({super.key});
  @override
  State<AdminReportsPage> createState() => _AdminReportsPageState();
}

class AdminActivityLogPage extends StatefulWidget {
  const AdminActivityLogPage({super.key});
  @override
  State<AdminActivityLogPage> createState() => _AdminActivityLogPageState();
}

class _AdminActivityLogPageState extends State<AdminActivityLogPage> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> rows = [];

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = asMap(
        await AppScope.stateOf(context).api.get('/admin/activity-logs'),
      );
      rows = asRows(result['data']);
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: load,
    child: ListView(
      padding: const EdgeInsets.all(12),
      children: [
        Text(
          'Lịch sử phân công và thay đổi trên toàn hệ thống',
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        if (loading) const LinearProgressIndicator(),
        if (error != null) _ErrorCard(message: error!, retry: load),
        if (!loading && error == null && rows.isEmpty)
          const Padding(
            padding: EdgeInsets.all(24),
            child: Text('Chưa có thao tác được ghi lại.'),
          ),
        ...rows.map((row) {
          final actor = asMap(row['user']);
          final properties = asMap(row['properties']);
          return Card(
            child: ListTile(
              leading: const Icon(Icons.history_rounded),
              title: Text(
                row['description']?.toString() ??
                    row['action']?.toString() ??
                    'Thao tác',
              ),
              subtitle: Text(
                '${actor['name'] ?? 'Tài khoản đã xóa'} · ${row['action'] ?? ''}\n${row['created_at'] ?? ''}${properties.isEmpty ? '' : '\n${properties.entries.map((e) => '${e.key}: ${e.value}').join(' · ')}'}',
              ),
              isThreeLine: true,
            ),
          );
        }),
      ],
    ),
  );
}

class _AdminReportsPageState extends State<AdminReportsPage> {
  String period = 'month';
  bool loading = true;
  String? error;
  Map<String, dynamic> data = {};

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      data = asMap(
        await AppScope.stateOf(context).api
            .get('/admin/reports/summary', query: {'period': period}),
      );
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(12),
    children: [
      DropdownButtonFormField<String>(
        initialValue: period,
        decoration: const InputDecoration(labelText: 'Kỳ báo cáo'),
        items: const [
          DropdownMenuItem(value: 'day', child: Text('Hôm nay')),
          DropdownMenuItem(value: 'week', child: Text('Tuần này')),
          DropdownMenuItem(value: 'month', child: Text('Tháng này')),
          DropdownMenuItem(value: 'year', child: Text('Năm nay')),
        ],
        onChanged: (value) {
          if (value != null) {
            period = value;
            load();
          }
        },
      ),
      if (loading) const LinearProgressIndicator(),
      if (error != null) _ErrorCard(message: error!, retry: load),
      if (data.isNotEmpty) ...[
        const SizedBox(height: 12),
        _MetricGrid(
          values: {
            'Tổng phiếu': data['total'] ?? 0,
            'Đã đóng': data['closed'] ?? 0,
            'Đã xử lý': data['resolved'] ?? 0,
            'Giờ xử lý TB': data['average_hours'] ?? 0,
            'Phiếu giáo viên': data['teacher_reports'] ?? 0,
          },
        ),
        const SizedBox(height: 14),
        Text(
          'Số phiếu theo danh mục',
          style: Theme.of(context).textTheme.titleLarge,
        ),
        ...asRows(data['categories']).map(
          (row) => ListTile(
            title: Text(row['name']?.toString() ?? ''),
            trailing: Text(row['tickets_count']?.toString() ?? '0'),
          ),
        ),
      ],
    ],
  );
}

class _AdminList extends StatefulWidget {
  const _AdminList({required this.kind});
  final String kind;
  @override
  State<_AdminList> createState() => _AdminListState();
}

class _AdminListState extends State<_AdminList> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> rows = [];
  final searchController = TextEditingController();
  String search = '';
  int page = 1;
  bool hasMore = false;
  bool loadingMore = false;

  String get endpoint => switch (widget.kind) {
    'categories' => '/admin/categories',
    'users' => '/admin/users',
    'team' => '/admin/team',
    _ => '/admin/buildings',
  };

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      page = 1;
      final payload = asMap(
        await AppScope.stateOf(context).api.get(
          endpoint,
          query: {
            'per_page': 100,
            'page': page,
            if ((widget.kind == 'users' || widget.kind == 'team') &&
                search.isNotEmpty)
              'search': search,
          },
        ),
      );
      rows = asRows(payload);
      hasMore = payload['next_page_url'] != null;
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> loadMore() async {
    if (!hasMore || loadingMore) return;
    setState(() => loadingMore = true);
    try {
      final nextPage = page + 1;
      final payload = asMap(
        await AppScope.stateOf(context).api.get(
          endpoint,
          query: {
            'per_page': 100,
            'page': nextPage,
            if ((widget.kind == 'users' || widget.kind == 'team') &&
                search.isNotEmpty)
              'search': search,
          },
        ),
      );
      rows.addAll(asRows(payload));
      page = nextPage;
      hasMore = payload['next_page_url'] != null;
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    } finally {
      if (mounted) setState(() => loadingMore = false);
    }
  }

  @override
  void dispose() {
    searchController.dispose();
    super.dispose();
  }

  Future<void> create() async {
    if (widget.kind == 'categories') return editCategory();
    if (widget.kind == 'buildings') return editBuilding();
    if (widget.kind == 'team') return createTeam();
    toast(context, 'Dùng chức năng đăng ký hoặc tạo tài khoản đội ngũ.');
  }

  Future<void> editCategory([Map<String, dynamic>? row]) async {
    final name = TextEditingController(text: row?['name']?.toString() ?? '');
    final color = TextEditingController(
      text: row?['color']?.toString() ?? '#1887D5',
    );
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: Text(row == null ? 'Thêm danh mục' : 'Sửa danh mục'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: name,
              decoration: const InputDecoration(labelText: 'Tên'),
            ),
            TextField(
              controller: color,
              decoration: const InputDecoration(
                labelText: 'Màu HEX, ví dụ #1887D5',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Lưu'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      final body = {'name': name.text.trim(), 'color': color.text.trim()};
      if (row == null) {
        await AppScope.stateOf(context).api
            .post('/admin/categories', body: body);
      } else {
        await AppScope.stateOf(context).api
            .put('/admin/categories/' + row['id'].toString(), body: body);
      }
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> deleteCategory(Map<String, dynamic> row) async {
    try {
      await AppScope.stateOf(context).api
          .delete('/admin/categories/' + row['id'].toString());
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> createTeam() async {
    final name = TextEditingController();
    final email = TextEditingController();
    final phone = TextEditingController();
    final password = TextEditingController();
    String role = 'staff';
    final selectedCategories = <String>{};
    final categories = asRows(
      asMap(
        await AppScope.stateOf(context).api.get('/admin/categories'),
      )['data'],
    );
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: const Text('Tạo tài khoản đội ngũ'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Họ tên'),
                ),
                TextField(
                  controller: email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Email'),
                ),
                TextField(
                  controller: phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'Điện thoại'),
                ),
                TextField(
                  controller: password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Mật khẩu'),
                ),
                DropdownButtonFormField<String>(
                  initialValue: role,
                  decoration: const InputDecoration(labelText: 'Vai trò'),
                  items: const [
                    DropdownMenuItem(
                      value: 'staff',
                      child: Text('Kỹ thuật viên'),
                    ),
                    DropdownMenuItem(
                      value: 'admin',
                      child: Text('Quản trị viên'),
                    ),
                  ],
                  onChanged: (value) => update(() => role = value ?? 'staff'),
                ),
                if (role == 'staff') ...[
                  const SizedBox(height: 10),
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text('Danh mục chuyên môn'),
                  ),
                  Wrap(
                    spacing: 6,
                    children: categories.map((item) {
                      final id = item['id'].toString();
                      return FilterChip(
                        label: Text(item['name']?.toString() ?? ''),
                        selected: selectedCategories.contains(id),
                        onSelected: (selected) => update(() {
                          if (selected) {
                            selectedCategories.add(id);
                          } else {
                            selectedCategories.remove(id);
                          }
                        }),
                      );
                    }).toList(),
                  ),
                ],
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Tạo'),
            ),
          ],
        ),
      ),
    );
    if (ok != true) return;
    if (role == 'staff' && selectedCategories.isEmpty) {
      toast(context, 'Chọn ít nhất một danh mục chuyên môn.');
      return;
    }
    try {
      await AppScope.stateOf(context).api.post(
        '/admin/team',
        body: {
          'name': name.text.trim(),
          'email': email.text.trim(),
          'phone': phone.text.trim(),
          'password': password.text,
          'role': role,
          if (role == 'staff') 'category_ids': selectedCategories.toList(),
        },
      );
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> editBuilding([Map<String, dynamic>? row]) async {
    final code = TextEditingController(text: row?['code']?.toString() ?? '');
    final name = TextEditingController(text: row?['name']?.toString() ?? '');
    final description = TextEditingController(
      text: row?['description']?.toString() ?? '',
    );
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: Text(row == null ? 'Thêm tòa nhà' : 'Sửa tòa nhà'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: code,
              decoration: const InputDecoration(labelText: 'Mã tòa'),
            ),
            TextField(
              controller: name,
              decoration: const InputDecoration(labelText: 'Tên'),
            ),
            TextField(
              controller: description,
              decoration: const InputDecoration(labelText: 'Mô tả'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Lưu'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      final body = {
        'code': code.text.trim(),
        'name': name.text.trim(),
        'description': description.text.trim(),
      };
      if (row == null) {
        await AppScope.stateOf(context).api
            .post('/admin/buildings', body: body);
      } else {
        await AppScope.stateOf(context).api
            .put('/admin/buildings/' + row['id'].toString(), body: body);
      }
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> addRoom(Map<String, dynamic> building) async {
    final code = TextEditingController();
    final name = TextEditingController();
    final floor = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: Text('Thêm phòng - ' + (building['name']?.toString() ?? '')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: code,
              decoration: const InputDecoration(labelText: 'Mã phòng'),
            ),
            TextField(
              controller: name,
              decoration: const InputDecoration(labelText: 'Tên phòng'),
            ),
            TextField(
              controller: floor,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Tầng (tùy chọn)'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Thêm phòng'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await AppScope.stateOf(context).api.post(
        '/admin/buildings/' + building['id'].toString() + '/rooms',
        body: {
          'code': code.text.trim(),
          'name': name.text.trim(),
          if (floor.text.trim().isNotEmpty) 'floor': int.tryParse(floor.text),
        },
      );
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> deleteBuilding(Map<String, dynamic> building) async {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Xóa tòa nhà?'),
        content: const Text(
          'Xóa tòa nhà sẽ xóa các phòng thuộc tòa nhà theo API.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Xóa'),
          ),
        ],
      ),
    );
    if (accepted != true) return;
    try {
      await AppScope.stateOf(context).api
          .delete('/admin/buildings/' + building['id'].toString());
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> editRoom(Map<String, dynamic> room) async {
    final code = TextEditingController(text: room['code']?.toString() ?? '');
    final name = TextEditingController(text: room['name']?.toString() ?? '');
    final floor = TextEditingController(text: room['floor']?.toString() ?? '');
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Sửa phòng'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: code,
              decoration: const InputDecoration(labelText: 'Mã phòng'),
            ),
            TextField(
              controller: name,
              decoration: const InputDecoration(labelText: 'Tên phòng'),
            ),
            TextField(
              controller: floor,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Tầng'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Lưu'),
          ),
        ],
      ),
    );
    if (accepted != true) return;
    try {
      await AppScope.stateOf(context).api.put(
        '/admin/rooms/' + room['id'].toString(),
        body: {
          'code': code.text.trim(),
          'name': name.text.trim(),
          if (floor.text.trim().isNotEmpty) 'floor': int.tryParse(floor.text),
        },
      );
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> deleteRoom(Map<String, dynamic> room) async {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialog) => AlertDialog(
        title: const Text('Xóa phòng?'),
        content: Text('Xóa phòng ' + (room['name']?.toString() ?? '') + '?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialog, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialog, true),
            child: const Text('Xóa'),
          ),
        ],
      ),
    );
    if (accepted != true) return;
    try {
      await AppScope.stateOf(context).api
          .delete('/admin/rooms/' + room['id'].toString());
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> editStaff(Map<String, dynamic> staff) async {
    final name = TextEditingController(text: staff['name']?.toString() ?? '');
    final phone = TextEditingController(text: staff['phone']?.toString() ?? '');
    final password = TextEditingController();
    final allCategories = asRows(
      asMap(
        await AppScope.stateOf(context).api.get('/admin/categories'),
      )['data'],
    );
    final selected = asRows(staff['categories'])
        .map((c) => c['id'].toString())
        .toSet();
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: Text('Sửa nhân viên: ' + (staff['name']?.toString() ?? '')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: name,
                  decoration: const InputDecoration(labelText: 'Họ tên'),
                ),
                TextField(
                  controller: phone,
                  decoration: const InputDecoration(labelText: 'Điện thoại'),
                ),
                TextField(
                  controller: password,
                  obscureText: true,
                  decoration: const InputDecoration(
                    labelText: 'Mật khẩu mới (để trống nếu giữ nguyên)',
                  ),
                ),
                const SizedBox(height: 12),
                const Text('Danh mục chuyên môn'),
                Wrap(
                  spacing: 6,
                  children: allCategories.map((category) {
                    final id = category['id'].toString();
                    return FilterChip(
                      label: Text(category['name']?.toString() ?? ''),
                      selected: selected.contains(id),
                      onSelected: (value) => update(() {
                        if (value) {
                          selected.add(id);
                        } else {
                          selected.remove(id);
                        }
                      }),
                    );
                  }).toList(),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Lưu'),
            ),
          ],
        ),
      ),
    );
    if (accepted != true) return;
    try {
      await AppScope.stateOf(context).api.put(
        '/admin/staff/' + staff['id'].toString(),
        body: {
          'name': name.text.trim(),
          'phone': phone.text.trim(),
          if (password.text.isNotEmpty) 'password': password.text,
          'category_ids': selected.toList(),
        },
      );
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> updateUserRole(Map<String, dynamic> row) async {
    String role = row['role']?.toString() ?? 'student';
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialog) => StatefulBuilder(
        builder: (context, update) => AlertDialog(
          title: Text('Vai trò: ' + (row['name']?.toString() ?? '')),
          content: DropdownButtonFormField<String>(
            value: role,
            items: const [
              DropdownMenuItem(value: 'student', child: Text('Sinh viên')),
              DropdownMenuItem(value: 'teacher', child: Text('Giảng viên')),
              DropdownMenuItem(value: 'staff', child: Text('Kỹ thuật viên')),
              DropdownMenuItem(value: 'admin', child: Text('Quản trị viên')),
            ],
            onChanged: (v) => update(() => role = v ?? role),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Hủy'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Lưu'),
            ),
          ],
        ),
      ),
    );
    if (ok != true) return;
    try {
      await AppScope.stateOf(context).api.patch(
        '/admin/users/' + row['id'].toString() + '/role',
        body: {'role': role},
      );
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  Future<void> toggleUser(Map<String, dynamic> row) async {
    try {
      await AppScope.stateOf(context).api.patch(
        '/admin/users/' + row['id'].toString() + '/status',
        body: {
          'is_deleted': !(row['is_deleted'] == true || row['is_deleted'] == 1),
        },
      );
      await load();
    } catch (e) {
      if (mounted) toast(context, readableError(e));
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.transparent,
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: load,
            child: ListView(
              padding: const EdgeInsets.all(10),
              children: [
                TextField(
                  controller: searchController,
                  decoration: const InputDecoration(
                    prefixIcon: Icon(Icons.search),
                    labelText: 'Tìm kiếm',
                  ),
                  onChanged: (value) {
                    if (widget.kind == 'categories' ||
                        widget.kind == 'buildings')
                      setState(() => search = value.trim().toLowerCase());
                  },
                  onSubmitted: (value) {
                    if (widget.kind == 'users' || widget.kind == 'team') {
                      search = value.trim();
                      load();
                    }
                  },
                ),
                const SizedBox(height: 8),

                if (error != null) _ErrorCard(message: error!, retry: load),
                ...rows
                    .where((row) {
                      if (widget.kind == 'users' ||
                          widget.kind == 'team' ||
                          search.isEmpty)
                        return true;
                      final text =
                          (row['name']?.toString() ?? '') +
                          ' ' +
                          (row['code']?.toString() ?? '');
                      return text.toLowerCase().contains(search);
                    })
                    .map((row) {
                      if (widget.kind == 'categories')
                        return Card(
                          child: ListTile(
                            title: Text(row['name']?.toString() ?? ''),
                            subtitle: Text(
                              'Phiếu: ' +
                                  (row['tickets_count']?.toString() ?? '0') +
                                  ' · Thiết bị: ' +
                                  (row['equipment_count']?.toString() ?? '0'),
                            ),
                            trailing: PopupMenuButton<String>(
                              onSelected: (v) => v == 'edit'
                                  ? editCategory(row)
                                  : deleteCategory(row),
                              itemBuilder: (_) => const [
                                PopupMenuItem(
                                  value: 'edit',
                                  child: Text('Sửa'),
                                ),
                                PopupMenuItem(
                                  value: 'delete',
                                  child: Text('Xóa'),
                                ),
                              ],
                            ),
                          ),
                        );
                      if (widget.kind == 'users')
                        return Card(
                          child: ListTile(
                            title: Text(row['name']?.toString() ?? ''),
                            subtitle: Text(
                              (row['email']?.toString() ?? '') +
                                  ' · ' +
                                  roleLabel(row['role']) +
                                  ' · Phiếu báo ' +
                                  (row['reported_tickets_count']?.toString() ??
                                      '0'),
                            ),
                            onTap: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => AdminPersonDetailPage(
                                  person: row,
                                  team: false,
                                ),
                              ),
                            ),
                            trailing: PopupMenuButton<String>(
                              onSelected: (v) => v == 'role'
                                  ? updateUserRole(row)
                                  : toggleUser(row),
                              itemBuilder: (_) => [
                                const PopupMenuItem(
                                  value: 'role',
                                  child: Text('Đổi vai trò'),
                                ),
                                PopupMenuItem(
                                  value: 'status',
                                  child: Text(
                                    row['is_deleted'] == true
                                        ? 'Mở khóa'
                                        : 'Khóa',
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      if (widget.kind == 'team')
                        return Card(
                          child: ListTile(
                            leading: const Icon(Icons.engineering_outlined),
                            title: Text(row['name']?.toString() ?? ''),
                            subtitle: Text(
                              (row['email']?.toString() ?? '') +
                                  ' · ' +
                                  roleLabel(row['role']) +
                                  ' · Lịch ' +
                                  (row['schedules_count']?.toString() ?? ''),
                            ),
                            onTap: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => AdminPersonDetailPage(
                                  person: row,
                                  team: true,
                                ),
                              ),
                            ),
                            trailing: PopupMenuButton<String>(
                              onSelected: (value) => value == 'edit'
                                  ? editStaff(row)
                                  : toggleUser(row),
                              itemBuilder: (_) => [
                                if (row['role'] == 'staff')
                                  const PopupMenuItem(
                                    value: 'edit',
                                    child: Text('Sửa nhân viên'),
                                  ),
                                PopupMenuItem(
                                  value: 'status',
                                  child: Text(
                                    (row['is_deleted'] == true ||
                                            row['is_deleted'] == 1)
                                        ? 'Mở khóa tài khoản'
                                        : 'Khóa tài khoản',
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );

                      return Card(
                        child: ExpansionTile(
                          title: Text(row['name']?.toString() ?? ''),
                          subtitle: Text(
                            (row['code']?.toString() ?? '') +
                                ' · ' +
                                (row['rooms_count']?.toString() ?? '0') +
                                ' phòng',
                          ),
                          trailing: PopupMenuButton<String>(
                            onSelected: (value) {
                              if (value == 'edit') editBuilding(row);
                              if (value == 'delete') deleteBuilding(row);
                            },
                            itemBuilder: (_) => const [
                              PopupMenuItem(
                                value: 'edit',
                                child: Text('Sửa tòa nhà'),
                              ),
                              PopupMenuItem(
                                value: 'delete',
                                child: Text('Xóa tòa nhà'),
                              ),
                            ],
                          ),
                          children: [
                            ListTile(
                              leading: const Icon(Icons.info_outline),
                              title: const Text('Chi tiết tòa nhà'),
                              onTap: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) =>
                                      AdminBuildingDetailPage(building: row),
                                ),
                              ),
                            ),
                            ...asRows(row['rooms']).map(
                              (room) => ListTile(
                                leading: const Icon(
                                  Icons.meeting_room_outlined,
                                ),
                                title: Text(room['name']?.toString() ?? ''),
                                subtitle: Text(
                                  'Mã QR: ' +
                                      (room['qr_code']?.toString() ??
                                          room['code']?.toString() ??
                                          ''),
                                ),
                                onTap: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        AdminRoomDetailPage(room: room),
                                  ),
                                ),
                                trailing: PopupMenuButton<String>(
                                  onSelected: (value) {
                                    if (value == 'edit') editRoom(room);
                                    if (value == 'delete') deleteRoom(room);
                                  },
                                  itemBuilder: (_) => const [
                                    PopupMenuItem(
                                      value: 'edit',
                                      child: Text('Sửa phòng'),
                                    ),
                                    PopupMenuItem(
                                      value: 'delete',
                                      child: Text('Xóa phòng'),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            TextButton.icon(
                              onPressed: () => addRoom(row),
                              icon: const Icon(Icons.add),
                              label: const Text('Thêm phòng'),
                            ),
                          ],
                        ),
                      );
                    }),
                if (hasMore)
                  Center(
                    child: TextButton(
                      onPressed: loadingMore ? null : loadMore,
                      child: Text(loadingMore ? 'Đang tải...' : 'Tải thêm'),
                    ),
                  ),
                if (rows.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(child: Text('Chưa có dữ liệu.')),
                  ),
              ],
            ),
          ),
    floatingActionButton: widget.kind == 'users'
        ? null
        : FloatingActionButton(onPressed: create, child: const Icon(Icons.add)),
  );
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({required this.schedule});
  final Map<String, dynamic> schedule;
  @override
  Widget build(BuildContext context) {
    final ticket = asMap(schedule['ticket']);
    final room = asMap(ticket['room']);
    return Card(
      child: ListTile(
        leading: const Icon(Icons.event_available_outlined),
        title: Text(ticket['ticket_number']?.toString() ?? 'Lịch xử lý'),
        subtitle: Text(
          displayDateTime(schedule['starts_at']) +
              ' · ' +
              (room['name']?.toString() ??
                  ticket['room_label']?.toString() ??
                  ''),
        ),
        trailing: _StatusChip(
          status: schedule['status']?.toString() ?? 'scheduled',
        ),
      ),
    );
  }
}

class AdminPersonDetailPage extends StatefulWidget {
  const AdminPersonDetailPage({
    super.key,
    required this.person,
    required this.team,
  });
  final Map<String, dynamic> person;
  final bool team;
  @override
  State<AdminPersonDetailPage> createState() => _AdminPersonDetailPageState();
}

class _AdminPersonDetailPageState extends State<AdminPersonDetailPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> detail = {};
  List<Map<String, dynamic>> tickets = [];
  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    final id = widget.person['id'].toString();
    final root = widget.team ? '/admin/team/' : '/admin/users/';
    try {
      final responses = await Future.wait([
        AppScope.stateOf(context).api.get(root + id),
        AppScope.stateOf(context).api
            .get(root + id + '/tickets', query: {'per_page': 50}),
      ]);
      detail = asMap(asMap(responses[0])['data']);
      tickets = asRows(responses[1]);
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) {
    final user = asMap(detail['user']).isEmpty
        ? widget.person
        : asMap(detail['user']);
    final categories = asRows(user['categories']);
    return Scaffold(
      appBar: AppBar(
        title: Text(user['name']?.toString() ?? 'Account details'),
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (error != null) _ErrorCard(message: error!, retry: load),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user['email']?.toString() ?? '',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          if (user['phone'] != null)
                            Text(user['phone'].toString()),
                          Text('Vai trò: ${roleLabel(user['role'])}'),
                          if (categories.isNotEmpty)
                            Wrap(
                              spacing: 6,
                              children: categories
                                  .map(
                                    (c) => Chip(
                                      label: Text(c['name']?.toString() ?? ''),
                                    ),
                                  )
                                  .toList(),
                            ),
                        ],
                      ),
                    ),
                  ),
                  _MetricGrid(
                    values: {
                      'Reported': sumCounts(detail['reported_counts']),
                      'Assigned': sumCounts(detail['assigned_counts']),
                      'Schedules':
                          detail['schedules_count'] ??
                          user['schedules_count'] ??
                          0,
                    },
                  ),
                  const SizedBox(height: 12),
                  Text(
                    widget.team ? 'Assigned tickets' : 'Reported tickets',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  if (tickets.isEmpty)
                    const Padding(
                      padding: EdgeInsets.all(18),
                      child: Text('No tickets.'),
                    ),
                  ...tickets.map((ticket) => _TicketTile(ticket: ticket)),
                ],
              ),
            ),
    );
  }
}

class AdminRoomDetailPage extends StatefulWidget {
  const AdminRoomDetailPage({super.key, required this.room});
  final Map<String, dynamic> room;
  @override
  State<AdminRoomDetailPage> createState() => _AdminRoomDetailPageState();
}

class _AdminRoomDetailPageState extends State<AdminRoomDetailPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> detail = {};
  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = asMap(
        await AppScope.stateOf(context).api
            .get('/admin/rooms/' + widget.room['id'].toString()),
      );
      detail = asMap(response['data']);
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  Future<Uint8List> qrPdf(String url, Map<String, dynamic> room) async {
    final document = pw.Document();
    document.addPage(
      pw.Page(
        build: (context) => pw.Center(
          child: pw.Column(
            children: [
              pw.Text('HELPDESK ROOM ' + (room['code']?.toString() ?? '')),
              pw.SizedBox(height: 24),
              pw.BarcodeWidget(
                barcode: pw.Barcode.qrCode(),
                data: url,
                width: 260,
                height: 260,
                drawText: false,
              ),
              pw.SizedBox(height: 16),
              pw.Text(url),
            ],
          ),
        ),
      ),
    );
    return document.save();
  }

  Future<void> printQr(String url, Map<String, dynamic> room) async {
    try {
      await Printing.layoutPdf(onLayout: (_) => qrPdf(url, room));
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    }
  }

  Future<void> shareQr(String url, Map<String, dynamic> room) async {
    try {
      final fileName =
          'helpdesk-room-' + (room['code']?.toString() ?? 'room') + '.pdf';
      await Printing.sharePdf(
        bytes: await qrPdf(url, room),
        filename: fileName,
      );
    } catch (error) {
      if (mounted) toast(context, readableError(error));
    }
  }

  @override
  Widget build(BuildContext context) {
    final room = asMap(detail['room']).isEmpty
        ? widget.room
        : asMap(detail['room']);
    final building = asMap(room['building']);
    final reportUrl = detail['report_url']?.toString() ?? '';
    final ticketRows = asRows(detail['tickets']);
    final equipment = asRows(room['equipment']);
    final url = reportUrl.isEmpty
        ? ''
        : AppScope.stateOf(context).api.resolveServerUrl(reportUrl);
    return Scaffold(
      appBar: AppBar(title: Text(room['name']?.toString() ?? 'Room details')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (error != null) _ErrorCard(message: error!, retry: load),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            (room['code']?.toString() ?? '') +
                                ' - ' +
                                (building['name']?.toString() ?? ''),
                          ),
                          if (room['floor'] != null)
                            Text('Floor: ' + room['floor'].toString()),
                          if (room['qr_code'] != null)
                            Text('Room code: ' + room['qr_code'].toString()),
                        ],
                      ),
                    ),
                  ),
                  if (url.isNotEmpty)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            const Text(
                              'Scan to open the issue report page for this room',
                            ),
                            const SizedBox(height: 10),
                            Center(
                              child: QrImageView(
                                data: url,
                                version: QrVersions.auto,
                                size: 210,
                              ),
                            ),
                            SelectableText(url, textAlign: TextAlign.center),
                            const SizedBox(height: 12),
                            Wrap(
                              spacing: 8,
                              children: [
                                OutlinedButton.icon(
                                  onPressed: () => printQr(url, room),
                                  icon: const Icon(Icons.print_outlined),
                                  label: const Text('In mã QR'),
                                ),
                                FilledButton.icon(
                                  onPressed: () => shareQr(url, room),
                                  icon: const Icon(Icons.download_outlined),
                                  label: const Text('Lưu PDF mã QR'),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  if (equipment.isNotEmpty) ...[
                    Text(
                      'Equipment',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    ...equipment.map(
                      (item) => ListTile(
                        leading: const Icon(Icons.build_outlined),
                        title: Text(item['name']?.toString() ?? 'Equipment'),
                        subtitle: Text(item['code']?.toString() ?? ''),
                      ),
                    ),
                  ],
                  Text(
                    'Room tickets (' + ticketRows.length.toString() + ')',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  if (ticketRows.isEmpty)
                    const Padding(
                      padding: EdgeInsets.all(18),
                      child: Text('No tickets.'),
                    ),
                  ...ticketRows.map((ticket) => _TicketTile(ticket: ticket)),
                ],
              ),
            ),
    );
  }
}

class AdminBuildingDetailPage extends StatefulWidget {
  const AdminBuildingDetailPage({super.key, required this.building});
  final Map<String, dynamic> building;
  @override
  State<AdminBuildingDetailPage> createState() =>
      _AdminBuildingDetailPageState();
}

class _AdminBuildingDetailPageState extends State<AdminBuildingDetailPage> {
  bool loading = true;
  String? error;
  Map<String, dynamic> building = {};
  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = asMap(
        await AppScope.stateOf(context).api
            .get('/admin/buildings/' + widget.building['id'].toString()),
      );
      building = asMap(response['data']);
    } catch (e) {
      error = readableError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => load());
  }

  @override
  Widget build(BuildContext context) {
    final item = building.isEmpty ? widget.building : building;
    final rooms = asRows(item['rooms']);
    return Scaffold(
      appBar: AppBar(
        title: Text(item['name']?.toString() ?? 'Chi tiết tòa nhà'),
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (error != null) _ErrorCard(message: error!, retry: load),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item['name']?.toString() ?? '',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                          Text('Code: ' + (item['code']?.toString() ?? '')),
                          if (item['description'] != null)
                            Text(item['description'].toString()),
                          Text(
                            'Rooms: ' +
                                (item['rooms_count']?.toString() ??
                                    rooms.length.toString()),
                          ),
                        ],
                      ),
                    ),
                  ),
                  ...rooms.map(
                    (room) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.meeting_room_outlined),
                        title: Text(room['name']?.toString() ?? ''),
                        subtitle: Text(
                          (room['code']?.toString() ?? '') +
                              ' - Tickets: ' +
                              (room['tickets_count']?.toString() ?? '0'),
                        ),
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => AdminRoomDetailPage(room: room),
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
