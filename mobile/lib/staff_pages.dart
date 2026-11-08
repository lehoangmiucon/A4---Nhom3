import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'auth_api.dart';
import 'account_pages.dart';
import 'ticket_pages.dart';
import 'customer_pages.dart' show customerBanner, customerInfo;
import 'notifications_page.dart';

String staffStatusLabel(dynamic status) => status == 'resolved' ? 'Chờ quản lý xác nhận' : status == 'closed' ? 'Hoàn tất' : statusLabel(status);

class StaffShell extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> user;
  final VoidCallback onLogout;
  final ValueChanged<Map<String, dynamic>> onUpdated;
  final Future<void> Function() onPasswordChanged;
  final bool busy;
  final String? error;
  const StaffShell({
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
  State<StaffShell> createState() => _StaffShellState();
}

class _StaffShellState extends State<StaffShell> {
  int tab = 0, revision = 0;
  Future<void> open(int id) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => StaffProcessPage(api: widget.api, id: id),
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
            ['Công việc kỹ thuật', 'Lịch kỹ thuật', 'Tài khoản KTV'][tab],
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          const Text(
            'Kỹ thuật viên',
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
      child: tab == 2
          ? AccountTab(
              api: widget.api,
              user: widget.user,
              onUpdated: widget.onUpdated,
              onPasswordChanged: widget.onPasswordChanged,
              onLogout: widget.onLogout,
              busy: widget.busy,
              error: widget.error,
            )
          : StaffAssignments(
              key: ValueKey('$tab-$revision'),
              api: widget.api,
              scheduleOnly: tab == 1,
              onOpen: open,
            ),
    ),
    bottomNavigationBar: NavigationBar(
      selectedIndex: tab,
      onDestinationSelected: (value) => setState(() {
        tab = value;
      }),
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.work_outline),
          label: 'Công việc',
        ),
        NavigationDestination(
          icon: Icon(Icons.calendar_month_outlined),
          label: 'Lịch',
        ),
        NavigationDestination(
          icon: Icon(Icons.person_outline),
          label: 'Tài khoản',
        ),
      ],
    ),
  );
}

class StaffAssignments extends StatefulWidget {
  final AuthApi api;
  final bool scheduleOnly;
  final ValueChanged<int> onOpen;
  const StaffAssignments({
    super.key,
    required this.api,
    this.scheduleOnly = false,
    required this.onOpen,
  });
  @override
  State<StaffAssignments> createState() => _StaffAssignmentsState();
}

class _StaffAssignmentsState extends State<StaffAssignments> {
  List<Map<String, dynamic>> tickets = [], schedules = [];
  int page = 0, lastPage = 1;
  String filter = '';
  bool loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    load(reset: true);
  }

  Future<void> load({bool reset = false}) async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await widget.api.get(
        'staff/tickets?page=${reset ? 1 : page + 1}${filter.isEmpty ? '' : '&status=$filter'}',
      );
      final list = reset ? await widget.api.get('staff/schedules') : null;
      if (mounted) {
        setState(() {
          if (reset) {
            tickets.clear();
            schedules = (list!['data'] as List)
                .map((e) => Map<String, dynamic>.from(e as Map))
                .toList();
          }
          tickets.addAll(
            (result['data'] as List).map(
              (e) => Map<String, dynamic>.from(e as Map),
            ),
          );
          page = result['current_page'] as int;
          lastPage = result['last_page'] as int;
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

  String group(Map<String, dynamic> s) {
    final time = DateTime.tryParse('${s['starts_at']}')?.toLocal();
    if (time == null) return 'Chưa xác định thời gian';
    final now = DateTime.now();
    if (time.year == now.year && time.month == now.month && time.day == now.day) {
      return 'Hôm nay';
    }
    return time.isAfter(now) ? 'Sắp tới' : 'Trước đó';
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () => load(reset: true),
    child: ListView(
      padding: const EdgeInsets.all(20),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        if (!widget.scheduleOnly) ...[
          customerBanner(
            'Danh sách được giao',
            'Theo dõi và cập nhật công việc',
          ),
          Wrap(
            spacing: 8,
            children: [
              for (final entry in {
                '': 'Tất cả',
                'assigned': 'Mới nhận',
                'in_progress': 'Đang xử lý',
              }.entries)
                ChoiceChip(
                  label: Text(entry.value),
                  selected: filter == entry.key,
                  onSelected: loading
                      ? null
                      : (_) {
                          filter = entry.key;
                          load(reset: true);
                        },
                ),
            ],
          ),
          const SizedBox(height: 12),
        ],
        if (error != null) ...[
          Text(error!, style: const TextStyle(color: Colors.red)),
          TextButton(
            onPressed: loading ? null : () => load(reset: page == 0),
            child: const Text('Thử lại'),
          ),
        ],
        if (!widget.scheduleOnly) ...[
          ...tickets.map(
            (t) => Card(
              color: Colors.white,
              elevation: 0,
              child: ListTile(
                contentPadding: const EdgeInsets.all(16),
                title: Text(
                  '${t['ticket_number']} · ${staffStatusLabel(t['status'])}',
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
                      '${t['title']}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                    Text(
                      '${t['building_label'] ?? ''} · ${t['room_label'] ?? ''}',
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Chạm để xử lý phiếu →',
                      style: TextStyle(fontSize: 12, color: Colors.blueGrey),
                    ),
                  ],
                ),
                onTap: () => widget.onOpen(t['id'] as int),
              ),
            ),
          ),
          if (!loading && tickets.isEmpty && error == null)
            customerInfo('Chưa có công việc trong danh sách này.'),
          if (!loading && page < lastPage)
            TextButton(
              onPressed: () => load(),
              child: const Text('Xem thêm công việc'),
            ),
          ticketHeading('Hôm nay'),
          if (!loading && !schedules.any((e) => group(e) == 'Hôm nay')) customerInfo('Hôm nay chưa có lịch được phân công.'),
          ...schedules.where((e) => group(e) == 'Hôm nay').map((s) => Card(color: Colors.white, elevation: 0, child: ListTile(contentPadding: const EdgeInsets.all(16), title: Text('${dateLabel(s['starts_at'])} · ${s['ticket']?['ticket_number'] ?? ''}'), subtitle: Text('${s['ticket']?['title'] ?? ''}\n${staffStatusLabel(s['ticket']?['status'])}'), trailing: const Icon(Icons.chevron_right), onTap: () => widget.onOpen(s['ticket_id'] as int)))),
        ] else ...[
          if (!loading && schedules.isEmpty && error == null)
            customerInfo('Bạn chưa có lịch xử lý được phân công.'),
          for (final label in [
            'Hôm nay',
            'Sắp tới',
            'Trước đó',
            'Chưa xác định thời gian',
          ]) ...[
            if (schedules.any((s) => group(s) == label)) ticketHeading(label),
            ...schedules
                .where((s) => group(s) == label)
                .map(
                  (s) => Card(
                    color: Colors.white,
                    elevation: 0,
                    child: ListTile(
                      contentPadding: const EdgeInsets.all(16),
                      title: Text(
                        '${dateLabel(s['starts_at'])} · ${s['ticket']?['ticket_number'] ?? ''}',
                      ),
                      subtitle: Text(
                        '${s['ticket']?['title'] ?? ''}\n${s['ticket']?['room']?['building']?['name'] ?? ''} · ${s['ticket']?['room']?['name'] ?? ''}\n${s['status'] == 'acknowledged' ? 'Đã xác nhận' : 'Chờ xác nhận'}',
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => widget.onOpen(s['ticket_id'] as int),
                    ),
                  ),
                ),
          ],
        ],
        if (loading)
          const Padding(
            padding: EdgeInsets.all(24),
            child: Center(child: CircularProgressIndicator()),
          ),
      ],
    ),
  );
}

class StaffProcessPage extends StatefulWidget {
  final AuthApi api;
  final int id;
  const StaffProcessPage({super.key, required this.api, required this.id});
  @override
  State<StaffProcessPage> createState() => _StaffProcessPageState();
}

class _StaffProcessPageState extends State<StaffProcessPage> {
  final note = TextEditingController();
  final form = GlobalKey<FormState>();
  final List<XFile> images = [];
  Map<String, dynamic>? ticket;
  String? target;
  String? error;
  bool loading = true, busy = false;
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
      final result = await widget.api.get('staff/tickets/${widget.id}');
      if (mounted) {
        setState(() {
          ticket = Map<String, dynamic>.from(result['data'] as Map);
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

  Future<void> acknowledge(int id) async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.patch(
        'staff/schedules/$id/acknowledge',
        {},
        method: 'POST',
      );
      await load();
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

  Future<void> pick(ImageSource source) async {
    if (busy) return;
    if (images.length >= 5) {
      setState(() {
        error = 'Chỉ thêm tối đa 5 ảnh.';
      });
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final image = await ImagePicker().pickImage(
        source: source,
        imageQuality: 85,
        maxWidth: 2400,
      );
      if (image != null) {
        if (await image.length() > 5 * 1024 * 1024) {
          throw Exception('Ảnh phải nhỏ hơn hoặc bằng 5 MB.');
        }
        if (mounted) {
          setState(() {
            images.add(image);
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          error = e is Exception && e.toString().contains('5 MB')
              ? 'Ảnh phải nhỏ hơn hoặc bằng 5 MB.'
              : 'Không mở được ảnh/camera. Kiểm tra quyền rồi thử lại.';
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
    if (!(ticket?['staff_schedules'] as List? ?? []).any((s) => s['status'] == 'acknowledged')) {
      setState(() { error = 'Xác nhận lịch xử lý trước khi cập nhật tiến độ.'; });
      return;
    }
    if (target == 'resolved' && images.isEmpty) {
      setState(() {
        error = 'Thêm ảnh minh chứng sau sửa chữa để gửi kết quả.';
      });
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.createTicket(
        {'status': target!, 'note': note.text.trim(), 'explicit_status': '1'},
        images,
        path: 'staff/tickets/${widget.id}/status',
        successStatus: 200,
      );
      if (mounted) {
        note.clear();
        images.clear();
        await load();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                target == 'resolved'
                    ? 'Đã gửi kết quả, chờ quản lý xác nhận.'
                    : 'Đã cập nhật tiến độ.',
              ),
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          error =
              '${errorLabel(e)}\nNếu kết nối gián đoạn, tải lại phiếu để kiểm tra trước khi gửi lại.';
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
  Widget build(BuildContext context) {
    final schedules = ticket?['staff_schedules'] as List? ?? [];
    final editable =
        ticket != null &&
        ['assigned', 'reopened', 'in_progress'].contains(ticket!['status']);
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Xử lý phiếu'),
            if (ticket != null)
              Text(
                'Kỹ thuật · ${ticket!['ticket_number']}',
                style: const TextStyle(fontSize: 12, color: Colors.blueGrey),
              ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Làm mới',
            onPressed: busy ? null : load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(20),
              children: [
                if (error != null) ...[
                  Text(error!, style: const TextStyle(color: Colors.red)),
                  const SizedBox(height: 12),
                ],
                if (ticket == null)
                  TextButton(onPressed: load, child: const Text('Thử lại'))
                else ...[
                  ticketPanel('Sự cố', '${ticket!['title']}'),
                  ticketPanel('Người báo', '${ticket!['reporter_name']}'),
                  ticketPanel(
                    'Vị trí',
                    '${ticket!['building_label'] ?? ''} · ${ticket!['room_label'] ?? ''}',
                  ),
                  ticketPanel('Mô tả', '${ticket!['description']}'),
                  ticketPanel('Trạng thái', staffStatusLabel(ticket!['status'])),
                  for (final s in schedules) ...[
                    ticketPanel(
                      'Lịch xử lý · ${s['status'] == 'acknowledged' ? 'Đã xác nhận' : 'Chờ xác nhận'}',
                      dateLabel(s['starts_at']),
                    ),
                    if (editable && s['status'] != 'acknowledged')
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: FilledButton.tonal(
                          onPressed: busy
                              ? null
                              : () => acknowledge(s['id'] as int),
                          child: const Text('Xác nhận lịch và bắt đầu xử lý'),
                        ),
                      ),
                  ],
                  if (editable && schedules.isEmpty)
                    customerInfo(
                      'Chưa có lịch xử lý. Vui lòng liên hệ quản lý để được xếp lịch.',
                    ),
                  if (editable)
                    Form(
                      key: form,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          ticketHeading('Trạng thái mới'),
                          DropdownButtonFormField<String>(
                            initialValue: target,
                            hint: const Text('Chọn trạng thái cập nhật'),
                            validator: (v) => v == null ? 'Chọn trạng thái trước khi cập nhật.' : null,
                            dropdownColor: Colors.white,
                            borderRadius: BorderRadius.circular(14),
                            menuMaxHeight: 200,
                            items: const [
                              DropdownMenuItem(
                                value: 'in_progress',
                                child: Text('Đang xử lý'),
                              ),
                              DropdownMenuItem(
                                value: 'resolved',
                                child: Text('Gửi kết quả hoàn thành'),
                              ),
                            ],
                            onChanged: busy
                                ? null
                                : (v) => setState(() {
                                    target = v!;
                                  }),
                          ),
                          ticketHeading('Ghi chú kỹ thuật'),
                          TextFormField(
                            controller: note,
                            enabled: !busy,
                            maxLines: 4,
                            maxLength: 3000,
                            decoration: const InputDecoration(
                              hintText: 'Mô tả kết quả kiểm tra hoặc xử lý',
                            ),
                            validator: (v) => v == null || v.trim().isEmpty
                                ? 'Nhập ghi chú kỹ thuật.'
                                : null,
                          ),
                          ...[
                            ticketHeading('Minh chứng xử lý'),
                            const Text('Tối đa 5 ảnh, mỗi ảnh không quá 5 MB.'),
                            Wrap(
                              spacing: 8,
                              children: [
                                TextButton.icon(
                                  onPressed: busy
                                      ? null
                                      : () => pick(ImageSource.gallery),
                                  icon: const Icon(Icons.photo_outlined),
                                  label: const Text('Chọn ảnh'),
                                ),
                                TextButton.icon(
                                  onPressed: busy
                                      ? null
                                      : () => pick(ImageSource.camera),
                                  icon: const Icon(Icons.camera_alt_outlined),
                                  label: const Text('Chụp ảnh'),
                                ),
                              ],
                            ),
                            ...images.map(
                              (image) => ListTile(
                                leading: Image.file(
                                  File(image.path),
                                  width: 48,
                                  height: 48,
                                  fit: BoxFit.cover,
                                ),
                                title: Text(
                                  image.name,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                trailing: IconButton(
                                  tooltip: 'Bỏ ảnh',
                                  onPressed: busy
                                      ? null
                                      : () => setState(() {
                                          images.remove(image);
                                        }),
                                  icon: const Icon(Icons.close),
                                ),
                              ),
                            ),
                          ],
                          FilledButton(
                            onPressed: busy ? null : save,
                            child: Text(
                              busy ? 'Đang cập nhật…' : 'Cập nhật tiến độ',
                            ),
                          ),
                        ],
                      ),
                    ),
                  if (!editable)
                    ticketPanel(
                      'Kết quả xử lý',
                      '${ticket!['resolution_note'] ?? 'Chưa có ghi chú kết quả.'}',
                    ),
                ],
              ],
            ),
    );
  }
}
