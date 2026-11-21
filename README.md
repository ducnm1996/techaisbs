# TechAI · Nhật ký thiết bị

Portal tiếng Việt bằng Laravel 13, Blade, MySQL; phiên web và token thiết bị Sanctum được tách biệt. Ứng dụng Laravel nằm ngay tại gốc repository: `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/` và `artisan`.

Document root của hosting phải là `<release>/public` (nếu checkout nằm trong public_html: `public_html/public`). Tệp `.htaccess` ở gốc tiếp tục chặn truy cập vào mã nguồn khi hosting cấu hình sai. Di chuyển thư mục không tự đổi document root của Hostinger.

Hướng dẫn triển khai: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Hợp đồng Android: [docs/ANDROID-API.md](docs/ANDROID-API.md). Báo cáo kiểm tra: [docs/SECURITY-VERIFICATION.md](docs/SECURITY-VERIFICATION.md).

Hệ thống chỉ nhận metadata từ thiết bị được chủ tài khoản ghép và cho phép; không thu nội dung tin nhắn/email/thông báo, không suy đoán IP/vị trí người liên hệ. `.env`, khóa và thông tin kết nối nằm ngoài Git. Bảng mới dùng tiền tố `portal_` để giữ dữ liệu cũ trong database hiện có.

## Chạy local và kiểm thử

Cấu hình `.env` riêng, `composer install`, chạy migrations, rồi `php artisan serve --host=127.0.0.1`. HTTP local cần SESSION_SECURE_COOKIE=false; không dùng thiết lập này trên hosting. Không cần npm/Vite; CSS và JavaScript được phục vụ từ `public/assets`.

Kiểm thử MySQL: dùng một database **riêng, có thể xóa, tên kết thúc `_testing`**, đặt DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD rồi chạy `php vendor/phpunit/phpunit/phpunit`. Bộ test có thao tác migrate:fresh/db:wipe; tuyệt đối không dùng database hosting hoặc dữ liệu thật.

Có thể chạy nhanh bằng SQLite với cùng lệnh; ba test MySQL sẽ bị bỏ qua, không thay thế kiểm thử khóa đồng thời trên MySQL. Các khóa trong phpunit.xml chỉ là fixture công khai dành cho test.

## Android

App cài thử nằm trong [android/](android/) với hướng dẫn [cài trên Samsung A34 và build APK](docs/ANDROID-APP.md). Mỗi nguồn mặc định tắt; ghép bằng mã một lần rồi người dùng chọn quyền và bật đồng bộ. Zalo/Viber/email chỉ có metadata thông báo, không truy cập nội dung. APK và khóa ký không đưa lên Git.

Agent Android 0.2 bổ sung trạng thái thiết bị/pin/bộ nhớ/mạng và phiên chia sẻ vị trí tùy chọn có thông báo thường trực. Portal có nguồn device riêng và thẻ mẫu cuối trên trang Thiết bị. Thiết bị ghép cũ cần mã mới để có quyền nguồn mới; không tự mở rộng quyền.

VPN cục bộ 0.3 (Android 10+) bổ sung nguồn network để ghi IP đích/cổng/TCP-UDP của Zalo, Viber và email khi người dùng bật và xác nhận VPN. Dữ liệu không có payload/DNS/TLS, chỉ gắn ứng dụng khi xác định được UID; xem [phạm vi và cách thử trên A34](docs/ANDROID-APP.md). Thư viện native được pin và có [license/provenance](android/third-party/README.md).
