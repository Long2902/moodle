# Cài đặt/nâng cấp

1. Sao lưu database Moodle.
2. Cài ZIP plugin trong `Site administration → Plugins → Install plugins`.
3. Khi nâng từ V11.0–V11.2, cài đè; không uninstall để giữ dữ liệu.
4. Xác nhận release `11.3.0-beta4`, build `2026080101`.
5. Hoàn tất trang Notifications/Upgrade database.
6. Purge all caches.
7. Cấu hình Engine URL và shared secret trong phần cài đặt activity module.
8. Nghiệm thu đổi nhóm giữa buổi, nộp ảnh, editor và màn hình chấm điểm trên staging.

Python Engine có thể chạy trên cPanel Passenger; Docker không bắt buộc.
