/* Local UI demo only. No API requests or changes to real helpdesk records. */
(() => {
  const tasks = [
    { id: 'SC-2026-081', title: 'Tay nắm cửa phòng học bị lỏng', location: 'Khu giảng đường A · Phòng A.101', device: 'DOOR-A101', date: '2026-11-02', time: '08:00', end: '09:00', status: 'resolved', priority: 'normal', category: 'mechanical', reporter: 'Minh Anh', role: 'Cán bộ quản lý phòng / khoa', description: 'Tay nắm cửa chính bị lỏng, khó đóng mở. Kiểm tra và siết lại các vị trí liên kết trước giờ học.', instruction: 'Liên hệ cán bộ quản lý phòng để nhận bàn giao.', report: { category: 'Cơ khí / Bàn ghế', rootCause: 'Hao mòn tự nhiên', note: 'Đã thay vít liên kết tay nắm cửa, căn chỉnh chốt và kiểm tra đóng mở nhiều lần. Cửa hoạt động bình thường, đã bàn giao cho cán bộ quản lý phòng.', materials: [{ name: 'Vít liên kết tay nắm', quantity: '2', unit: 'cái' }], verifier: 'Minh Anh', role: 'Cán bộ quản lý phòng / khoa', submittedAt: '2026-11-02T09:00:00+07:00', before: [], after: [], signature: '', sample: true } },
    { id: 'SC-2026-084', title: 'Máy lạnh không hoạt động', location: 'Khu giảng đường A · Phòng A.203', device: 'AC-203-02', date: '2026-11-02', time: '09:30', end: '11:00', status: 'in_progress', priority: 'high', category: 'electrical', reporter: 'Lan Nguyễn', role: 'Giảng viên đứng lớp', description: 'Máy lạnh không khởi động dù đã bật điều khiển. Đèn báo trên thiết bị không sáng. Sự cố xuất hiện trước giờ học buổi sáng.', instruction: 'Phòng có tiết học lúc 11:30. Vui lòng kiểm tra nguồn điện, chạy thử thiết bị và xác nhận với giảng viên trước khi bàn giao.' },
    { id: 'SC-2026-089', title: 'Máy chiếu không nhận tín hiệu', location: 'Khu giảng đường B · Phòng B.105', device: 'PJ-105-01', date: '2026-11-02', time: '14:00', end: '15:00', status: 'assigned', priority: 'normal', category: 'av', reporter: 'Hoàng Trần', role: 'Giảng viên đứng lớp', description: 'Máy chiếu bật được nhưng không nhận tín hiệu HDMI từ máy tính của giảng viên.', instruction: 'Mang theo cáp HDMI dự phòng. Phòng trống từ 14:00 đến 15:00.' },
    { id: 'SC-2026-092', title: 'Wi-Fi chập chờn tại phòng máy', location: 'Khu thực hành · Phòng TH.02', device: 'AP-TH02', date: '2026-11-03', time: '08:30', end: '10:00', status: 'assigned', priority: 'high', category: 'network', reporter: 'Nam Minh', role: 'Cán bộ quản lý phòng / khoa', description: 'Kết nối Wi-Fi bị gián đoạn khi nhiều máy truy cập đồng thời.', instruction: 'Kiểm tra điểm truy cập và đường truyền trong giờ phòng máy trống.' },
    { id: 'SC-2026-095', title: 'Bóng đèn hành lang bị hỏng', location: 'Khu hành chính · Tầng 2', device: 'LIGHT-H201', date: '2026-11-05', time: '13:30', end: '14:30', status: 'assigned', priority: 'normal', category: 'electrical', reporter: 'Hải Khánh', role: 'Giám thị tòa nhà', description: 'Hai bóng đèn hành lang không sáng. Đề nghị kiểm tra và thay thế.', instruction: 'Chuẩn bị bóng đèn phù hợp và bảo đảm an toàn điện khi thi công.' }
  ];
  let database;
  function open() {
    if (!database) database = new Promise((resolve, reject) => {
      const request = indexedDB.open('helpdesk-technician-ui-demo', 1);
      request.onupgradeneeded = () => request.result.createObjectStore('tasks', { keyPath: 'id' });
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
      request.onblocked = () => reject(new Error('Không mở được bộ nhớ bản thử.'));
    });
    return database;
  }
  async function read() {
    const db = await open();
    return new Promise((resolve, reject) => {
      const request = db.transaction('tasks').objectStore('tasks').getAll();
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  async function all() {
    const saved = await read();
    return tasks.map((task) => ({ ...task, ...saved.find((item) => item.id === task.id) }));
  }
  async function update(id, change) {
    if (!tasks.some((task) => task.id === id)) throw new Error('Không tìm thấy nhiệm vụ.');
    const db = await open();
    await new Promise((resolve, reject) => {
      const tx = db.transaction('tasks', 'readwrite');
      const store = tx.objectStore('tasks');
      const request = store.get(id);
      request.onsuccess = () => store.put({ ...(request.result || {}), ...change, id });
      tx.oncomplete = resolve;
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error);
    });
  }
  window.TechnicianDemo = {
    all, update, samples: () => tasks.map((task) => ({ ...task })),
    selectedId: () => new URLSearchParams(location.search).get('id') || 'SC-2026-084',
    labels: { assigned: 'Được phân công', in_progress: 'Đang xử lý', resolved: 'Đã nghiệm thu' },
    detailUrl: (id) => document.body.dataset.app ? `#task?id=${encodeURIComponent(id)}` : `technician-task.html?id=${encodeURIComponent(id)}`,
    reportUrl: (id) => document.body.dataset.app ? `#report?id=${encodeURIComponent(id)}` : `technician-task.html?id=${encodeURIComponent(id)}#report`,
    scheduleUrl: () => document.body.dataset.app ? '#schedule' : 'technician.html',
    resolveUrl: (id) => document.body.dataset.app ? `#resolve?id=${encodeURIComponent(id)}` : `ticket-resolve.html?id=${encodeURIComponent(id)}`
  };
})();
