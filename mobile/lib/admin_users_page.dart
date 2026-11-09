import 'dart:async';

import 'package:flutter/material.dart';

import 'auth_api.dart';
import 'account_pages.dart' show memberLabel;
import 'ticket_pages.dart';
import 'customer_pages.dart' show customerInfo;

String userState(Map<String, dynamic> user) => user['is_deleted'] == true
    ? 'Đã khóa'
    : user['email_verified_at'] == null
    ? 'Chưa xác minh'
    : 'Hoạt động';

class AdminUsersPage extends StatefulWidget {
  final AuthApi api;
  final int currentUserId;
  const AdminUsersPage({
    super.key,
    required this.api,
    required this.currentUserId,
  });
  @override
  State<AdminUsersPage> createState() => _AdminUsersPageState();
}

class _AdminUsersPageState extends State<AdminUsersPage> {
  final search = TextEditingController();
  Timer? debounce;
  String role = '', status = '';
  int page = 0, last = 1, total = 0, generation = 0;
  bool loading = true, opening = false;
  String? error;
  List<Map<String, dynamic>> users = [];
  @override
  void initState() {
    super.initState();
    load(reset: true);
  }

  @override
  void dispose() {
    debounce?.cancel();
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool reset = false}) async {
    final current = ++generation;
    setState(() {
      loading = true;
      error = null;
      if (reset) {
        users.clear();
        page = 0;
      }
    });
    try {
      final query = Uri(
        queryParameters: {
          'page': '${reset ? 1 : page + 1}',
          'search': search.text.trim(),
          'status': status,
          if (role.isNotEmpty) 'role': role,
        },
      ).query;
      final data = await widget.api.get('admin/users?$query');
      if (mounted && current == generation) {
        setState(() {
          users.addAll(
            (data['data'] as List).map(
              (e) => Map<String, dynamic>.from(e as Map),
            ),
          );
          page = data['current_page'] as int;
          last = data['last_page'] as int;
          total = data['total'] as int;
        });
      }
    } catch (e) {
      if (mounted && current == generation) {
        setState(() {
          error = errorLabel(e);
        });
      }
    } finally {
      if (mounted && current == generation) {
        setState(() {
          loading = false;
        });
      }
    }
  }

  Future<void> detail(Map<String, dynamic> user) async {
    if (opening) return;
    setState(() {
      opening = true;
      error = null;
    });
    try {
      final data = await widget.api.get('admin/users/${user['id']}');
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        backgroundColor: const Color(0xFFF5F8F7),
        builder: (ctx) => SafeArea(
          child: SizedBox(
            height: MediaQuery.sizeOf(ctx).height * .83,
            child: AdminUserSheet(
              api: widget.api,
              user: Map<String, dynamic>.from(data['data'] as Map),
              currentUserId: widget.currentUserId,
            ),
          ),
        ),
      );
      if (mounted) load(reset: true);
    } catch (e) {
      if (mounted) {
        setState(() {
          error = errorLabel(e);
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          opening = false;
        });
      }
    }
  }

  Future<void> create() async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => StaffEditor(api: widget.api),
    );
    if (mounted) load(reset: true);
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () => load(reset: true),
    child: ListView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Quản lý người dùng',
                    style: TextStyle(fontSize: 23, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    loading
                        ? 'Đang tải danh sách…'
                        : '$total tài khoản trong danh sách',
                    style: const TextStyle(
                      fontSize: 12,
                      color: Colors.blueGrey,
                    ),
                  ),
                ],
              ),
            ),
            IconButton.filled(
              tooltip: 'Tạo tài khoản KTV',
              onPressed: opening ? null : create,
              icon: const Icon(Icons.person_add_alt_1),
            ),
          ],
        ),
        const SizedBox(height: 20),
        TextField(
          controller: search,
          maxLength: 120,
          decoration: const InputDecoration(
            hintText: 'Mã sinh viên, họ tên hoặc email',
            prefixIcon: Icon(Icons.search),
            counterText: '',
          ),
          onChanged: (_) {
            debounce?.cancel();
            ++generation;
            debounce = Timer(
              const Duration(milliseconds: 350),
              () => load(reset: true),
            );
          },
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: role,
          isExpanded: true,
          dropdownColor: Colors.white,
          borderRadius: BorderRadius.circular(14),
          items: const [
            DropdownMenuItem(value: '', child: Text('Tất cả vai trò')),
            DropdownMenuItem(value: 'student', child: Text('Sinh viên')),
            DropdownMenuItem(value: 'teacher', child: Text('Giảng viên')),
            DropdownMenuItem(value: 'staff', child: Text('Kỹ thuật viên')),
            DropdownMenuItem(value: 'admin', child: Text('Quản trị viên')),
          ],
          onChanged: (v) {
            debounce?.cancel();
            role = v!;
            load(reset: true);
          },
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          children: [
            for (final entry in {
              '': 'Tất cả',
              'active': 'Hoạt động',
              'unverified': 'Chưa xác minh',
              'locked': 'Đã khóa',
            }.entries)
              ChoiceChip(
                label: Text(entry.value),
                selected: status == entry.key,
                onSelected: (_) {
                  debounce?.cancel();
                  status = entry.key;
                  load(reset: true);
                },
              ),
          ],
        ),
        const SizedBox(height: 16),
        if (error != null) ...[
          Text(error!, style: const TextStyle(color: Colors.red)),
          TextButton(
            onPressed: loading ? null : () => load(reset: page == 0),
            child: const Text('Thử lại'),
          ),
        ],
        ...users.map((u) {
          final locked = u['is_deleted'] == true;
          return Card(
            color: Colors.white,
            elevation: 0,
            margin: const EdgeInsets.only(bottom: 12),
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 10,
              ),
              leading: CircleAvatar(
                backgroundColor: locked
                    ? const Color(0xFFFDEDEC)
                    : const Color(0xFFE9F3EF),
                child: Icon(
                  u['role'] == 'staff'
                      ? Icons.engineering_outlined
                      : u['role'] == 'admin'
                      ? Icons.admin_panel_settings_outlined
                      : Icons.school_outlined,
                  color: locked ? Colors.red : ticketGreen,
                ),
              ),
              title: Text(
                '${u['name']}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                ),
              ),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 5),
                  Text(
                    '${u['email']}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 12),
                  ),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: [
                      Text(
                        memberLabel(u),
                        style: const TextStyle(
                          fontSize: 12,
                          color: Colors.blueGrey,
                        ),
                      ),
                      const Text('·'),
                      Text(
                        userState(u),
                        style: TextStyle(
                          fontSize: 12,
                          color: locked
                              ? Colors.red
                              : u['email_verified_at'] == null
                              ? Colors.orange.shade800
                              : ticketGreen,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              trailing: const Icon(Icons.chevron_right, size: 20),
              onTap: opening ? null : () => detail(u),
            ),
          );
        }),
        if (loading || opening)
          const Padding(
            padding: EdgeInsets.all(20),
            child: Center(child: CircularProgressIndicator()),
          ),
        if (!loading && error == null && users.isEmpty)
          customerInfo('Không tìm thấy tài khoản phù hợp.'),
        if (!loading && page < last)
          TextButton(
            onPressed: () => load(),
            child: const Text('Xem thêm tài khoản'),
          ),
      ],
    ),
  );
}

class AdminUserSheet extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> user;
  final int currentUserId;
  const AdminUserSheet({
    super.key,
    required this.api,
    required this.user,
    required this.currentUserId,
  });
  @override
  State<AdminUserSheet> createState() => _AdminUserSheetState();
}

class _AdminUserSheetState extends State<AdminUserSheet> {
  late Map<String, dynamic> user;
  bool busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    user = widget.user;
  }

  Future<void> toggle() async {
    final locking = user['is_deleted'] != true;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(locking ? 'Khóa tài khoản?' : 'Mở khóa tài khoản?'),
        content: Text(
          locking
              ? '${user['name']} sẽ không thể đăng nhập và các phiên hiện có sẽ bị thu hồi.'
              : 'Cho phép ${user['name']} đăng nhập lại?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Xác nhận'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final data = await widget.api.patch('admin/users/${user['id']}/status', {
        'is_deleted': locking,
      });
      if (mounted) {
        setState(() {
          user = {...user, ...Map<String, dynamic>.from(data['data'] as Map)};
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

  Future<void> confirmTeacher() async {
    final becomingTeacher = user['role'] != 'teacher';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(
          becomingTeacher ? 'Xác nhận giảng viên?' : 'Bỏ xác nhận giảng viên?',
        ),
        content: Text(
          'Cập nhật loại người dùng cho ${user['name']}? Tài khoản sẽ cần đăng nhập lại.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Xác nhận'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final result = await widget.api.patch(
        'admin/users/${user['id']}/teacher',
        {'is_teacher': becomingTeacher},
      );
      if (mounted) {
        setState(() {
          user = {...user, ...Map<String, dynamic>.from(result['data'] as Map)};
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

  Future<void> deleteAccount() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Xóa tài khoản?'),
        content: Text(
          'Xóa ${user['name']} khỏi danh sách và thu hồi đăng nhập? Lịch sử phiếu vẫn được giữ lại.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Xóa tài khoản'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.patch('admin/users/${user['id']}', {}, method: 'DELETE');
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Đã xóa tài khoản.')));
        Navigator.of(context).pop();
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
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
    children: [
      Text(
        '${user['name']}',
        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
      ),
      const SizedBox(height: 6),
      Text(
        '${memberLabel(user)} · ${userState(user)}',
        style: const TextStyle(color: Colors.blueGrey),
      ),
      const SizedBox(height: 20),
      ticketPanel('Loại người dùng', memberLabel(user)),
      ticketPanel('Email', '${user['email']}'),
      if (['student', 'teacher'].contains(user['role']))
        FilledButton.tonal(
          onPressed:
              busy ||
                  user['email_verified_at'] == null ||
                  user['is_deleted'] == true
              ? null
              : confirmTeacher,
          child: Text(
            user['role'] == 'teacher'
                ? 'Bỏ xác nhận giảng viên'
                : 'Xác nhận giảng viên',
          ),
        ),
      if (user['role'] == 'student')
        ticketPanel(
          'Mã sinh viên',
          '${user['member_code'] ?? 'Chưa cập nhật'}',
        ),
      ticketPanel('Số điện thoại', '${user['phone'] ?? 'Chưa cập nhật'}'),
      ticketPanel('Ngày tạo', dateLabel(user['created_at'])),
      if (user['role'] == 'staff')
        ticketPanel(
          'Danh mục xử lý',
          (user['categories'] as List? ?? [])
              .map((c) => '${c['name']}')
              .join(', '),
        ),
      if (error != null)
        Text(error!, style: const TextStyle(color: Colors.red)),
      if (user['role'] == 'staff')
        FilledButton.tonal(
          onPressed: busy
              ? null
              : () async {
                  await showModalBottomSheet<void>(
                    context: context,
                    isScrollControlled: true,
                    showDragHandle: true,
                    builder: (_) => StaffEditor(api: widget.api, user: user),
                  );
                  if (!mounted) return;
                  try {
                    final data = await widget.api.get(
                      'admin/users/${user['id']}',
                    );
                    if (mounted) {
                      setState(() {
                        user = Map<String, dynamic>.from(data['data'] as Map);
                      });
                    }
                  } catch (e) {
                    if (mounted) {
                      setState(() {
                        error = errorLabel(e);
                      });
                    }
                  }
                },
          child: const Text('Chỉnh sửa KTV và danh mục'),
        ),
      if (user['id'] != widget.currentUserId &&
          ['student', 'teacher', 'staff'].contains(user['role']))
        OutlinedButton.icon(
          style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
          onPressed: busy ? null : deleteAccount,
          icon: const Icon(Icons.delete_outline),
          label: const Text('Xóa tài khoản'),
        ),
      const SizedBox(height: 12),
      if (user['id'] != widget.currentUserId)
        OutlinedButton.icon(
          onPressed: busy ? null : toggle,
          icon: Icon(
            user['is_deleted'] == true ? Icons.lock_open : Icons.lock_outline,
          ),
          label: Text(
            user['is_deleted'] == true ? 'Mở khóa tài khoản' : 'Khóa tài khoản',
          ),
        ),
    ],
  );
}

class StaffEditor extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic>? user;
  const StaffEditor({super.key, required this.api, this.user});
  @override
  State<StaffEditor> createState() => _StaffEditorState();
}

class _StaffEditorState extends State<StaffEditor> {
  final form = GlobalKey<FormState>();
  late final TextEditingController name, email, phone;
  final password = TextEditingController();
  final Set<int> selected = {};
  List<dynamic> categories = [];
  bool loading = true, busy = false, hidden = true;
  String? error;
  @override
  void initState() {
    super.initState();
    name = TextEditingController(text: widget.user?['name']?.toString() ?? '');
    email = TextEditingController(
      text: widget.user?['email']?.toString() ?? '',
    );
    phone = TextEditingController(
      text: widget.user?['phone']?.toString() ?? '',
    );
    selected.addAll(
      (widget.user?['categories'] as List? ?? []).map((c) => c['id'] as int),
    );
    load();
  }

  @override
  void dispose() {
    name.dispose();
    email.dispose();
    phone.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final data = await widget.api.get('categories');
      if (mounted) {
        setState(() {
          categories = data['data'] as List;
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
          loading = false;
        });
      }
    }
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate()) return;
    if (selected.isEmpty) {
      setState(() {
        error = 'Chọn ít nhất một danh mục xử lý cho KTV.';
      });
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.patch(
        widget.user == null
            ? 'admin/staff'
            : 'admin/staff/${widget.user!['id']}',
        {
          'name': name.text.trim(),
          'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
          if (widget.user == null) 'email': email.text.trim().toLowerCase(),
          if (password.text.isNotEmpty) 'password': password.text,
          'category_ids': selected.toList(),
        },
        method: widget.user == null ? 'POST' : 'PUT',
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              widget.user == null
                  ? 'Đã tạo tài khoản KTV.'
                  : 'Đã cập nhật KTV.',
            ),
          ),
        );
        Navigator.of(context).pop();
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
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .85,
        child: loading
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  Text(
                    widget.user == null ? 'Tạo tài khoản KTV' : 'Chỉnh sửa KTV',
                    style: const TextStyle(
                      fontSize: 23,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 20),
                  Form(
                    key: form,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        TextFormField(
                          controller: name,
                          enabled: !busy,
                          maxLength: 120,
                          decoration: const InputDecoration(
                            labelText: 'Họ và tên',
                          ),
                          validator: (v) => v == null || v.trim().isEmpty
                              ? 'Nhập họ tên.'
                              : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: email,
                          enabled: !busy && widget.user == null,
                          keyboardType: TextInputType.emailAddress,
                          decoration: const InputDecoration(labelText: 'Email'),
                          validator: (v) =>
                              v == null ||
                                  !RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$')
                                      .hasMatch(v.trim())
                              ? 'Nhập email hợp lệ.'
                              : null,
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: phone,
                          enabled: !busy,
                          keyboardType: TextInputType.phone,
                          decoration: const InputDecoration(
                            labelText: 'Số điện thoại (tùy chọn)',
                          ),
                          validator: (v) =>
                              v == null ||
                                  v.trim().isEmpty ||
                                  RegExp(r'^\+?[0-9]{9,15}$').hasMatch(v.trim())
                              ? null
                              : 'Nhập số điện thoại hợp lệ.',
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: password,
                          enabled: !busy,
                          obscureText: hidden,
                          decoration: InputDecoration(
                            labelText: widget.user == null
                                ? 'Mật khẩu ban đầu'
                                : 'Mật khẩu mới (để trống nếu giữ nguyên)',
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
                          validator: (v) =>
                              (widget.user == null || v?.isNotEmpty == true) &&
                                  (v == null || v.length < 8)
                              ? 'Mật khẩu cần ít nhất 8 ký tự.'
                              : null,
                        ),
                        ticketHeading('Danh mục xử lý'),
                        Wrap(
                          spacing: 8,
                          runSpacing: 4,
                          children: categories
                              .map(
                                (c) => FilterChip(
                                  label: Text('${c['name']}'),
                                  selected: selected.contains(c['id']),
                                  onSelected: busy
                                      ? null
                                      : (v) => setState(() {
                                          if (v) {
                                            selected.add(c['id'] as int);
                                          } else {
                                            selected.remove(c['id']);
                                          }
                                        }),
                                ),
                              )
                              .toList(),
                        ),
                        if (error != null)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            child: Text(
                              error!,
                              style: const TextStyle(color: Colors.red),
                            ),
                          ),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: busy || categories.isEmpty ? null : save,
                          child: Text(
                            busy
                                ? 'Đang lưu…'
                                : widget.user == null
                                ? 'Tạo tài khoản KTV'
                                : 'Lưu thay đổi',
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
      ),
    ),
  );
}
