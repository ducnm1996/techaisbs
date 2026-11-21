# TechAI · Nhật ký thiết bị

Portal tiếng Việt bằng Laravel 13, Blade, MySQL; phiên web và token thiết bị Sanctum được tách biệt. Ứng dụng Laravel nằm ngay tại gốc repository: `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/` và `artisan`.

Document root của hosting phải là `<release>/public` (nếu checkout nằm trong public_html: `public_html/public`). Tệp `.htaccess` ở gốc tiếp tục chặn truy cập vào mã nguồn khi hosting cấu hình sai. Di chuyển thư mục không tự đổi document root của Hostinger.

Hướng dẫn triển khai: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Hợp đồng Android: [docs/ANDROID-API.md](docs/ANDROID-API.md). Báo cáo kiểm tra: [docs/SECURITY-VERIFICATION.md](docs/SECURITY-VERIFICATION.md).

Hệ thống chỉ nhận metadata từ thiết bị được chủ tài khoản ghép và cho phép; không thu nội dung tin nhắn/email/thông báo, không suy đoán IP/vị trí người liên hệ. `.env`, khóa và thông tin kết nối nằm ngoài Git. Bảng mới dùng tiền tố `portal_` để giữ dữ liệu cũ trong database hiện có.

## Chạy local và kiểm thử

Cấu hình `.env` riêng, `composer install`, chạy migrations, rồi `php artisan serve --host=127.0.0.1`. HTTP local cần SESSION_SECURE_COOKIE=false; không dùng thiết lập này trên hosting. Không cần npm/Vite; CSS và JavaScript được phục vụ từ `public/assets`.

Kiểm thử MySQL: dùng một database **riêng, có thể xóa, tên kết thúc `_testing`**, đặt DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD rồi chạy `php vendor/phpunit/phpunit/phpunit`. Bộ test có thao tác migrate:fresh/db:wipe; tuyệt đối không dùng database hosting hoặc dữ liệu thật.

Có thể chạy nhanh bằng SQLite với cùng lệnh; ba test MySQL sẽ bị bỏ qua, không thay thế kiểm thử khóa đồng thời trên MySQL. Các khóa trong phpunit.xml chỉ là fixture công khai dành cho test.
