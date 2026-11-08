import 'dart:async';

import 'package:flutter/material.dart';

import 'auth_api.dart';
import 'account_pages.dart';
import 'ticket_pages.dart';
import 'notifications_page.dart';
import 'customer_pages.dart' show customerBanner, customerInfo, reportDropdown;
import 'admin_users_page.dart';

String adminStatus(Map<String, dynamic> ticket) =>
    ticket['status'] == 'resolved'
    ? 'Chờ xác nhận hoàn thành'
    : ticket['status'] == 'new'
    ? (ticket['triage_status'] == 'approved'
          ? 'Cần phân công'
          : ticket['triage_status'] == 'needs_info'
          ? 'Chờ bổ sung'
          : 'Chờ duyệt')
    : statusLabel(ticket['status']);
Widget adminTicketTile(Map<String, dynamic> ticket, VoidCallback onTap) => Card(
  color: Colors.white,
  elevation: 0,
  margin: const EdgeInsets.only(bottom: 12),
  child: ListTile(
    contentPadding: const EdgeInsets.all(16),
    title: Text(
      '${ticket['ticket_number']} · ${adminStatus(ticket)}',
      style: const TextStyle(
        color: ticketGreen,
        fontSize: 12,
        fontWeight: FontWeight.bold,
      ),
    ),
    subtitle: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 8),
        Text(
          '${ticket['title']}',
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 6),
        Text(
          '${ticket['building_label'] ?? ''} · ${ticket['room_label'] ?? ''}',
          style: const TextStyle(fontSize: 12, color: Colors.blueGrey),
        ),
      ],
    ),
    trailing: const Icon(Icons.chevron_right),
    onTap: onTap,
  ),
);

class AdminShell extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> user;
  final VoidCallback onLogout;
  final ValueChanged<Map<String, dynamic>> onUpdated;
  final Future<void> Function() onPasswordChanged;
  final bool busy;
  final String? error;
  const AdminShell({
    super.key,
    required this.api,
    required this.user,
    required this.onLogout,
    required this.onUpdated,
    required this.onPasswordChanged,
    this.busy = false,
    this.error,
  });
  @override
  State<AdminShell> createState() => _AdminShellState();
}

class _AdminShellState extends State<AdminShell> {
  int tab = 0, revision = 0;
  String queue = '';
  Future<void> open(int id) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => AdminReviewPage(api: widget.api, id: id),
      ),
    );
    if (mounted) {
      setState(() {
        revision++;
      });
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            [
              'Quản trị tổng quan',
              'Duyệt phiếu',
              'Người dùng',
              'Tài khoản quản trị',
            ][tab],
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          const Text(
            'Quản trị viên',
            style: TextStyle(fontSize: 12, color: Colors.blueGrey),
          ),
        ],
      ),
      actions: [
        IconButton(
          tooltip: 'Thông báo',
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => NotificationsPage(api: widget.api),
            ),
          ),
          icon: const Icon(Icons.notifications_outlined),
        ),
      ],
    ),
    body: SafeArea(
      child: switch (tab) {
        0 => AdminOverview(
          key: ValueKey(revision),
          api: widget.api,
          onOpen: open,
          onQueue: (status) => setState(() {
            queue = status;
            tab = 1;
          }),
        ),
        1 => AdminQueue(
          key: ValueKey('queue-$queue-$revision'),
          api: widget.api,
          initialStatus: queue,
          onOpen: open,
        ),
        2 => AdminUsersPage(
          api: widget.api,
          currentUserId: widget.user['id'] as int,
        ),
        _ => AccountTab(
          api: widget.api,
          user: widget.user,
          onUpdated: widget.onUpdated,
          onPasswordChanged: widget.onPasswordChanged,
          onLogout: widget.onLogout,
          busy: widget.busy,
          error: widget.error,
        ),
      },
    ),
    bottomNavigationBar: NavigationBar(
      selectedIndex: tab,
      onDestinationSelected: (v) => setState(() {
        tab = v;
        if (v == 1) queue = '';
      }),
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.dashboard_outlined),
          label: 'Tổng quan',
        ),
        NavigationDestination(
          icon: Icon(Icons.fact_check_outlined),
          label: 'Duyệt',
        ),
        NavigationDestination(
          icon: Icon(Icons.group_outlined),
          label: 'Người dùng',
        ),
        NavigationDestination(
          icon: Icon(Icons.person_outline),
          label: 'Tài khoản',
        ),
      ],
    ),
  );
}

class AdminOverview extends StatefulWidget {
  final AuthApi api;
  final ValueChanged<int> onOpen;
  final ValueChanged<String> onQueue;
  const AdminOverview({
    super.key,
    required this.api,
    required this.onOpen,
    required this.onQueue,
  });
  @override
  State<AdminOverview> createState() => _AdminOverviewState();
}

class _AdminOverviewState extends State<AdminOverview> {
  Map<String, dynamic>? summary;
  List<dynamic> tickets = [];
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final results = await Future.wait([
        widget.api.get('admin/summary'),
        widget.api.get('admin/tickets?queue=action'),
      ]);
      if (mounted) {
        setState(() {
          summary = Map<String, dynamic>.from(results[0]['data'] as Map);
          tickets = (results[1]['data'] as List).take(5).toList();
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

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: load,
    child: ListView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        customerBanner(
          'Điều hành helpdesk',
          'Tập trung xử lý các phiếu cần hành động',
        ),
        if (error != null) ...[
          Text(error!, style: const TextStyle(color: Colors.red)),
          TextButton(onPressed: load, child: const Text('Thử lại')),
        ],
        if (summary != null) ...[
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              ActionChip(
                label: Text('Chờ duyệt ${summary!['pending_review']}'),
                onPressed: () => widget.onQueue('new'),
              ),
              ActionChip(
                label: Text('Cần phân công ${summary!['needs_assignment']}'),
                onPressed: () => widget.onQueue('assignment'),
              ),
              ActionChip(
                label: Text('Chờ xác nhận ${summary!['pending_confirmation']}'),
                onPressed: () => widget.onQueue('resolved'),
              ),
            ],
          ),
          ticketHeading('Phiếu cần xử lý'),
          ...tickets.map(
            (t) => adminTicketTile(
              Map<String, dynamic>.from(t as Map),
              () => widget.onOpen(t['id'] as int),
            ),
          ),
          if (tickets.isEmpty && !loading)
            customerInfo(
              'Hiện chưa có phiếu cần duyệt, phân công hoặc xác nhận.',
            ),
        ],
        if (loading) const Center(child: CircularProgressIndicator()),
      ],
    ),
  );
}

class AdminQueue extends StatefulWidget {
  final AuthApi api;
  final ValueChanged<int> onOpen;
  final String initialStatus;
  const AdminQueue({
    super.key,
    required this.api,
    required this.onOpen,
    this.initialStatus = '',
  });
  @override
  State<AdminQueue> createState() => _AdminQueueState();
}

class _AdminQueueState extends State<AdminQueue> {
  final search = TextEditingController();
  Timer? timer;
  List<Map<String, dynamic>> tickets = [];
  int page = 0, last = 1, generation = 0;
  late String status;
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    status = widget.initialStatus;
    load(reset: true);
  }

  @override
  void dispose() {
    timer?.cancel();
    search.dispose();
    super.dispose();
  }

  Future<void> load({bool reset = false}) async {
    final current = ++generation;
    setState(() {
      loading = true;
      error = null;
      if (reset) {
        page = 0;
        tickets.clear();
      }
    });
    try {
      final query = Uri(
        queryParameters: {
          'page': '${reset ? 1 : page + 1}',
          'search': search.text.trim(),
          if (status != 'assignment') 'status': status,
          if (status == 'assignment') 'queue': 'assignment',
        },
      ).query;
      final data = await widget.api.get('admin/tickets?$query');
      if (mounted && current == generation) {
        setState(() {
          tickets.addAll(
            (data['data'] as List).map(
              (e) => Map<String, dynamic>.from(e as Map),
            ),
          );
          page = data['current_page'] as int;
          last = data['last_page'] as int;
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

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () => load(reset: true),
    child: ListView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        TextField(
          controller: search,
          maxLength: 180,
          decoration: const InputDecoration(
            hintText: 'Mã phiếu / nội dung',
            prefixIcon: Icon(Icons.search),
            counterText: '',
          ),
          onChanged: (_) {
            timer?.cancel();
            ++generation;
            timer = Timer(
              const Duration(milliseconds: 350),
              () => load(reset: true),
            );
          },
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          children: [
            for (final entry in {
              '': 'Tất cả',
              'new': 'Chờ duyệt',
              'assignment': 'Cần phân công',
              'assigned': 'Đã phân công',
              'in_progress': 'Đang xử lý',
              'resolved': 'Chờ xác nhận',
              'closed': 'Hoàn tất',
            }.entries)
              ChoiceChip(
                label: Text(entry.value),
                selected: status == entry.key,
                onSelected: (_) {
                  timer?.cancel();
                  status = entry.key;
                  load(reset: true);
                },
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (error != null) ...[
          Text(error!, style: const TextStyle(color: Colors.red)),
          TextButton(
            onPressed: loading ? null : () => load(reset: page == 0),
            child: const Text('Thử lại'),
          ),
        ],
        ...tickets.map(
          (t) => adminTicketTile(t, () => widget.onOpen(t['id'] as int)),
        ),
        if (!loading && error == null && tickets.isEmpty)
          customerInfo('Không có phiếu trong danh sách này.'),
        if (loading) const Center(child: CircularProgressIndicator()),
        if (!loading && page < last)
          TextButton(
            onPressed: () => load(),
            child: const Text('Xem thêm phiếu'),
          ),
      ],
    ),
  );
}

class AdminReviewPage extends StatefulWidget {
  final AuthApi api;
  final int id;
  const AdminReviewPage({super.key, required this.api, required this.id});
  @override
  State<AdminReviewPage> createState() => _AdminReviewPageState();
}

class _AdminReviewPageState extends State<AdminReviewPage> {
  Map<String, dynamic>? ticket;
  bool loading = true, busy = false;
  String? error;
  final note = TextEditingController();
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    note.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.api.get('admin/tickets/${widget.id}');
      if (mounted) {
        setState(() {
          ticket = Map<String, dynamic>.from(data['data'] as Map);
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

  Future<void> act(String action, {String? decision}) async {
    if (busy) return;
    if ((decision == 'needs_info' || action == 'reopen') &&
        note.text.trim().isEmpty) {
      setState(() {
        error = 'Nhập lý do hoặc nội dung cần bổ sung.';
      });
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Xác nhận thao tác'),
        content: Text(
          action == 'close'
              ? 'Xác nhận kết quả xử lý và hoàn tất phiếu này?'
              : action == 'reopen'
              ? 'Gửi yêu cầu KTV xử lý lại phiếu này?'
              : decision == 'approved'
              ? 'Duyệt thông tin để chuyển sang phân công?'
              : 'Gửi yêu cầu bổ sung thông tin cho người báo?',
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
      final data = await widget.api.patch(
        'admin/tickets/${widget.id}/$action',
        {'note': note.text.trim(), 'decision': ?decision},
        method: 'POST',
      );
      if (mounted) {
        note.clear();
        await load();
        if (mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text('${data['message']}')));
        }
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

  Widget attachments(String label, String kind) {
    final images = (ticket!['attachments'] as List? ?? [])
        .where((e) => e['attachment_type'] == kind)
        .toList();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ticketHeading(label),
        if (images.isEmpty) const Text('Chưa có ảnh.'),
        ...images.map((a) {
          final path = '${a['path']}';
          final base = AuthApi.baseUrl.replaceFirst(RegExp(r'/api/?$'), '');
          final url =
              '$base/storage/${path.split('/').map(Uri.encodeComponent).join('/')}';
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.network(
                url,
                fit: BoxFit.contain,
                height: 200,
                errorBuilder: (_, _, _) => const SizedBox(
                  height: 80,
                  child: Center(child: Text('Không tải được ảnh.')),
                ),
              ),
            ),
          );
        }),
      ],
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Duyệt phiếu')),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(20),
            children: [
              if (error != null) ...[
                Text(error!, style: const TextStyle(color: Colors.red)),
                TextButton(
                  onPressed: busy ? null : load,
                  child: const Text('Tải lại'),
                ),
              ],
              if (ticket != null) ...[
                ticketPanel(
                  '${ticket!['ticket_number']} · ${adminStatus(ticket!)}',
                  '${ticket!['title']}',
                ),
                ticketPanel('Người báo', '${ticket!['reporter_name']}'),
                ticketPanel(
                  'Vị trí',
                  '${ticket!['building_label'] ?? ''} · ${ticket!['room_label'] ?? ''}',
                ),
                ticketPanel(
                  'Mức ưu tiên',
                  const {
                        'low': 'Thấp',
                        'normal': 'Trung bình',
                        'high': 'Cao',
                        'urgent': 'Khẩn cấp',
                      }[ticket!['priority']] ??
                      'Trung bình',
                ),
                ticketPanel('Mô tả', '${ticket!['description']}'),
                attachments('Ảnh báo cáo', 'report'),
                if (ticket!['assigned_staff'] != null)
                  ticketPanel(
                    'Kỹ thuật viên',
                    '${ticket!['assigned_staff']['name']}',
                  ),
                if (['resolved', 'closed'].contains(ticket!['status'])) ...[
                  ticketPanel(
                    'Kết quả xử lý',
                    '${ticket!['resolution_note'] ?? ''}',
                  ),
                  attachments('Ảnh xử lý', 'repair'),
                ],
                if (ticket!['status'] == 'new' ||
                    ['resolved', 'closed'].contains(ticket!['status'])) ...[
                  ticketHeading('Ghi chú'),
                  TextField(
                    controller: note,
                    enabled: !busy,
                    maxLines: 3,
                    maxLength: 2000,
                    decoration: const InputDecoration(
                      hintText: 'Nội dung cần bổ sung hoặc lý do xử lý lại',
                    ),
                  ),
                ],
                if (ticket!['status'] == 'new') ...[
                  if (ticket!['triage_status'] != 'approved')
                    FilledButton(
                      onPressed: busy
                          ? null
                          : () => act('triage', decision: 'approved'),
                      child: const Text('Duyệt phiếu'),
                    ),
                  const SizedBox(height: 12),
                  FilledButton.tonal(
                    onPressed: busy
                        ? null
                        : () => act('triage', decision: 'needs_info'),
                    child: const Text('Yêu cầu bổ sung'),
                  ),
                  const SizedBox(height: 12),
                ],
                if ((ticket!['status'] == 'new' &&
                        ticket!['triage_status'] == 'approved') ||
                    ['assigned', 'reopened'].contains(ticket!['status']))
                  FilledButton(
                    onPressed: busy
                        ? null
                        : () async {
                            await Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => AdminAssignPage(
                                  api: widget.api,
                                  ticket: ticket!,
                                ),
                              ),
                            );
                            if (mounted) load();
                          },
                    child: const Text('Phân công xử lý'),
                  ),
                if (ticket!['status'] == 'resolved')
                  FilledButton(
                    onPressed: busy ? null : () => act('close'),
                    child: const Text('Xác nhận hoàn thành'),
                  ),
                if (['resolved', 'closed'].contains(ticket!['status']))
                  Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: OutlinedButton(
                      onPressed: busy ? null : () => act('reopen'),
                      child: const Text('Yêu cầu xử lý lại'),
                    ),
                  ),
                const SizedBox(height: 16),
                OutlinedButton(
                  onPressed: busy
                      ? null
                      : () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) =>
                                TicketDetail(api: widget.api, id: widget.id),
                          ),
                        ),
                  child: const Text('Xem lịch sử phiếu'),
                ),
              ],
            ],
          ),
  );
}

class AdminAssignPage extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> ticket;
  const AdminAssignPage({super.key, required this.api, required this.ticket});
  @override
  State<AdminAssignPage> createState() => _AdminAssignPageState();
}

class _AdminAssignPageState extends State<AdminAssignPage> {
  final form = GlobalKey<FormState>();
  final note = TextEditingController();
  List<Map<String, dynamic>> staff = [];
  int? staffId;
  DateTime? scheduled;
  String priority = 'normal';
  bool loading = true, busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    priority = widget.ticket['priority']?.toString() ?? 'normal';
    load();
  }

  @override
  void dispose() {
    note.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final data = await widget.api.get('admin/staff');
      if (mounted) {
        setState(() {
          staff = (data['data'] as List)
              .map((e) => Map<String, dynamic>.from(e as Map))
              .where(
                (e) =>
                    widget.ticket['category_id'] == null ||
                    (e['categories'] as List? ?? []).any(
                      (c) => c['id'] == widget.ticket['category_id'],
                    ),
              )
              .toList();
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

  Future<void> selectTime() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: now.add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(now.add(const Duration(hours: 1))),
    );
    if (time != null && mounted) {
      setState(() {
        scheduled = DateTime(
          date.year,
          date.month,
          date.day,
          time.hour,
          time.minute,
        );
      });
    }
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate()) return;
    if (scheduled == null || !scheduled!.isAfter(DateTime.now())) {
      setState(() {
        error = 'Chọn lịch xử lý ở thời điểm tương lai.';
      });
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.patch('admin/tickets/${widget.ticket['id']}/assign', {
        'staff_id': staffId,
        'priority': priority,
        'scheduled_at': scheduled!.toUtc().toIso8601String(),
        'note': note.text.trim(),
      }, method: 'POST');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã phân công và tạo lịch xử lý.')),
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
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Phân công xử lý')),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(20),
            children: [
              ticketPanel(
                'Phiếu',
                '${widget.ticket['ticket_number']} · ${widget.ticket['title']}',
              ),
              if (error != null)
                Text(error!, style: const TextStyle(color: Colors.red)),
              if (staff.isEmpty)
                customerInfo(
                  'Chưa có KTV phù hợp danh mục. Cập nhật danh mục xử lý cho KTV tại trang Người dùng.',
                ),
              Form(
                key: form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ticketHeading('Kỹ thuật viên'),
                    reportDropdown(
                      initialValue: staffId,
                      hint: const Text('Chọn KTV phù hợp'),
                      items: staff
                          .map(
                            (e) => DropdownMenuItem(
                              value: e['id'] as int,
                              child: Text(
                                '${e['name']}',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: busy
                          ? null
                          : (v) => setState(() {
                              staffId = v;
                            }),
                      validator: (v) =>
                          v == null ? 'Chọn kỹ thuật viên.' : null,
                    ),
                    ticketHeading('Mức ưu tiên'),
                    DropdownButtonFormField<String>(
                      initialValue: priority,
                      dropdownColor: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      items: const [
                        DropdownMenuItem(value: 'low', child: Text('Thấp')),
                        DropdownMenuItem(
                          value: 'normal',
                          child: Text('Trung bình'),
                        ),
                        DropdownMenuItem(value: 'high', child: Text('Cao')),
                        DropdownMenuItem(
                          value: 'urgent',
                          child: Text('Khẩn cấp'),
                        ),
                      ],
                      onChanged: busy
                          ? null
                          : (v) => setState(() {
                              priority = v!;
                            }),
                    ),
                    ticketHeading('Lịch xử lý'),
                    OutlinedButton.icon(
                      onPressed: busy ? null : selectTime,
                      icon: const Icon(Icons.event_outlined),
                      label: Text(
                        scheduled == null
                            ? 'Chọn ngày và giờ'
                            : dateLabel(scheduled!.toIso8601String()),
                      ),
                    ),
                    ticketHeading('Ghi chú giao việc'),
                    TextFormField(
                      controller: note,
                      enabled: !busy,
                      maxLines: 3,
                      maxLength: 2000,
                      decoration: const InputDecoration(
                        hintText: 'Mô tả yêu cầu xử lý',
                      ),
                    ),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: busy || staff.isEmpty ? null : save,
                      child: Text(
                        busy ? 'Đang phân công…' : 'Xác nhận phân công',
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
  );
}
