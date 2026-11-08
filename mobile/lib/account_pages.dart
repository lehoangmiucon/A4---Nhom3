import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'auth_api.dart';
import 'ticket_pages.dart';

String memberLabel(Map<String, dynamic> user) {
  if (user['role'] == 'admin') return 'Quản trị viên';
  if (user['role'] == 'staff') return 'Kỹ thuật viên';
  return user['member_type'] == 'lecturer' ? 'Giảng viên' : 'Sinh viên';
}

bool schoolAccount(Map<String, dynamic> user) => user['role'] == 'student';

class AccountTab extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> user;
  final ValueChanged<Map<String, dynamic>> onUpdated;
  final Future<void> Function() onPasswordChanged;
  final VoidCallback onLogout;
  final bool busy;
  final String? error;
  const AccountTab({
    super.key,
    required this.api,
    required this.user,
    required this.onUpdated,
    required this.onPasswordChanged,
    required this.onLogout,
    this.busy = false,
    this.error,
  });
  @override
  State<AccountTab> createState() => _AccountTabState();
}

class _AccountTabState extends State<AccountTab> {
  late Map<String, dynamic> profile;
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    profile = widget.user;
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await widget.api.get('account/profile');
      if (mounted) {
        setState(() {
          profile = Map<String, dynamic>.from(result['data'] as Map);
        });
        widget.onUpdated(profile);
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          error = errorLabel(e);
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: load,
    child: ListView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        if (loading) const Center(child: CircularProgressIndicator()),
        if (error != null) ...[
          Text(error!, style: const TextStyle(color: Colors.red)),
          TextButton(onPressed: load, child: const Text('Thử lại')),
        ],
        Container(
          padding: const EdgeInsets.all(24),
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: const Color(0xFFE9F3EF),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(
            children: [
              const Icon(
                Icons.account_circle_outlined,
                size: 52,
                color: ticketGreen,
              ),
              const SizedBox(height: 12),
              Text(
                '${profile['name']}',
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                '${memberLabel(profile)} · ${profile['email_verified_at'] != null ? 'Đã xác minh' : 'Chưa xác minh'}',
              ),
            ],
          ),
        ),
        if (schoolAccount(profile) && profile['member_type'] != 'lecturer')
          ticketPanel(
            'Mã sinh viên',
            profile['member_code']?.toString() ?? 'Chưa cập nhật',
          ),
        ticketPanel('Email', '${profile['email']}'),
        ticketPanel(
          'Số điện thoại',
          profile['phone']?.toString().isNotEmpty == true
              ? '${profile['phone']}'
              : 'Chưa cập nhật',
        ),
        FilledButton.tonal(
          onPressed: loading || error != null
              ? null
              : () async {
                  final result = await Navigator.of(context)
                      .push<Map<String, dynamic>>(
                        MaterialPageRoute(
                          builder: (_) =>
                              EditProfilePage(api: widget.api, user: profile),
                        ),
                      );
                  if (result != null && mounted) {
                    setState(() {
                      profile = result;
                    });
                    widget.onUpdated(result);
                  }
                },
          child: const Text('Chỉnh sửa hồ sơ'),
        ),
        if (schoolAccount(profile))
          Card(
            elevation: 0,
            color: Colors.white,
            margin: const EdgeInsets.symmetric(vertical: 16),
            child: ListTile(
              title: const Text('Bảo mật và mật khẩu'),
              subtitle: const Text(
                'Xác thực qua email trường',
                style: TextStyle(fontSize: 12),
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: loading || error != null
                  ? null
                  : () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => AccountSecurityPage(
                          api: widget.api,
                          email: '${profile['email']}',
                          onChanged: widget.onPasswordChanged,
                        ),
                      ),
                    ),
            ),
          ),
        if (profile['role'] == 'staff' || profile['role'] == 'admin')
          Card(
            elevation: 0,
            color: Colors.white,
            margin: const EdgeInsets.symmetric(vertical: 16),
            child: ListTile(
              title: const Text('Đổi mật khẩu'),
              trailing: const Icon(Icons.chevron_right),
              onTap: loading || error != null
                  ? null
                  : () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => StaffPasswordPage(
                          api: widget.api,
                          onChanged: widget.onPasswordChanged,
                        ),
                      ),
                    ),
            ),
          ),
        if (widget.error != null)
          Text(widget.error!, style: const TextStyle(color: Colors.red)),
        const SizedBox(height: 12),
        OutlinedButton(
          onPressed: widget.busy ? null : widget.onLogout,
          child: Text(widget.busy ? 'Đang đăng xuất…' : 'Đăng xuất'),
        ),
      ],
    ),
  );
}

class EditProfilePage extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> user;
  const EditProfilePage({super.key, required this.api, required this.user});
  @override
  State<EditProfilePage> createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<EditProfilePage> {
  late final TextEditingController name, phone, memberCode;
  bool get isStudent =>
      schoolAccount(widget.user) && widget.user['member_type'] != 'lecturer';
  final form = GlobalKey<FormState>();
  bool busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    name = TextEditingController(text: '${widget.user['name']}');
    phone = TextEditingController(text: widget.user['phone']?.toString() ?? '');
    memberCode = TextEditingController(
      text: widget.user['member_code']?.toString() ?? '',
    );
  }

  @override
  void dispose() {
    name.dispose();
    phone.dispose();
    memberCode.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final result = await widget.api.patch('account/profile', {
        'name': name.text.trim(),
        'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
        if (isStudent) 'member_code': memberCode.text.trim(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Đã lưu hồ sơ.')));
        Navigator.of(context)
            .pop(Map<String, dynamic>.from(result['data'] as Map));
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          error = errorLabel(e);
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Chỉnh sửa hồ sơ')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        ticketPanel(
          'Thông tin tài khoản',
          'Email và vai trò được giữ theo hồ sơ đăng ký.',
        ),
        Form(
          key: form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Họ và tên'),
              const SizedBox(height: 8),
              TextFormField(
                controller: name,
                enabled: !busy,
                maxLength: 120,
                decoration: const InputDecoration(hintText: 'Nhập họ tên'),
                validator: (v) =>
                    v == null || v.trim().isEmpty ? 'Nhập họ tên.' : null,
              ),
              const SizedBox(height: 16),
              if (isStudent) ...[
                const Text('Mã sinh viên'),
                const SizedBox(height: 8),
                TextFormField(
                  controller: memberCode,
                  enabled: !busy,
                  maxLength: 80,
                  decoration: const InputDecoration(
                    hintText: 'Nhập mã sinh viên',
                  ),
                  validator: (v) => v == null || v.trim().isEmpty
                      ? 'Nhập mã sinh viên.'
                      : null,
                ),
                const SizedBox(height: 16),
              ],
              const Text('Số điện thoại (không bắt buộc)'),
              const SizedBox(height: 8),
              TextFormField(
                controller: phone,
                enabled: !busy,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(hintText: 'Nhập số liên hệ'),
                validator: (v) =>
                    v == null ||
                        v.trim().isEmpty ||
                        RegExp(r'^\+?[0-9]{9,15}$').hasMatch(v.trim())
                    ? null
                    : 'Nhập số điện thoại từ 9–15 chữ số.',
              ),
              const SizedBox(height: 24),
              if (error != null)
                Text(error!, style: const TextStyle(color: Colors.red)),
              FilledButton(
                onPressed: busy ? null : save,
                child: Text(busy ? 'Đang lưu…' : 'Lưu thay đổi'),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class AccountSecurityPage extends StatefulWidget {
  final AuthApi api;
  final String email;
  final Future<void> Function() onChanged;
  const AccountSecurityPage({
    super.key,
    required this.api,
    required this.email,
    required this.onChanged,
  });
  @override
  State<AccountSecurityPage> createState() => _AccountSecurityPageState();
}

class _AccountSecurityPageState extends State<AccountSecurityPage> {
  final code = TextEditingController(),
      password = TextEditingController(),
      confirmation = TextEditingController();
  final form = GlobalKey<FormState>();
  bool busy = false, hidden = true;
  String? error, notice;
  @override
  void dispose() {
    code.dispose();
    password.dispose();
    confirmation.dispose();
    super.dispose();
  }

  Future<void> sendCode() async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
      notice = null;
    });
    try {
      await widget.api.patch('account/password-code', {}, method: 'POST');
      if (mounted) {
        setState(() {
          notice = 'Nếu đủ điều kiện, mã đã được gửi tới email trường. Mã có hạn 10 phút; kiểm tra cả thư rác. Nếu vừa gửi, đợi ít nhất 60 giây trước khi yêu cầu lại.';
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          error = errorLabel(e);
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          busy = false;
        });
      }
    }
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.patch('account/password-code', {
        'token': code.text.trim(),
        'password': password.text,
        'password_confirmation': confirmation.text,
      });
      if (!mounted) return;
      Navigator.of(context).pop();
      await widget.onChanged();
    } catch (e) {
      if (mounted) {
        setState(() {
          error = errorLabel(e);
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Bảo mật và mật khẩu')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        ticketPanel('Email xác minh', widget.email),
        FilledButton.tonal(
          onPressed: busy ? null : sendCode,
          child: const Text('Gửi mã xác minh qua email'),
        ),
        if (notice != null)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Text(notice!),
          ),
        const SizedBox(height: 20),
        Form(
          key: form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Mã xác minh'),
              const SizedBox(height: 8),
              TextFormField(
                controller: code,
                enabled: !busy,
                inputFormatters: [
                  FilteringTextInputFormatter.allow(RegExp('[a-zA-Z]')),
                  LengthLimitingTextInputFormatter(6),
                ],
                decoration: const InputDecoration(
                  hintText: 'Nhập 6 chữ cái trong email',
                ),
                validator: (v) => v?.length == 6 ? null : 'Nhập đủ 6 chữ cái.',
              ),
              const SizedBox(height: 16),
              const Text('Mật khẩu mới'),
              const SizedBox(height: 8),
              TextFormField(
                controller: password,
                enabled: !busy,
                obscureText: hidden,
                decoration: InputDecoration(
                  hintText: 'Tối thiểu 8 ký tự',
                  suffixIcon: IconButton(
                    tooltip: hidden ? 'Hiện mật khẩu' : 'Ẩn mật khẩu',
                    onPressed: () => setState(() {
                      hidden = !hidden;
                    }),
                    icon: Icon(
                      hidden
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                  ),
                ),
                validator: (v) => v == null || v.length < 8
                    ? 'Mật khẩu cần tối thiểu 8 ký tự.'
                    : null,
              ),
              const SizedBox(height: 16),
              const Text('Nhập lại mật khẩu mới'),
              const SizedBox(height: 8),
              TextFormField(
                controller: confirmation,
                enabled: !busy,
                obscureText: hidden,
                decoration: const InputDecoration(
                  hintText: 'Xác nhận mật khẩu mới',
                ),
                validator: (v) => v != password.text || v == null || v.isEmpty
                    ? 'Mật khẩu xác nhận chưa khớp.'
                    : null,
              ),
              const SizedBox(height: 20),
              if (error != null)
                Text(error!, style: const TextStyle(color: Colors.red)),
              FilledButton(
                onPressed: busy ? null : save,
                child: Text(busy ? 'Đang xử lý…' : 'Đổi mật khẩu'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const Text(
          'Sau khi đổi mật khẩu, bạn cần đăng nhập lại trên các thiết bị.',
        ),
      ],
    ),
  );
}

class StaffPasswordPage extends StatefulWidget {
  final AuthApi api;
  final Future<void> Function() onChanged;
  const StaffPasswordPage({
    super.key,
    required this.api,
    required this.onChanged,
  });
  @override
  State<StaffPasswordPage> createState() => _StaffPasswordPageState();
}

class _StaffPasswordPageState extends State<StaffPasswordPage> {
  final current = TextEditingController(),
      password = TextEditingController(),
      confirmation = TextEditingController();
  final form = GlobalKey<FormState>();
  bool busy = false, hidden = true;
  String? error;
  @override
  void dispose() {
    current.dispose();
    password.dispose();
    confirmation.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.patch('account/password', {
        'current_password': current.text,
        'password': password.text,
        'password_confirmation': confirmation.text,
      }, method: 'POST');
      if (!mounted) return;
      Navigator.of(context).pop();
      await widget.onChanged();
    } catch (e) {
      if (mounted) {
        setState(() {
          error = errorLabel(e);
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Đổi mật khẩu')),
    body: ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Form(
          key: form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (final entry in [
                ('Mật khẩu hiện tại', current),
                ('Mật khẩu mới', password),
                ('Nhập lại mật khẩu mới', confirmation),
              ]) ...[
                Text(entry.$1),
                const SizedBox(height: 8),
                TextFormField(
                  controller: entry.$2,
                  enabled: !busy,
                  obscureText: hidden,
                  decoration: InputDecoration(
                    hintText: entry.$1,
                    suffixIcon: IconButton(
                      tooltip: hidden ? 'Hiện mật khẩu' : 'Ẩn mật khẩu',
                      onPressed: () => setState(() {
                        hidden = !hidden;
                      }),
                      icon: Icon(
                        hidden
                            ? Icons.visibility_outlined
                            : Icons.visibility_off_outlined,
                      ),
                    ),
                  ),
                  validator: (v) {
                    if (v == null || v.isEmpty) {
                      return 'Vui lòng nhập ${entry.$1.toLowerCase()}.';
                    }
                    if (entry.$2 == password &&
                        (v.length < 8 || v == current.text)) {
                      return 'Mật khẩu mới cần ít nhất 8 ký tự và khác mật khẩu hiện tại.';
                    }
                    if (entry.$2 == confirmation && v != password.text) {
                      return 'Mật khẩu xác nhận chưa khớp.';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 18),
              ],
              if (error != null)
                Text(error!, style: const TextStyle(color: Colors.red)),
              FilledButton(
                onPressed: busy ? null : save,
                child: Text(busy ? 'Đang xử lý…' : 'Đổi mật khẩu'),
              ),
              const SizedBox(height: 12),
              const Text('Đổi mật khẩu thành công sẽ yêu cầu đăng nhập lại.'),
            ],
          ),
        ),
      ],
    ),
  );
}
