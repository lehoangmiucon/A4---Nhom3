import 'package:flutter/material.dart';

import 'auth_api.dart';
import 'ticket_pages.dart';

class NotificationsPage extends StatefulWidget {
  final AuthApi api;
  const NotificationsPage({super.key, required this.api});
  @override
  State<NotificationsPage> createState() => _NotificationsPageState();
}

class _NotificationsPageState extends State<NotificationsPage> {
  final List<Map<String, dynamic>> items = [];
  int page = 0, lastPage = 1;
  bool loading = true, saving = false;
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
      final data = await widget.api.get(
        'notifications?page=${reset ? 1 : page + 1}',
      );
      if (mounted) {
        setState(() {
          if (reset) items.clear();
          items.addAll(
            (data['data'] as List).map(
              (e) => Map<String, dynamic>.from(e as Map),
            ),
          );
          page = data['current_page'] as int;
          lastPage = data['last_page'] as int;
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

  Future<void> mark({Map<String, dynamic>? item}) async {
    if (saving) return;
    setState(() {
      saving = true;
      error = null;
    });
    try {
      await widget.api.patch(
        item == null
            ? 'notifications/read-all'
            : 'notifications/${item['id']}/read',
        {},
      );
      if (mounted) {
        setState(() {
          for (final row in items) {
            if (item == null || row['id'] == item['id']) {
              row['read_at'] = DateTime.now().toIso8601String();
            }
          }
        });
      }
      final id = int.tryParse('${item?['ticket_id']}');
      if (mounted && id != null) {
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => TicketDetail(api: widget.api, id: id),
          ),
        );
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
          saving = false;
        });
      }
    }
  }

  bool today(Map<String, dynamic> row) {
    final date = DateTime.tryParse('${row['created_at']}')?.toLocal(),
        now = DateTime.now();
    return date != null &&
        date.year == now.year &&
        date.month == now.month &&
        date.day == now.day;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Thông báo',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          Text(
            'Cập nhật tiến độ',
            style: TextStyle(fontSize: 12, color: Colors.blueGrey),
          ),
        ],
      ),
    ),
    body: RefreshIndicator(
      onRefresh: () => saving ? Future.value() : load(reset: true),
      child: ListView(
        padding: const EdgeInsets.all(20),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          if (error != null) ...[
            Text(error!, style: const TextStyle(color: Colors.red)),
            TextButton(
              onPressed: loading || saving
                  ? null
                  : () => load(reset: page == 0),
              child: const Text('Thử lại'),
            ),
          ],
          if (!loading && items.isEmpty && error == null)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Text('Bạn chưa có thông báo nào.'),
            ),
          for (final current in [true, false]) ...[
            if (items.any((e) => today(e) == current))
              ticketHeading(current ? 'Hôm nay' : 'Trước đó'),
            ...items
                .where((e) => today(e) == current)
                .map(
                  (row) => Card(
                    color: row['read_at'] == null
                        ? const Color(0xFFE9F3EF)
                        : Colors.white,
                    elevation: 0,
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      contentPadding: const EdgeInsets.all(16),
                      leading: row['read_at'] == null
                          ? const Icon(
                              Icons.circle,
                              color: ticketGreen,
                              size: 10,
                            )
                          : const Icon(Icons.notifications_none, size: 18),
                      title: Text(
                        '${row['title']}',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: row['read_at'] == null
                              ? FontWeight.bold
                              : FontWeight.normal,
                        ),
                      ),
                      subtitle: Text(
                        '${row['body']}\n${dateLabel(row['created_at'])}',
                        style: const TextStyle(fontSize: 12),
                      ),
                      onTap: saving || loading ? null : () => mark(item: row),
                    ),
                  ),
                ),
          ],
          if (loading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            ),
          if (!loading && page < lastPage && error == null)
            TextButton(
              onPressed: saving ? null : () => load(),
              child: const Text('Xem thêm thông báo'),
            ),
          if (items.isNotEmpty)
            FilledButton.tonal(
              onPressed: saving || loading ? null : () => mark(),
              child: Text(saving ? 'Đang cập nhật…' : 'Đánh dấu tất cả đã đọc'),
            ),
        ],
      ),
    ),
  );
}
