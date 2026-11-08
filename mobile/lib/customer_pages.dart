import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import 'auth_api.dart';
import 'ticket_pages.dart';
import 'notifications_page.dart';
import 'account_pages.dart';

const customerGreen = Color(0xFF087F50);
const customerPale = Color(0xFFE9F3EF);

// One menu style for building, room and incident category selectors.
DropdownButtonFormField<int> reportDropdown({
  Key? key,
  int? initialValue,
  required List<DropdownMenuItem<int>> items,
  required ValueChanged<int?>? onChanged,
  FormFieldValidator<int>? validator,
  Widget? hint,
  Widget? disabledHint,
  bool isExpanded = true,
}) => DropdownButtonFormField<int>(
  key: key,
  initialValue: initialValue,
  items: items,
  onChanged: onChanged,
  validator: validator,
  hint: hint,
  disabledHint: disabledHint,
  isExpanded: isExpanded,
  menuMaxHeight: 240,
  itemHeight: 48,
  borderRadius: BorderRadius.circular(14),
  dropdownColor: Colors.white,
  elevation: 3,
  style: const TextStyle(fontSize: 14, color: Color(0xFF19332D)),
  icon: const Icon(Icons.keyboard_arrow_down_rounded, size: 20),
);
Widget customerBanner(String title, String subtitle) => Container(
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
          fontSize: 11,
          fontWeight: FontWeight.bold,
        ),
      ),
      const SizedBox(height: 14),
      Text(
        title,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.bold,
          fontSize: 22,
        ),
      ),
      const SizedBox(height: 10),
      Text(
        subtitle,
        style: const TextStyle(color: Colors.white70, fontSize: 13),
      ),
    ],
  ),
);
Widget customerInfo(String text) => Container(
  margin: const EdgeInsets.only(bottom: 14),
  padding: const EdgeInsets.all(14),
  decoration: BoxDecoration(
    color: customerPale,
    borderRadius: BorderRadius.circular(12),
  ),
  child: Text(text, style: const TextStyle(fontSize: 13)),
);
Widget infoRow(String label, String value) => Container(
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
      const SizedBox(height: 6),
      Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
    ],
  ),
);

class CustomerShell extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> user;
  final VoidCallback onLogout;
  final ValueChanged<Map<String, dynamic>> onUpdated;
  final Future<void> Function() onPasswordChanged;
  final bool busy;
  final String? error;
  const CustomerShell({
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
  State<CustomerShell> createState() => _CustomerShellState();
}

class _CustomerShellState extends State<CustomerShell> {
  int tab = 0, revision = 0;
  Future<void> report({bool manualLookup = false, String? scannedCode}) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ReportFlow(
          api: widget.api,
          manualLookup: manualLookup,
          scannedCode: scannedCode,
        ),
      ),
    );
    if (mounted) {
      setState(() {
        revision++;
        tab = 0;
      });
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
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
      toolbarHeight: 76,
      title: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            ['Trang chủ', 'Phiếu của tôi', 'Quét QR', 'Cá nhân'][tab],
            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(
            tab == 2 ? 'Tự nhận diện phòng học' : memberLabel(widget.user),
            style: const TextStyle(fontSize: 12, color: Colors.blueGrey),
          ),
        ],
      ),
    ),
    body: SafeArea(
      child: tab == 0
          ? RecentTickets(
              key: ValueKey(revision),
              api: widget.api,
              user: widget.user,
              onReport: report,
              onQr: () => setState(() {
                tab = 2;
              }),
              onAll: () => setState(() {
                tab = 1;
              }),
            )
          : tab == 1
          ? TicketHome(
              key: ValueKey('tickets-$revision'),
              api: widget.api,
              user: widget.user,
            )
          : tab == 2
          ? QrLanding(
              onManual: () => report(manualLookup: true),
              onScan: (code) => report(scannedCode: code),
            )
          : AccountTab(
              api: widget.api,
              user: widget.user,
              onUpdated: widget.onUpdated,
              onPasswordChanged: widget.onPasswordChanged,
              onLogout: widget.onLogout,
              busy: widget.busy,
              error: widget.error,
            ),
    ),
    bottomNavigationBar: NavigationBar(
      selectedIndex: tab,
      onDestinationSelected: (value) => setState(() {
        tab = value;
      }),
      destinations: const [
        NavigationDestination(
          icon: Icon(Icons.home_outlined),
          label: 'Trang chủ',
        ),
        NavigationDestination(
          icon: Icon(Icons.receipt_long_outlined),
          label: 'Phiếu',
        ),
        NavigationDestination(icon: Icon(Icons.qr_code), label: 'QR'),
        NavigationDestination(
          icon: Icon(Icons.person_outline),
          label: 'Cá nhân',
        ),
      ],
    ),
  );
}

class RecentTickets extends StatefulWidget {
  final AuthApi api;
  final Map<String, dynamic> user;
  final VoidCallback onReport, onQr, onAll;
  const RecentTickets({
    super.key,
    required this.api,
    required this.user,
    required this.onReport,
    required this.onQr,
    required this.onAll,
  });
  @override
  State<RecentTickets> createState() => _RecentTicketsState();
}

class _RecentTicketsState extends State<RecentTickets> {
  List<dynamic> items = [];
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
      final data = await widget.api.get('tickets');
      if (mounted) {
        setState(() {
          items = (data['data'] as List).take(3).toList();
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
          'Xin chào, ${widget.user['name']}',
          'Bạn cần hỗ trợ sự cố gì hôm nay?',
        ),
        const Text(
          'Thao tác nhanh',
          style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 10),
        Container(
          decoration: BoxDecoration(
            color: customerPale,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Wrap(
            children: [
              TextButton.icon(
                onPressed: widget.onQr,
                icon: const Icon(Icons.qr_code),
                label: const Text('Quét QR phòng'),
              ),
              TextButton.icon(
                onPressed: widget.onReport,
                icon: const Icon(Icons.add_circle_outline),
                label: const Text('Báo sự cố'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 22),
        const Text(
          'Phiếu gần đây',
          style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
        ),
        const SizedBox(height: 12),
        if (loading) const Center(child: CircularProgressIndicator()),
        if (error != null) ...[
          customerInfo(error!),
          TextButton(onPressed: load, child: const Text('Thử lại')),
        ],
        if (!loading && error == null && items.isEmpty)
          customerInfo(
            'Bạn chưa có phiếu sự cố. Chọn Báo sự cố để tạo phiếu đầu tiên.',
          ),
        ...items.map(
          (ticket) => Card(
            color: Colors.white,
            elevation: 0,
            child: ListTile(
              contentPadding: const EdgeInsets.all(16),
              title: Text(
                '${ticket['ticket_number']} · ${statusLabel(ticket['status'])}',
                style: const TextStyle(
                  fontSize: 12,
                  color: customerGreen,
                  fontWeight: FontWeight.bold,
                ),
              ),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 9),
                  Text(
                    '${ticket['title']} · ${ticket['room_label'] ?? ''}',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                    ),
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
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton(
            onPressed: widget.onAll,
            child: const Text('Xem tất cả phiếu'),
          ),
        ),
      ],
    ),
  );
}

class QrLanding extends StatelessWidget {
  final VoidCallback onManual;
  final ValueChanged<String> onScan;
  const QrLanding({super.key, required this.onManual, required this.onScan});
  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(20),
    children: [
      customerBanner('Đưa camera vào QR', 'Mã được dán tại vị trí phòng học'),
      Container(
        height: 160,
        margin: const EdgeInsets.only(bottom: 16),
        decoration: BoxDecoration(
          color: customerPale,
          borderRadius: BorderRadius.circular(16),
        ),
        child: const Center(
          child: Icon(Icons.qr_code_scanner, size: 80, color: customerGreen),
        ),
      ),
      customerInfo(
        'Quét QR để tự nhận diện tòa và phòng. Bạn cũng có thể nhập mã phòng thủ công.',
      ),
      FilledButton(
        onPressed: () async {
          final code = await Navigator.of(context).push<String>(
            MaterialPageRoute(builder: (_) => const RoomScanner()),
          );
          if (code != null && context.mounted) onScan(code);
        },
        child: const Text('Quét mã QR'),
      ),
      const SizedBox(height: 12),
      FilledButton.tonal(
        onPressed: onManual,
        child: const Text('Nhập mã phòng thủ công'),
      ),
    ],
  );
}

enum ReportStep { location, first, second, success }

class ReportFlow extends StatefulWidget {
  final AuthApi api;
  final bool manualLookup;
  final String? scannedCode;
  const ReportFlow({
    super.key,
    required this.api,
    this.manualLookup = false,
    this.scannedCode,
  });
  @override
  State<ReportFlow> createState() => _ReportFlowState();
}

class _ReportFlowState extends State<ReportFlow> {
  final roomCode = TextEditingController(),
      title = TextEditingController(),
      description = TextEditingController();
  final firstForm = GlobalKey<FormState>(), secondForm = GlobalKey<FormState>();
  final picker = ImagePicker();
  List<Map<String, dynamic>> rooms = [], categories = [];
  final List<XFile> images = [];
  Map<String, dynamic>? room, ticket;
  int? buildingId, categoryId;
  String priority = 'normal';
  ReportStep step = ReportStep.location;
  bool busy = false, loading = true;
  String? error;
  @override
  void initState() {
    super.initState();
    step = widget.manualLookup || widget.scannedCode != null
        ? ReportStep.location
        : ReportStep.first;
    load();
    if (widget.scannedCode != null) {
      roomCode.text = widget.scannedCode!;
      lookup();
    }
  }

  @override
  void dispose() {
    roomCode.dispose();
    title.dispose();
    description.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final results = await Future.wait([
        widget.api.get('rooms'),
        widget.api.get('categories'),
      ]);
      if (mounted) {
        setState(() {
          rooms = (results[0]['data'] as List)
              .map((e) => Map<String, dynamic>.from(e as Map))
              .toList();
          categories = (results[1]['data'] as List)
              .map((e) => Map<String, dynamic>.from(e as Map))
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

  Future<void> lookup() async {
    if (roomCode.text.trim().isEmpty) {
      setState(() {
        error = 'Nhập mã phòng cần kiểm tra.';
      });
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final result = await widget.api.get(
        'rooms/${Uri.encodeComponent(roomCode.text.trim())}',
      );
      if (mounted) {
        setState(() {
          room = Map<String, dynamic>.from(result['data'] as Map);
          buildingId = room!['building_id'] as int;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          error = 'Chưa tìm thấy phòng hoặc không kết nối được máy chủ. Kiểm tra mã và thử lại.';
          room = null;
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
    if (images.length >= 5) {
      setState(() {
        error = 'Chỉ đính kèm tối đa 5 ảnh.';
      });
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final image = await picker.pickImage(
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
          error = e.toString().contains('5 MB')
              ? 'Ảnh phải nhỏ hơn hoặc bằng 5 MB.'
              : 'Không mở được ảnh hoặc camera. Kiểm tra quyền truy cập và thử lại.';
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

  Future<void> send() async {
    if (busy || !secondForm.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final result = await widget.api.createTicket({
        'room_id': '${room!['id']}',
        'room_code': '${room!['code']}',
        'category_id': '$categoryId',
        'title': title.text.trim(),
        'description': description.text.trim(),
        'priority': priority,
      }, images);
      if (mounted) {
        setState(() {
          ticket = result;
          step = ReportStep.success;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          error =
              '${errorLabel(e)}\nNếu kết nối gián đoạn, kiểm tra danh sách phiếu trước khi gửi lại.';
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

  void back() {
    if (busy) return;
    if (step == ReportStep.second) {
      setState(() {
        step = ReportStep.first;
        error = null;
      });
    } else if (step == ReportStep.first &&
        (widget.manualLookup || widget.scannedCode != null)) {
      setState(() {
        step = ReportStep.location;
        error = null;
      });
    } else {
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop:
        !busy &&
        (step == ReportStep.location ||
            step == ReportStep.success ||
            (step == ReportStep.first &&
                !widget.manualLookup &&
                widget.scannedCode == null)),
    onPopInvokedWithResult: (didPop, _) {
      if (!didPop && !busy) back();
    },
    child: Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        leading: IconButton(
          onPressed: busy ? null : back,
          icon: const Icon(Icons.arrow_back),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              switch (step) {
                ReportStep.location => 'Xác nhận vị trí',
                ReportStep.first => 'Báo sự cố (1/2)',
                ReportStep.second => 'Báo sự cố (2/2)',
                ReportStep.success => 'Gửi thành công',
              },
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
            ),
            Text(switch (step) {
              ReportStep.location => 'Kiểm tra địa điểm',
              ReportStep.first => 'Vị trí và nhóm lỗi',
              ReportStep.second => 'Mô tả và minh chứng',
              ReportStep.success => 'Mã phiếu được tạo',
            }, style: const TextStyle(fontSize: 12, color: Colors.blueGrey)),
          ],
        ),
      ),
      body: SafeArea(
        child: loading
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  if (error != null) ...[
                    Text(error!, style: const TextStyle(color: Colors.red)),
                    const SizedBox(height: 12),
                  ],
                  ...body(),
                ],
              ),
      ),
    ),
  );
  Widget field(String label, Widget child) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
        ),
        const SizedBox(height: 8),
        child,
      ],
    ),
  );
  List<Widget> body() {
    switch (step) {
      case ReportStep.location:
        return [
          if (room == null && widget.scannedCode == null) ...[
            field(
              'Mã phòng',
              TextField(
                controller: roomCode,
                onChanged: (_) {
                  if (room != null) {
                    setState(() {
                      room = null;
                    });
                  }
                },
                enabled: !busy,
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => busy ? null : lookup(),
                decoration: const InputDecoration(
                  hintText: 'Nhập mã phòng, ví dụ A201',
                ),
              ),
            ),
            FilledButton.tonal(
              onPressed: busy ? null : lookup,
              child: Text(busy ? 'Đang kiểm tra…' : 'Kiểm tra phòng'),
            ),
            const SizedBox(height: 16),
          ],
          if (room == null && widget.scannedCode != null && !busy)
            TextButton(
              onPressed: lookup,
              child: const Text('Thử tra cứu lại phòng'),
            ),
          if (busy) const Center(child: CircularProgressIndicator()),
          if (room != null) ...[
            infoRow('Tòa nhà', '${room!['building']?['name'] ?? '—'}'),
            infoRow('Phòng', '${room!['name']} · ${room!['code']}'),
            customerInfo('Phòng đã được đối chiếu với dữ liệu hệ thống.'),
            if (widget.scannedCode == null)
              TextButton(
                onPressed: () => setState(() {
                  room = null;
                }),
                child: const Text('Nhập mã phòng khác'),
              ),
            FilledButton(
              onPressed: busy
                  ? null
                  : () => setState(() {
                      step = ReportStep.first;
                      error = null;
                    }),
              child: const Text('Tạo báo cáo sự cố'),
            ),
          ],
          if (rooms.isEmpty || categories.isEmpty)
            TextButton(
              onPressed: busy ? null : load,
              child: const Text('Tải lại dữ liệu phòng và danh mục'),
            ),
        ];
      case ReportStep.first:
        final buildings = <int, String>{
          for (final item in rooms)
            if (item['building'] != null)
              item['building_id'] as int: '${item['building']['name']}',
        };
        return [
          Form(
            key: firstForm,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                field(
                  'Tòa',
                  reportDropdown(
                    key: ValueKey('building-$buildingId'),
                    hint: const Text(
                      'Chọn tòa nhà',
                      style: TextStyle(fontSize: 14),
                    ),
                    initialValue: buildings.containsKey(buildingId)
                        ? buildingId
                        : null,
                    isExpanded: true,
                    items: buildings.entries
                        .map(
                          (e) => DropdownMenuItem(
                            value: e.key,
                            child: Text(
                              e.value,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: busy
                        ? null
                        : (value) => setState(() {
                            buildingId = value;
                            room = null;
                          }),
                    validator: (v) => v == null ? 'Chọn tòa nhà.' : null,
                  ),
                ),
                field(
                  'Phòng',
                  reportDropdown(
                    key: ValueKey('room-$buildingId-${room?['id']}'),
                    hint: const Text(
                      'Chọn phòng',
                      style: TextStyle(fontSize: 14),
                    ),
                    disabledHint: const Text(
                      'Chọn tòa nhà trước',
                      style: TextStyle(fontSize: 14),
                    ),
                    initialValue: room?['id'] as int?,
                    isExpanded: true,
                    items: rooms
                        .where((e) => e['building_id'] == buildingId)
                        .map(
                          (e) => DropdownMenuItem(
                            value: e['id'] as int,
                            child: Text(
                              '${e['name']} · ${e['code']}',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: busy
                        ? null
                        : (value) => setState(() {
                            room = rooms.firstWhere((e) => e['id'] == value);
                          }),
                    validator: (v) => v == null ? 'Chọn phòng.' : null,
                  ),
                ),
                field(
                  'Danh mục sự cố',
                  reportDropdown(
                    initialValue: categoryId,
                    hint: const Text(
                      'Chọn danh mục sự cố',
                      style: TextStyle(fontSize: 14),
                    ),
                    isExpanded: true,
                    items: categories
                        .map(
                          (e) => DropdownMenuItem(
                            value: e['id'] as int,
                            child: Text('${e['name']}'),
                          ),
                        )
                        .toList(),
                    onChanged: busy
                        ? null
                        : (value) => setState(() {
                            categoryId = value;
                          }),
                    validator: (v) => v == null ? 'Chọn danh mục sự cố.' : null,
                  ),
                ),
                field(
                  'Tiêu đề',
                  TextFormField(
                    controller: title,
                    maxLength: 180,
                    decoration: const InputDecoration(
                      hintText: 'Nhập tiêu đề sự cố',
                    ),
                    validator: (v) => v == null || v.trim().isEmpty
                        ? 'Nhập tiêu đề sự cố.'
                        : null,
                  ),
                ),

                FilledButton(
                  onPressed: () {
                    if (firstForm.currentState!.validate()) {
                      setState(() {
                        step = ReportStep.second;
                        error = null;
                      });
                    }
                  },
                  child: const Text('Tiếp tục'),
                ),
              ],
            ),
          ),
        ];
      case ReportStep.second:
        return [
          Form(
            key: secondForm,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                field(
                  'Mô tả chi tiết',
                  TextFormField(
                    controller: description,
                    enabled: !busy,
                    maxLines: 4,
                    maxLength: 5000,
                    decoration: const InputDecoration(
                      hintText: 'Nêu hiện tượng, thời gian xảy ra…',
                    ),
                    validator: (v) => v == null || v.trim().isEmpty
                        ? 'Nhập mô tả sự cố.'
                        : null,
                  ),
                ),
                field(
                  'Mức độ ảnh hưởng',
                  DropdownButtonFormField<String>(
                    initialValue: priority,
                    isExpanded: true,
                    items: const [
                      DropdownMenuItem(
                        value: 'low',
                        child: Text('Ảnh hưởng nhỏ'),
                      ),
                      DropdownMenuItem(
                        value: 'normal',
                        child: Text('Ảnh hưởng một phần'),
                      ),
                      DropdownMenuItem(
                        value: 'high',
                        child: Text('Ảnh hưởng cả lớp / phòng'),
                      ),
                      DropdownMenuItem(
                        value: 'urgent',
                        child: Text('Khẩn cấp / liên quan an toàn'),
                      ),
                    ],
                    onChanged: busy
                        ? null
                        : (value) => setState(() {
                            priority = value!;
                          }),
                  ),
                ),
                const Text('Minh chứng (tối đa 5 ảnh, mỗi ảnh 5 MB)'),
                Wrap(
                  spacing: 8,
                  children: [
                    TextButton.icon(
                      onPressed: busy ? null : () => pick(ImageSource.gallery),
                      icon: const Icon(Icons.photo_outlined),
                      label: const Text('Chọn ảnh'),
                    ),
                    TextButton.icon(
                      onPressed: busy ? null : () => pick(ImageSource.camera),
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
                customerInfo(
                  'Không gửi kèm dữ liệu cá nhân hoặc mật khẩu vào ảnh minh chứng.',
                ),
                FilledButton(
                  onPressed: busy ? null : send,
                  child: Text(busy ? 'Đang gửi phiếu…' : 'Gửi phiếu sự cố'),
                ),
              ],
            ),
          ),
        ];
      case ReportStep.success:
        return [
          customerBanner(
            'Gửi báo cáo thành công',
            'Mã: ${ticket!['ticket_number']}',
          ),
          infoRow(
            'Vị trí',
            '${ticket!['building_label'] ?? ''} · ${ticket!['room_label'] ?? ''}',
          ),
          infoRow('Trạng thái', statusLabel(ticket!['status'])),
          FilledButton(
            onPressed: () {
              Navigator.of(context).pushReplacement(
                MaterialPageRoute<void>(
                  builder: (_) =>
                      TicketDetail(api: widget.api, id: ticket!['id'] as int),
                ),
              );
            },
            child: const Text('Xem chi tiết phiếu'),
          ),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Về trang chủ'),
          ),
        ];
    }
  }
}

/// Read room codes from existing room URLs without navigating to the QR URL.
String? roomCodeFromQr(String raw) {
  final value = raw.trim();
  if (value.isEmpty || value.length > 2048) return null;
  final uri = Uri.tryParse(value);
  if (uri != null && uri.hasScheme) {
    if (uri.scheme != 'http' && uri.scheme != 'https') return null;
    final queryCode =
        uri.queryParameters['room_code'] ?? uri.queryParameters['room'];
    if (queryCode != null && queryCode.isNotEmpty && queryCode.length <= 80) {
      return queryCode;
    }
    final segments = uri.pathSegments;
    if (segments.length >= 2 &&
        ['r', 'report'].contains(segments[segments.length - 2])) {
      return segments.last.length <= 80 ? segments.last : null;
    }
    return null;
  }
  return value.length <= 80 && !value.contains('/') ? value : null;
}

class RoomScanner extends StatefulWidget {
  const RoomScanner({super.key});
  @override
  State<RoomScanner> createState() => _RoomScannerState();
}

class _RoomScannerState extends State<RoomScanner> {
  final controller = MobileScannerController(
    formats: [BarcodeFormat.qrCode],
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool finished = false;
  String? error;
  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Quét mã phòng')),
    body: Column(
      children: [
        Expanded(
          child: MobileScanner(
            controller: controller,
            onDetect: (capture) {
              if (finished) return;
              for (final barcode in capture.barcodes) {
                final code = roomCodeFromQr(barcode.rawValue ?? '');
                if (code != null) {
                  finished = true;
                  Navigator.of(context).pop(code);
                  return;
                }
              }
              setState(() {
                error = 'QR không chứa mã phòng được hỗ trợ. Hãy quét QR của phòng học.';
              });
            },
            errorBuilder: (context, error) => Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  'Không mở được camera. Kiểm tra quyền camera hoặc quay lại để nhập mã thủ công.\n${error.errorCode.name}',
                ),
              ),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(20),
          child: Text(
            error ?? 'Đưa mã QR vào camera. Thông tin phòng sẽ được kiểm tra trên hệ thống.',
          ),
        ),
      ],
    ),
  );
}
