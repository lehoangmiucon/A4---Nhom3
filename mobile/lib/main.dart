import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'auth_api.dart';
import 'ticket_pages.dart';
import 'customer_pages.dart';
import 'staff_pages.dart';
import 'admin_pages.dart';

const green = Color(0xFF087F50);
const pale = Color(0xFFE9F3EF);
void main() => runApp(const HelpdeskApp());

class HelpdeskApp extends StatelessWidget {
  const HelpdeskApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Helpdesk UTH',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: green),
      scaffoldBackgroundColor: const Color(0xFFF5F8F7),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.white,
        foregroundColor: Color(0xFF19332D),
        surfaceTintColor: Colors.transparent,
        systemOverlayStyle: SystemUiOverlayStyle.dark,
      ),
      textTheme: const TextTheme(
        bodyMedium: TextStyle(color: Color(0xFF19332D), fontSize: 14),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        hintStyle: const TextStyle(color: Color(0xFF7A8F88), fontSize: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: Color(0xFFDCE6E1)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: Color(0xFFDCE6E1)),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: green,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(50),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: green),
      ),
    ),
    home: const LoginPage(),
  );
}

enum AuthScreen { login, register, otp, verified, forgot, reset }

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});
  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final api = AuthApi();
  final form = GlobalKey<FormState>();
  final email = TextEditingController(),
      password = TextEditingController(),
      name = TextEditingController(),
      code = TextEditingController(),
      phone = TextEditingController(),
      otp = TextEditingController(),
      confirmation = TextEditingController(),
      resetToken = TextEditingController();
  AuthScreen screen = AuthScreen.login;
  String memberType = 'student';
  bool busy = false, hidden = true;
  String? error, notice;
  Map<String, dynamic>? user;
  @override
  void dispose() {
    for (final controller in [
      email,
      password,
      name,
      code,
      phone,
      otp,
      confirmation,
      resetToken,
    ]) {
      controller.dispose();
    }
    api.client.close();
    super.dispose();
  }

  void go(AuthScreen next) {
    if (busy) return;
    FocusScope.of(context).unfocus();
    setState(() {
      screen = next;
      error = null;
      notice = null;
      password.clear();
      confirmation.clear();
      form.currentState?.reset();
    });
  }

  Future<void> perform(
    Future<void> Function() action, {
    bool validate = true,
  }) async {
    if (busy || (validate && !(form.currentState?.validate() ?? false))) return;
    FocusScope.of(context).unfocus();
    setState(() {
      busy = true;
      error = null;
      notice = null;
    });
    try {
      await action();
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

  Future<void> submit() => perform(() async {
    final address = email.text.trim();
    switch (screen) {
      case AuthScreen.login:
        final result = await api.login(address, password.text);
        if (mounted) {
          setState(() {
            user = result;
            password.clear();
          });
        }
      case AuthScreen.register:
        await api.post('auth/register', {
          'name': name.text.trim(),
          'email': address,
          'password': password.text,
          'password_confirmation': confirmation.text,
          'member_type': memberType,
          if (memberType == 'student') 'member_code': code.text.trim(),
          'phone': phone.text.trim(),
        });
        if (mounted) {
          setState(() {
            screen = AuthScreen.otp;
            password.clear();
            confirmation.clear();
            otp.clear();
          });
        }
      case AuthScreen.otp:
        await api.post('auth/verify-otp', {
          'email': address,
          'otp': otp.text.trim(),
        });
        if (mounted) {
          setState(() {
            screen = AuthScreen.verified;
            otp.clear();
          });
        }
      case AuthScreen.forgot:
        final result = await api.post('auth/forgot-password', {
          'email': address,
        });
        if (mounted) {
          setState(() {
            notice = result['message'] as String;
            screen = AuthScreen.reset;
            resetToken.clear();
          });
        }
      case AuthScreen.reset:
        await api.post('auth/reset-password', {
          'email': address,
          'token': resetToken.text.trim(),
          'password': password.text,
          'password_confirmation': confirmation.text,
        });
        if (mounted) {
          setState(() {
            screen = AuthScreen.login;
            password.clear();
            confirmation.clear();
            resetToken.clear();
            notice = 'Đã đổi mật khẩu. Đăng nhập bằng mật khẩu mới.';
          });
        }
      case AuthScreen.verified:
        break;
    }
  });
  Future<void> resend() => perform(() async {
    final result = await api.post('auth/resend-otp', {
      'email': email.text.trim(),
    });
    if (mounted) {
      setState(() {
        notice = result['message'] as String;
      });
    }
  }, validate: false);
  Future<void> logout() => perform(() async {
    await api.logout();
    if (mounted) {
      setState(() {
        user = null;
        screen = AuthScreen.login;
      });
    }
  }, validate: false);

  String get title => switch (screen) {
    AuthScreen.login => 'Đăng nhập',
    AuthScreen.register => 'Đăng ký',
    AuthScreen.otp => 'Xác minh email',
    AuthScreen.verified => 'Email đã xác minh',
    AuthScreen.forgot => 'Quên mật khẩu',
    AuthScreen.reset => 'Mật khẩu mới',
  };
  String get subtitle => switch (screen) {
    AuthScreen.login => 'Tài khoản UTH',
    AuthScreen.register =>
      memberType == 'lecturer' ? 'Giảng viên' : 'Sinh viên',
    AuthScreen.otp => 'Đăng ký · Bước 2',
    AuthScreen.verified => 'Trạng thái tài khoản',
    _ => 'Khôi phục truy cập',
  };
  Widget signedInHome() {
    void updated(Map<String, dynamic> profile) => setState(() {
      user = profile;
    });
    Future<void> changedPassword() async {
      await api.storage.delete(key: 'access_token');
      if (mounted) {
        setState(() {
          user = null;
          screen = AuthScreen.login;
          password.clear();
          notice = 'Đã đổi mật khẩu. Vui lòng đăng nhập lại.';
        });
      }
    }

    if (user!['role'] == 'admin') {
      return AdminShell(
        api: api,
        user: user!,
        onLogout: logout,
        onUpdated: updated,
        onPasswordChanged: changedPassword,
        busy: busy,
        error: error,
      );
    }
    if (user!['role'] == 'staff') {
      return StaffShell(
        api: api,
        user: user!,
        onLogout: logout,
        onUpdated: updated,
        onPasswordChanged: changedPassword,
        busy: busy,
        error: error,
      );
    }
    return CustomerShell(
      api: api,
      user: user!,
      onLogout: logout,
      onUpdated: updated,
      onPasswordChanged: changedPassword,
      busy: busy,
      error: error,
    );
  }

  @override
  Widget build(BuildContext context) => user != null
      ? signedInHome()
      : PopScope(
          canPop: screen == AuthScreen.login && !busy,
          onPopInvokedWithResult: (didPop, result) {
            if (!didPop && !busy && user == null) go(AuthScreen.login);
          },
          child: Scaffold(
            appBar: AppBar(
              toolbarHeight: 82,
              automaticallyImplyLeading: false,
              leading: user == null && screen != AuthScreen.login
                  ? IconButton(
                      tooltip: 'Quay lại đăng nhập',
                      onPressed: busy ? null : () => go(AuthScreen.login),
                      icon: const Icon(Icons.arrow_back),
                    )
                  : null,
              title: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    user == null ? title : 'Helpdesk UTH',
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    user == null ? subtitle : 'Tài khoản UTH',
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF7A8F88),
                    ),
                  ),
                ],
              ),
              actions: [
                if (user != null)
                  IconButton(
                    tooltip: 'Đăng xuất',
                    onPressed: busy ? null : logout,
                    icon: const Icon(Icons.logout),
                  ),
              ],
            ),
            body: user != null
                ? Column(
                    children: [
                      if (error != null) info(error!, isError: true),
                      Expanded(
                        child: TicketHome(api: api, user: user!),
                      ),
                    ],
                  )
                : SafeArea(
                    child: Center(
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 480),
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(20),
                          child: Form(
                            key: form,
                            child: AutofillGroup(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: content(),
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
          ),
        );
  Widget banner(String heading, String detail) => Container(
    padding: const EdgeInsets.all(20),
    margin: const EdgeInsets.only(bottom: 16),
    decoration: BoxDecoration(
      color: const Color(0xFF075D43),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          '◆  HELPDESK UTH',
          style: TextStyle(
            color: Color(0xFFBDEEDC),
            fontWeight: FontWeight.bold,
            fontSize: 12,
          ),
        ),
        const SizedBox(height: 16),
        Text(
          heading,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 23,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 12),
        Text(
          detail,
          style: const TextStyle(color: Color(0xFFD2F2E6), fontSize: 13),
        ),
      ],
    ),
  );
  Widget info(String text, {bool isError = false}) => Container(
    margin: const EdgeInsets.symmetric(vertical: 10),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: isError ? const Color(0xFFFFECEB) : pale,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Text(
      text,
      style: TextStyle(
        color: isError ? const Color(0xFF9E2424) : const Color(0xFF19332D),
        fontSize: 13,
      ),
    ),
  );
  Widget heading(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 18, top: 4),
    child: Text(
      text,
      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
    ),
  );
  Widget field(
    String label,
    TextEditingController controller,
    String hint, {
    bool secret = false,
    TextInputType? keyboard,
    String? Function(String?)? validate,
    List<TextInputFormatter>? formatters,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
        ),
        const SizedBox(height: 8),
        TextFormField(
          key: ValueKey('${screen.name}-$label'),
          controller: controller,
          enabled: !busy,
          obscureText: secret && hidden,
          keyboardType: keyboard,
          inputFormatters: formatters,
          autocorrect: !secret,
          decoration: InputDecoration(
            hintText: hint,
            suffixIcon: secret
                ? IconButton(
                    tooltip: hidden ? 'Hiện mật khẩu' : 'Ẩn mật khẩu',
                    onPressed: () => setState(() {
                      hidden = !hidden;
                    }),
                    icon: Icon(
                      hidden
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                      size: 20,
                    ),
                  )
                : null,
          ),
          validator:
              validate ??
              (value) => value == null || value.trim().isEmpty
                  ? 'Vui lòng nhập $label.'
                  : null,
        ),
      ],
    ),
  );
  String? validEmail(String? value) =>
      value == null ||
          !RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(value.trim())
      ? 'Nhập địa chỉ email hợp lệ.'
      : null;
  String? validPassword(String? value) => value == null || value.length < 8
      ? 'Mật khẩu cần tối thiểu 8 ký tự.'
      : null;
  Widget primary(String text, VoidCallback action) => Padding(
    padding: const EdgeInsets.only(top: 4, bottom: 10),
    child: FilledButton(
      onPressed: busy ? null : action,
      child: busy
          ? const SizedBox(
              height: 20,
              width: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : Text(text, style: const TextStyle(fontWeight: FontWeight.bold)),
    ),
  );
  Widget secondary(String text, VoidCallback action) => Padding(
    padding: const EdgeInsets.only(bottom: 4),
    child: FilledButton(
      style: FilledButton.styleFrom(
        backgroundColor: pale,
        foregroundColor: green,
      ),
      onPressed: busy ? null : action,
      child: Text(text, style: const TextStyle(fontWeight: FontWeight.bold)),
    ),
  );
  List<Widget> content() {
    final messages = <Widget>[
      if (error != null) info(error!, isError: true),
      if (notice != null) info(notice!),
    ];
    switch (screen) {
      case AuthScreen.login:
        return [
          banner('Hệ thống hỗ trợ sự cố UTH', 'Báo sự cố · Theo dõi · Hỗ trợ'),
          heading('Đăng nhập'),
          field(
            'Email hoặc mã sinh viên',
            email,
            'Nhập email hoặc mã sinh viên',
          ),
          field(
            'Mật khẩu',
            password,
            'Nhập mật khẩu',
            secret: true,
            validate: (v) =>
                v == null || v.isEmpty ? 'Nhập mật khẩu của bạn.' : null,
          ),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton(
              onPressed: busy ? null : () => go(AuthScreen.forgot),
              child: const Text('Quên mật khẩu?'),
            ),
          ),
          ...messages,
          primary('Đăng nhập', submit),
          secondary('Đăng ký tài khoản', () => go(AuthScreen.register)),
          TextButton(
            onPressed: busy ? null : () => go(AuthScreen.otp),
            child: const Text('Đã đăng ký nhưng chưa xác minh email?'),
          ),
        ];
      case AuthScreen.register:
        return [
          heading(
            memberType == 'student'
                ? 'Tạo tài khoản sinh viên'
                : 'Tạo tài khoản giảng viên',
          ),
          const Text('Vai trò', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          DropdownButtonFormField<String>(
            menuMaxHeight: 240,
            itemHeight: 48,
            borderRadius: BorderRadius.circular(14),
            dropdownColor: Colors.white,
            elevation: 3,
            isExpanded: true,
            style: const TextStyle(fontSize: 14, color: Color(0xFF19332D)),
            icon: const Icon(Icons.keyboard_arrow_down_rounded, size: 20),
            initialValue: memberType,
            items: const [
              DropdownMenuItem(value: 'student', child: Text('Sinh viên')),
              DropdownMenuItem(value: 'lecturer', child: Text('Giảng viên')),
            ],
            onChanged: busy
                ? null
                : (value) => setState(() {
                    memberType = value!;
                    if (memberType == 'lecturer') code.clear();
                    error = null;
                  }),
          ),
          const SizedBox(height: 16),
          field('Họ và tên', name, 'Nhập họ tên'),
          if (memberType == 'student')
            field('Mã sinh viên', code, 'Nhập mã sinh viên'),
          field(
            'Email',
            email,
            'Email trường @ut.edu.vn',
            keyboard: TextInputType.emailAddress,
            validate: (v) =>
                validEmail(v) ??
                (v!.trim().toLowerCase().endsWith('@ut.edu.vn')
                    ? null
                    : 'Sử dụng email trường @ut.edu.vn.'),
          ),
          field(
            'Số điện thoại (không bắt buộc)',
            phone,
            'Nhập số điện thoại',
            keyboard: TextInputType.phone,
            validate: (v) =>
                (v == null ||
                    v.trim().isEmpty ||
                    RegExp(r'^\+?[0-9]{9,15}$').hasMatch(v.trim()))
                ? null
                : 'Nhập số điện thoại từ 9–15 chữ số.',
          ),
          field(
            'Mật khẩu',
            password,
            'Tối thiểu 8 ký tự',
            secret: true,
            validate: validPassword,
          ),
          field(
            'Xác nhận mật khẩu',
            confirmation,
            'Nhập lại mật khẩu',
            secret: true,
            validate: (v) => v != password.text || v == null || v.isEmpty
                ? 'Mật khẩu xác nhận chưa khớp.'
                : null,
          ),
          ...messages,
          primary('Tiếp tục', submit),
        ];
      case AuthScreen.otp:
        return [
          banner('Kiểm tra email', 'Mã OTP dùng để xác thực email'),
          field(
            'Email tài khoản',
            email,
            'Email đã đăng ký',
            keyboard: TextInputType.emailAddress,
            validate: validEmail,
          ),
          field(
            'Mã xác minh',
            otp,
            'Nhập mã OTP gồm 6 chữ số',
            keyboard: TextInputType.number,
            formatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(6),
            ],
            validate: (v) => v?.length == 6 ? null : 'Nhập đủ 6 chữ số.',
          ),
          info('Mã có thời hạn và giới hạn lần thử theo cấu hình server.'),
          ...messages,
          primary('Xác minh email', submit),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton(
              onPressed: busy
                  ? null
                  : () {
                      if (validEmail(email.text) != null) {
                        setState(() {
                          error =
                              'Nhập địa chỉ email hợp lệ trước khi gửi lại mã.';
                        });
                      } else {
                        resend();
                      }
                    },
              child: const Text('Gửi lại mã'),
            ),
          ),
        ];
      case AuthScreen.verified:
        return [
          banner(
            'Email đã xác minh',
            'Tài khoản đã sẵn sàng sử dụng Helpdesk UTH',
          ),
          info('Bạn có thể đăng nhập và gửi báo cáo sự cố ngay.'),
          primary('Quay lại đăng nhập', () => go(AuthScreen.login)),
        ];
      case AuthScreen.forgot:
        return [
          heading('Đặt lại mật khẩu'),
          field(
            'Email tài khoản',
            email,
            'Nhập email',
            keyboard: TextInputType.emailAddress,
            validate: validEmail,
          ),
          ...messages,
          primary('Gửi mã đặt lại', submit),
          info('Kết quả được thông báo chung để bảo vệ thông tin tài khoản.'),
          TextButton(
            onPressed: busy ? null : () => go(AuthScreen.reset),
            child: const Text('Tôi đã có mã đặt lại'),
          ),
        ];
      case AuthScreen.reset:
        return [
          field(
            'Email tài khoản',
            email,
            'Nhập email',
            keyboard: TextInputType.emailAddress,
            validate: validEmail,
          ),
          field(
            'Mã xác minh',
            resetToken,
            'Nhập 6 chữ cái trong email',
            formatters: [
              FilteringTextInputFormatter.allow(RegExp('[a-zA-Z]')),
              LengthLimitingTextInputFormatter(6),
            ],
            validate: (v) =>
                v?.length == 6 ? null : 'Nhập đủ 6 chữ cái trong email.',
          ),
          field(
            'Mật khẩu mới',
            password,
            'Tối thiểu 8 ký tự',
            secret: true,
            validate: validPassword,
          ),
          field(
            'Xác nhận mật khẩu',
            confirmation,
            'Nhập lại mật khẩu',
            secret: true,
            validate: (v) => v != password.text || v == null || v.isEmpty
                ? 'Mật khẩu xác nhận chưa khớp.'
                : null,
          ),
          ...messages,
          primary('Đổi mật khẩu', submit),
          TextButton(
            onPressed: busy ? null : () => go(AuthScreen.forgot),
            child: const Text('Gửi lại mã đặt lại'),
          ),
        ];
    }
  }
}
