# Báo cáo kiểm tra · 21/11/2026

## Phạm vi bàn giao

Theo lựa chọn cuối của người dùng: **hoàn thiện mã nguồn và hướng dẫn trước**. Chưa cấu hình hoặc bật ứng dụng mới trên hosting. Không coi kết quả local hay trạng thái hosting cũ là xác minh triển khai production của portal mới.

## Đã chạy

- PHP CLI **8.4.27**, Laravel **13.43.0**, MySQL Community **8.4.13**, InnoDB, Windows local.
- Toàn bộ PHPUnit: **21 tests, 21 passed, 136 assertions**, bao gồm hai bài kiểm tra đồng thời bằng hai tiến trình PHP dùng cùng MySQL database riêng. Không bỏ qua test MySQL trong lượt này.
- `composer validate --no-check-publish`: hợp lệ. `composer audit --locked --format=json`: advisories=[], abandoned=[]. Kết quả tại thời điểm chạy, cần chạy lại khi triển khai hoặc cập nhật dependency.
- Pint đã chạy và sửa định dạng. Blade view cache thành công. JavaScript kiểm tra cú pháp thành công.
- Git ignore chặn `.env`, runtime, vendor, SQLite/SQL, log và archive; `.env.example` chỉ chứa giá trị mẫu, khóa production để trống. Khóa trong phpunit.xml là fixture công khai chỉ dành cho test, không dùng ở môi trường thật.
- App local chạy trên 127.0.0.1:8000, dùng database riêng và user MySQL runtime chỉ SELECT/INSERT/UPDATE/DELETE. User migration/test có quyền cao hơn chỉ trong database local. HTTP local: login 200, register 404, .env 404, storage/logs/laravel.log bị chặn. CSP xuất hiện trên phản hồi.

## Phạm vi test bảo mật

| Nhóm | Kết quả |
|---|---|
| Khách chưa đăng nhập / tắt đăng ký | Web redirect đăng nhập; register GET/POST 404 |
| Hai tài khoản, hai thiết bị | Tài khoản A không đọc/lọc/tìm số/xóa sự kiện B; sửa ID URL/payload bị chặn; thống kê chỉ tính tài khoản hiện tại |
| Quản trị | Không đọc/xóa lịch sử B mặc dù là admin; người thường không vào quản trị; tạo tài khoản gửi notification và ghi audit |
| Phân tách auth | Bearer thiết bị không vào admin; phiên web đơn thuần không gửi được sự kiện API |
| 2FA | Admin chưa xác nhận không vào portal hoặc tạo mã ghép; TOTP đúng cho phép truy cập; tái sử dụng cùng mã bị từ chối; secret mã hóa khi lưu |
| Mã ghép | Có hạn ngắn, chỉ lưu hash, không dùng lại; hai yêu cầu đồng thời trả 201 và 422, chỉ tạo một thiết bị/token |
| Token | Quyền tối thiểu, expiry bắt buộc; sai ability, token hết hạn, token thu hồi, thiết bị thu hồi, tài khoản vô hiệu hóa bị từ chối |
| Idempotency | Gửi lại cùng event_id chỉ tạo một hàng trên cùng thiết bị; hai thiết bị dùng cùng UUID vẫn là hai sự kiện độc lập; hai yêu cầu đồng thời cùng sự kiện trả 200/201 và chỉ tạo một hàng |
| Dữ liệu | Từ chối content/body và trường lạ, nguồn chưa cho phép, vị trí chưa cho phép, thời gian sai định dạng/tương lai, tên quá dài; số/tên/IP/vị trí/máy chủ được mã hóa khi lưu; chỉ mục số là HMAC riêng |
| Hiển thị | Tên chứa script hiển thị dưới dạng văn bản escape; các trang Blade chính render thành công |
| Giới hạn | Login, password link, TOTP, pairing và ingest theo thiết bị đều có test 429; lô >50 hoặc >64 KiB bị từ chối |
| Phiên | CSRF thiếu token bị 419; test cookie Secure/HttpOnly/Lax; code đăng nhập và xác nhận 2FA tạo lại phiên |
| Mật khẩu | Token thiết lập/đặt lại hết hạn và không dùng lại; setup_pending được gỡ khi hoàn tất; liên kết email dùng fragment cho token |
| Retention | Lệnh purge xóa lịch sử quá thời hạn cấu hình |

## Hiện trạng tên miền, không phải app mới

Trước khi push ứng dụng mới, HTTPS tại techai.sbs đã trả lời qua TLS được Node fetch xác minh mặc định; HTTP `/login` chuyển 301 sang HTTPS. HTTPS `/login` trả 404 (ứng dụng cũ đã bị xóa). Các đường dẫn đã thử: `/.env` 403, `/portal/.env` 403, `/storage/logs/laravel.log` 404, `/portal/storage/logs/laravel.log` 404, `/backup.sql` 403, `/backup.zip` 404, `/.git/config` 403. Không thấy dấu hiệu APP_KEY, DB_PASSWORD, khóa riêng, SQL CREATE TABLE hoặc Git config trong nội dung trả về. Đây chỉ là baseline của hosting hiện có. Không xác nhận các thư mục/path backup khác chưa được cung cấp.

## Chưa xác minh / phải làm trước production

- Phiên bản PHP **web và CLI chính xác** trên hosting. Người dùng xác nhận 8.4 hoặc 8.5; chưa có truy cập SSH đã đối chiếu khóa để tự đọc. PHP 8.5 được thêm vào CI nhưng chưa chạy local trong phiên này.
- CI GitHub chưa được quan sát hoàn tất. Không ghi kết quả CI là đã đạt.
- Document root `portal/public`; MySQL riêng cùng quyền runtime/migration thực tế; `.env`, storage và các khóa ngoài Git; chạy migrations/preflight/config cache trên hosting.
- SMTP thật và nhận thư mời; tài khoản quản trị đầu tiên, thiết lập mật khẩu và 2FA trên hosting.
- HTTPS redirect, cookie, CSP và auth/API end-to-end của **bản mới**, kiểm tra bên ngoài các URL nhạy cảm sau triển khai. `.htaccess` gốc từ chối truy cập nếu hosting còn trỏ vào repository root; 403 tại đây không phải portal đã hoạt động.
- Cron thực tế và theo dõi lỗi purge. `BACKUP_RETENTION_DAYS` không tự chỉnh chính sách backup Hostinger. Script backup chưa chạy trên hosting; chưa cài/kiểm tra age và chưa thử restore. Cần xác minh thời hạn giữ, khóa giải mã, quyền truy cập và quy trình khôi phục/xóa với nhà cung cấp.
- Cấu hình access log, WAF, APM, crash reporting thực tế: không ghi token/mật khẩu/mã ghép/số đầy đủ/body. Chưa kiểm tra hệ thống log của Hostinger.
- Android: có hợp đồng API, lớp Keystore mẫu và quy tắc loại token khỏi backup. Chưa có APK/bộ thu thập metadata hoàn chỉnh; chưa biên dịch với Android SDK, chưa test Keystore/backup/quyền trên thiết bị thật.

Hướng dẫn thao tác: [DEPLOYMENT.md](DEPLOYMENT.md). Hợp đồng Android và giới hạn dữ liệu: [ANDROID-API.md](ANDROID-API.md).
