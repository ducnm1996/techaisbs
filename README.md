# TechAI · Nhật ký thiết bị

Portal tiếng Việt bằng Laravel 13, Blade, MySQL; phiên web và token thiết bị Sanctum được tách biệt.

Mã ứng dụng nằm trong `portal/`. Document root của hosting phải là `portal/public`, không phải gốc repository. Tệp `.htaccess` ở gốc cố ý từ chối truy cập khi cấu hình sai.

Đây là hệ thống nhận metadata do thiết bị được chủ sở hữu ghép và cho phép gửi. Không thu nội dung tin nhắn, email, thông báo; không suy đoán IP/vị trí của người liên hệ. Xem hướng dẫn triển khai, hợp đồng API và báo cáo kiểm tra trong `docs/`.
