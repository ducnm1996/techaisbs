# Báo cáo kiểm tra · 21/11/2026

## Kết quả hiện tại trên Hostinger

Portal đã được đưa lên **https://techai.sbs/login**. Lỗi 403 của trang chủ và lỗi 500 của Laravel đã được xử lý. Đây là kết quả sau triển khai, không phải kết quả suy ra từ localhost.

- SSH được xác minh bằng fingerprint ECDSA khớp ảnh người dùng cung cấp; dùng credential SSH đã được người dùng cấp trực tiếp. Tải cấu hình riêng qua SFTP sau khi người dùng đồng ý cụ thể. Không dùng credential truy xuất từ lịch sử Git để đăng nhập SSH.
- PHP web **8.5.13**, PHP CLI **8.3.37**. Composer xác nhận lock file cài được trên CLI và dependencies production đã cài, không có dev dependencies.
- Database thực tế là **MariaDB 11.8.9**, truy cập bằng driver MySQL của Laravel. Đây không phải Oracle MySQL; nếu yêu cầu bắt buộc đúng engine Oracle MySQL thì cần thay môi trường database trước khi dùng dữ liệu thật.
- Đã kiểm tra 14 bảng `portal_`, 4 migration đã import và không có migration cần chạy thêm. Không thay đổi bảng/dữ liệu cũ. Trước khi tạo khóa mới đã kiểm tra namespace lịch sử mới chưa có sự kiện.
- Checkout được giữ ở thư mục riêng `<domain>/portal-app`; `public_html` là symlink tới `portal-app/public`. Git khi chạy từ `public_html` nhận đúng worktree riêng. Không mở toàn bộ repository bằng rewrite.
- `.env` và storage nằm tại `<domain>/portal-shared`, ngoài vùng web và ngoài Git. Khóa ứng dụng/HMAC được tạo trên máy chủ, không in ra hoặc đưa về Git. APP_DEBUG=false; cấu hình phiên Secure/HttpOnly/Lax và mã hóa phiên.
- `portal:preflight`, package discovery, migrate/status, config/route/view cache trên hosting đã chạy thành công. Composer bị giới hạn proc_open nên dùng --no-scripts và chạy Artisan trực tiếp.
- `composer audit --locked --no-dev --format=json` trên hosting: advisories=[], abandoned=[].

## Kiểm tra HTTP từ bên ngoài

| Kiểm tra | Kết quả |
|---|---|
| HTTP /login | 301 sang HTTPS |
| HTTPS / | 302 tới /login |
| HTTPS /login | 200, đúng nội dung trang đăng nhập TechAI |
| CSS portal | 200 |
| Cookie phiên | Secure=true, HttpOnly=true, SameSite=Lax |
| Header bảo vệ | CSP và HSTS có trong phản hồi |
| POST đăng nhập thiếu CSRF | 419 |
| POST API events chưa xác thực | 401 |
| /register | 404 |
| /admin chưa đăng nhập | 302 tới /login |
| /.env, /.git/config, /storage/logs/laravel.log | 403 |
| /composer.json | 404 |
| /backup.sql, /exports/techai-portal-schema.sql | 403 |

Nội dung phản hồi đã kiểm tra không chứa APP_KEY, DB_PASSWORD, khóa riêng, SQL CREATE TABLE hoặc Git config. Chưa khẳng định an toàn cho những đường dẫn backup tùy chỉnh chưa được cung cấp.

## Kiểm thử local đã chạy

PHP 8.4.27, Laravel 13.43.0, MySQL Community 8.4.13 InnoDB: **22/22 tests đạt, 144 assertions**, gồm hai tiến trình gửi đồng thời trên cùng database và test giữ nguyên bảng/dữ liệu legacy khi tạo namespace portal_. Không bỏ qua test MySQL trong lượt bàn giao. Chốt chặn chỉ cho phép test phá hủy schema trên database disposable có tên kết thúc `_testing`.

Các ca test bao gồm: hai tài khoản/hai thiết bị; không đọc, lọc, tìm số hoặc xóa dữ liệu chéo tài khoản; admin không đọc lịch sử riêng của B; tách bearer thiết bị khỏi phiên web; mã ghép một lần và race; token hết hạn/thu hồi/tài khoản vô hiệu hóa; idempotency theo thiết bị; TOTP chống replay; mã hóa trường nhạy cảm/HMAC; escape Blade; giới hạn lô/rate; CSRF/cookie; token mật khẩu có hạn và không dùng lại; purge lịch sử quá hạn.

Pint, composer validate, Blade cache, cú pháp JavaScript và script shell đạt. Composer audit local không có advisory/abandoned tại thời điểm chạy. `.env`, `.env.hostinger`, vendor, runtime, SQL dữ liệu và log được Git bỏ qua; chỉ file SQL schema không chứa dữ liệu người dùng được đưa vào exports/.

## Phần còn chờ trước khi sử dụng đầy đủ

- **Email quản trị và SMTP thật:** chưa được cung cấp. Chưa tạo tài khoản quản trị đầu tiên, chưa gửi thư mời hoặc thử đăng nhập/2FA dương tính trên hosting. Các luồng này đã được test local; không ghi là đã test thành công trên hosting.
- **Cron:** tài khoản SSH không có crontab; chưa cài được scheduler. Cần thêm cron trong hPanel: mỗi phút chạy `cd <domain>/portal-app && php artisan schedule:run`. Chưa xác nhận purge chạy định kỳ thật.
- **Quyền MySQL runtime:** user hiện có vẫn được cấp quyền DDL. Cần thu hẹp về DML trên các bảng portal_ và tách tài khoản migrations, không cấp quyền đọc bảng cũ nếu không cần. Chưa tự thay quyền database qua hPanel.
- **Engine database:** MariaDB tương thích driver MySQL hiện chạy được; cần xác nhận nếu chỉ chấp nhận Oracle MySQL.
- **Backup:** script chỉ xuất portal_ và mã hóa bằng age; chưa cài/kiểm tra age, chưa chạy backup/restore thực tế hoặc xác minh thời hạn giữ backup Hostinger. BACKUP_RETENTION_DAYS không tự chỉnh chính sách hPanel.
- Chưa kiểm tra cấu hình access log/WAF/APM/crash reporting của nhà cung cấp. App không ghi body/token/mật khẩu/mã ghép/số đầy đủ vào log.
- Chưa quan sát CI GitHub hoàn tất; không tính CI là đã đạt.
- Android hiện có hợp đồng API, lớp Keystore mẫu và quy tắc backup, chưa có APK/bộ thu thập hoàn chỉnh hoặc test thiết bị thật.

Hướng dẫn: [DEPLOYMENT.md](DEPLOYMENT.md). Hợp đồng Android: [ANDROID-API.md](ANDROID-API.md).
