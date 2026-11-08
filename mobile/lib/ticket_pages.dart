import 'dart:async';

import 'package:flutter/material.dart';

import 'auth_api.dart';

const ticketGreen = Color(0xFF087F50);
String sentTimeLabel(dynamic value) {
  final formatted = dateLabel(value);
  final parts = formatted.split(' ');
  return parts.length == 2 ? '${parts[1]} · ${parts[0]}' : formatted;
}

String statusLabel(dynamic status) =>
    const {
      'new': 'Chờ tiếp nhận',
      'assigned': 'Đã phân công',
      'in_progress': 'Đang xử lý',
      'resolved': 'Đã xử lý',
      'closed': 'Đã đóng',
      'reopened': 'Yêu cầu xử lý lại',
    }[status] ??
    '$status';
String dateLabel(dynamic value) {
  final date = DateTime.tryParse('$value')?.toLocal();
  if (date == null) return '—';
  String pad(int n) => '$n'.padLeft(2, '0');
  return '${pad(date.day)}/${pad(date.month)}/${date.year} ${pad(date.hour)}:${pad(date.minute)}';
}

String errorLabel(Object error) =>
    error is Exception && error.toString().startsWith('Exception: ')
    ? error.toString().substring(11)
    : 'Không kết nối được máy chủ. Kiểm tra mạng rồi thử lại.';
Widget ticketPanel(String label, String text) => Container(
  margin: const EdgeInsets.only(bottom: 12),
  padding: const EdgeInsets.all(16),
  decoration: BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(12),
  ),
  child: Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: const TextStyle(color: Colors.blueGrey, fontSize: 12)),
      const SizedBox(height: 7),
      Text(text, style: const TextStyle(fontWeight: FontWeight.w600)),
    ],
  ),
);
Widget ticketHeading(String title) => Padding(
  padding: const EdgeInsets.symmetric(vertical: 12),
  child: Text(
    title,
    style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
  ),
);

class TicketHome extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> user;
  const TicketHome({super.key, required this.api, required this.user});
  @override
  State<TicketHome> createState() => _TicketHomeState();
}

class _TicketHomeState extends State<TicketHome> {
  final search = TextEditingController();
  Timer? debounce;
  final List<Map<String, dynamic>> tickets = [];
  int page = 0, lastPage = 1, requestId = 0;
  String filter = '';
  bool loading = true;
  String? error;
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
    final current = ++requestId;
    setState(() {
      loading = true;
      error = null;
      if (reset) {
        tickets.clear();
        page = 0;
      }
    });
    try {
      final query = Uri(
        queryParameters: {
          'page': '${reset ? 1 : page + 1}',
          'search': search.text.trim(),
          'status_group': filter,
        },
      ).query;
      final result = await widget.api.get('tickets?$query');
      if (!mounted || current != requestId) return;
      setState(() {
        tickets.addAll(
          (result['data'] as List).map(
            (item) => Map<String, dynamic>.from(item as Map),
          ),
        );
        page = result['current_page'] as int;
        lastPage = result['last_page'] as int;
      });
    } catch (e) {
      if (mounted && current == requestId) {
        setState(() {
          error = errorLabel(e);
        });
      }
    } finally {
      if (mounted && current == requestId) {
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
        const Text('Tìm kiếm', style: TextStyle(fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        TextField(
          controller: search,
          maxLength: 180,
          decoration: const InputDecoration(
            hintText: 'Mã phiếu / nội dung',
            prefixIcon: Icon(Icons.search),
            counterText: '',
          ),
          onChanged: (_) {
            debounce?.cancel();
            ++requestId;
            setState(() {
              loading = true;
              tickets.clear();
            });
            debounce = Timer(
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
              'active': 'Đang xử lý',
              'completed': 'Hoàn tất',
            }.entries)
              ChoiceChip(
                label: Text(entry.value),
                selected: filter == entry.key,
                onSelected: (_) {
                  debounce?.cancel();
                  filter = entry.key;
                  load(reset: true);
                },
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (error != null) ...[
          Text(error!, style: const TextStyle(color: Colors.red)),
          TextButton(
            onPressed: () => load(reset: page == 0),
            child: const Text('Thử lại'),
          ),
        ],
        if (!loading && error == null && tickets.isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 32),
            child: Text('Chưa có phiếu sự cố nào.'),
          ),
        ...tickets.map(
          (ticket) => Card(
            color: Colors.white,
            elevation: 0,
            margin: const EdgeInsets.only(bottom: 12),
            child: ListTile(
              contentPadding: const EdgeInsets.all(16),
              title: Text(
                '${ticket['ticket_number']} · ${statusLabel(ticket['status'])}',
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
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    '${ticket['building_label'] ?? ''} · ${ticket['room_label'] ?? ''}',
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Chạm để xem chi tiết →',
                    style: TextStyle(fontSize: 12, color: Colors.blueGrey),
                  ),
                ],
              ),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) =>
                      TicketDetail(api: widget.api, id: ticket['id'] as int),
                ),
              ),
            ),
          ),
        ),
        if (loading)
          const Padding(
            padding: EdgeInsets.all(24),
            child: Center(child: CircularProgressIndicator()),
          ),
        if (!loading && error == null && page < lastPage)
          OutlinedButton(
            onPressed: () => load(),
            child: const Text('Xem thêm phiếu'),
          ),
      ],
    ),
  );
}

class TicketDetail extends StatefulWidget {
  final AuthApi api;
  final int id;
  const TicketDetail({super.key, required this.api, required this.id});
  @override
  State<TicketDetail> createState() => _TicketDetailState();
}

class _TicketDetailState extends State<TicketDetail> {
  Future<void> supplement() async {
    final title = TextEditingController(
      text: ticket!['title']?.toString() ?? '',
    );
    final description = TextEditingController(
      text: ticket!['description']?.toString() ?? '',
    );
    final form = GlobalKey<FormState>();
    bool saving = false;
    String? message;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (ctx, update) => SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              0,
              20,
              MediaQuery.viewInsetsOf(ctx).bottom + 20,
            ),
            child: SingleChildScrollView(
              child: Form(
                key: form,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ticketHeading('Bổ sung thông tin phiếu'),
                    TextFormField(
                      controller: title,
                      enabled: !saving,
                      maxLength: 180,
                      decoration: const InputDecoration(labelText: 'Tiêu đề'),
                      validator: (v) => v == null || v.trim().isEmpty
                          ? 'Nhập tiêu đề.'
                          : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: description,
                      enabled: !saving,
                      maxLength: 5000,
                      maxLines: 5,
                      decoration: const InputDecoration(
                        labelText: 'Mô tả bổ sung',
                      ),
                      validator: (v) =>
                          v == null || v.trim().isEmpty ? 'Nhập mô tả.' : null,
                    ),
                    if (message != null)
                      Text(message!, style: const TextStyle(color: Colors.red)),
                    FilledButton(
                      onPressed: saving
                          ? null
                          : () async {
                              if (!form.currentState!.validate()) return;
                              update(() {
                                saving = true;
                                message = null;
                              });
                              try {
                                await widget.api.patch('tickets/${widget.id}', {
                                  'title': title.text.trim(),
                                  'description': description.text.trim(),
                                  'room_id': ticket!['room_id'],
                                  'room_code': ticket!['room']?['code'],
                                  'building_code':
                                      ticket!['room']?['building']?['code'],
                                  'category_id': ticket!['category_id'],
                                  'priority': ticket!['priority'],
                                  'phone': ticket!['reporter_phone'],
                                });
                                if (ctx.mounted) Navigator.of(ctx).pop(true);
                              } catch (e) {
                                if (ctx.mounted) {
                                  update(() {
                                    message = errorLabel(e);
                                  });
                                }
                              } finally {
                                if (ctx.mounted) {
                                  update(() {
                                    saving = false;
                                  });
                                }
                              }
                            },
                      child: Text(
                        saving ? 'Đang lưu…' : 'Gửi thông tin bổ sung',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
    // Controllers belong to the closing sheet until its exit animation completes.
    await Future<void>.delayed(const Duration(milliseconds: 300));
    title.dispose();
    description.dispose();
    if (saved == true && mounted) load();
  }

  Map<String, dynamic>? ticket;
  String? error;
  bool loading = true;
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
      final result = await widget.api.get('tickets/${widget.id}');
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

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Chi tiết phiếu',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          if (ticket != null)
            Text(
              '${ticket!['ticket_number']} · ${statusLabel(ticket!['status'])}',
              style: const TextStyle(fontSize: 12, color: Colors.blueGrey),
            ),
        ],
      ),
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
        ? Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(error!),
                  TextButton(onPressed: load, child: const Text('Thử lại')),
                ],
              ),
            ),
          )
        : RefreshIndicator(
            onRefresh: load,
            child: ListView(
              padding: const EdgeInsets.all(20),
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                ticketPanel('Sự cố', '${ticket!['title']}'),
                ticketPanel(
                  'Người gửi',
                  '${ticket!['reporter_name'] ?? ticket!['reporter']?['name'] ?? '—'}',
                ),
                ticketPanel(
                  'Phòng',
                  '${ticket!['building_label'] ?? ''} · ${ticket!['room_label'] ?? ''}',
                ),
                ticketPanel(
                  '✓ Đã gửi yêu cầu',
                  sentTimeLabel(ticket!['created_at']),
                ),
                ticketPanel(
                  'Nhân viên phụ trách',
                  ticket!['assigned_staff']?['name']?.toString() ??
                      'Đang chờ phân công',
                ),
                ...(List<dynamic>.from(ticket!['logs'] as List? ?? [])
                        .where(
                          (log) =>
                              !(log['to_status'] == 'new' &&
                                  log['from_status'] == null) &&
                              log['note'] != 'Người báo sự cố đã gửi báo cáo.',
                        )
                        .toList()
                      ..sort(
                        (a, b) => '${a['created_at']}'.compareTo(
                          '${b['created_at']}',
                        ),
                      ))
                    .map(
                      (log) => ticketPanel(
                        '${log['note'] ?? statusLabel(log['to_status'])}',
                        '${log['user']?['name'] ?? 'Hệ thống'} · ${dateLabel(log['created_at'])}',
                      ),
                    ),
                if (!['resolved', 'closed'].contains(ticket!['status']))
                  ticketPanel('○ Hoàn tất xử lý', 'Chưa hoàn tất'),
                if (ticket!['can_supplement'] == true)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    child: FilledButton.tonal(
                      onPressed: supplement,
                      child: const Text('Bổ sung thông tin'),
                    ),
                  ),
                ticketHeading('Mô tả'),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE9F3EF),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text('${ticket!['description'] ?? '—'}'),
                ),
                if (ticket!['resolution_note'] != null) ...[
                  ticketHeading('Kết quả xử lý'),
                  ticketPanel('Nội dung', '${ticket!['resolution_note']}'),
                ],
                if (ticket!['feedback_at'] != null) ...[
                  ticketHeading('Đánh giá của bạn'),
                  ticketPanel(
                    ticket!['feedback_satisfied'] == true
                        ? 'Hài lòng'
                        : 'Chưa hài lòng',
                    '${ticket!['feedback_comment'] ?? 'Không có góp ý thêm.'}',
                  ),
                ],
                if (ticket!['can_review'] == true)
                  Padding(
                    padding: const EdgeInsets.only(top: 24),
                    child: FilledButton(
                      onPressed: () async {
                        final sent = await Navigator.of(context).push<bool>(
                          MaterialPageRoute(
                            builder: (_) =>
                                ReviewPage(api: widget.api, id: widget.id),
                          ),
                        );
                        if (sent == true && mounted) load();
                      },
                      child: const Text('Đánh giá xử lý'),
                    ),
                  ),
              ],
            ),
          ),
  );
}

class ReviewPage extends StatefulWidget {
  final AuthApi api;
  final int id;
  const ReviewPage({super.key, required this.api, required this.id});
  @override
  State<ReviewPage> createState() => _ReviewPageState();
}

class _ReviewPageState extends State<ReviewPage> {
  final comment = TextEditingController();
  bool? satisfied;
  bool busy = false;
  String? error;
  @override
  void dispose() {
    comment.dispose();
    super.dispose();
  }

  Future<void> send() async {
    if (busy) return;
    if (satisfied == null) {
      setState(() {
        error = 'Chọn mức độ hài lòng trước khi gửi.';
      });
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.api.patch('tickets/${widget.id}/review', {
        'satisfied': satisfied,
        'comment': comment.text.trim(),
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã gửi đánh giá. Cảm ơn bạn!')),
        );
        Navigator.of(context).pop(true);
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
  Widget build(BuildContext context) => PopScope(
    canPop: !busy,
    child: Scaffold(
      appBar: AppBar(title: const Text('Đánh giá xử lý')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: const Color(0xFF075D43),
              borderRadius: BorderRadius.circular(18),
            ),
            child: const Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '◆ HELPDESK UTH',
                  style: TextStyle(color: Colors.white70, fontSize: 12),
                ),
                SizedBox(height: 14),
                Text(
                  'Sự cố đã được xử lý',
                  style: TextStyle(
                    fontSize: 23,
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                SizedBox(height: 10),
                Text(
                  'Đánh giá giúp cải thiện chất lượng hỗ trợ',
                  style: TextStyle(color: Colors.white70),
                ),
              ],
            ),
          ),
          ticketHeading('Mức độ hài lòng'),
          Wrap(
            spacing: 8,
            children: [
              ChoiceChip(
                label: const Text('Hài lòng'),
                selected: satisfied == true,
                onSelected: busy
                    ? null
                    : (_) => setState(() {
                        satisfied = true;
                      }),
              ),
              ChoiceChip(
                label: const Text('Chưa hài lòng'),
                selected: satisfied == false,
                onSelected: busy
                    ? null
                    : (_) => setState(() {
                        satisfied = false;
                      }),
              ),
            ],
          ),
          ticketHeading('Góp ý'),
          TextField(
            controller: comment,
            enabled: !busy,
            maxLength: 2000,
            maxLines: 4,
            decoration: const InputDecoration(
              hintText: 'Nhập ý kiến (không bắt buộc)',
            ),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(error!, style: const TextStyle(color: Colors.red)),
            ),
          FilledButton(
            onPressed: busy ? null : send,
            child: Text(busy ? 'Đang gửi…' : 'Gửi đánh giá'),
          ),
        ],
      ),
    ),
  );
}
