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

PHP 8.4.27, Laravel 13.43.0, MySQL Community 8.4.13 InnoDB: **23/23 tests đạt, 153 assertions**, gồm hai tiến trình gửi đồng thời trên cùng database và test giữ nguyên bảng/dữ liệu legacy khi tạo namespace portal_. Không bỏ qua test MySQL trong lượt bàn giao. Chốt chặn chỉ cho phép test phá hủy schema trên database disposable có tên kết thúc `_testing`.

Các ca test bao gồm: hai tài khoản/hai thiết bị; không đọc, lọc, tìm số hoặc xóa dữ liệu chéo tài khoản; admin không đọc lịch sử riêng của B; tách bearer thiết bị khỏi phiên web; mã ghép một lần và race; token hết hạn/thu hồi/tài khoản vô hiệu hóa; idempotency theo thiết bị; đăng nhập quản trị trực tiếp bằng mật khẩu; mã hóa trường nhạy cảm/HMAC; escape Blade; giới hạn lô/rate; CSRF/cookie; token mật khẩu có hạn và không dùng lại; purge lịch sử quá hạn.

Pint, composer validate, Blade cache, cú pháp JavaScript và script shell đạt. Composer audit local không có advisory/abandoned tại thời điểm chạy. `.env`, `.env.hostinger`, vendor, runtime, SQL dữ liệu và log được Git bỏ qua; chỉ file SQL schema không chứa dữ liệu người dùng được đưa vào exports/.

## Phần còn chờ trước khi sử dụng đầy đủ

- **SMTP thật:** chưa được cấu hình. Tài khoản quản trị ban đầu đã được cấp trực tiếp theo yêu cầu chủ website; chưa thử gửi thư mời qua SMTP trên hosting.
- **Cron:** tài khoản SSH không có crontab; chưa cài được scheduler. Cần thêm cron trong hPanel: mỗi phút chạy `cd <domain>/portal-app && php artisan schedule:run`. Chưa xác nhận purge chạy định kỳ thật.
- **Quyền MySQL runtime:** user hiện có vẫn được cấp quyền DDL. Cần thu hẹp về DML trên các bảng portal_ và tách tài khoản migrations, không cấp quyền đọc bảng cũ nếu không cần. Chưa tự thay quyền database qua hPanel.
- **Engine database:** MariaDB tương thích driver MySQL hiện chạy được; cần xác nhận nếu chỉ chấp nhận Oracle MySQL.
- **Backup:** script chỉ xuất portal_ và mã hóa bằng age; chưa cài/kiểm tra age, chưa chạy backup/restore thực tế hoặc xác minh thời hạn giữ backup Hostinger. BACKUP_RETENTION_DAYS không tự chỉnh chính sách hPanel.
- Chưa kiểm tra cấu hình access log/WAF/APM/crash reporting của nhà cung cấp. App không ghi body/token/mật khẩu/mã ghép/số đầy đủ vào log.
- Chưa quan sát CI GitHub hoàn tất; không tính CI là đã đạt.
- **Đồng bộ Git cũ:** đã phát hiện hPanel chép lại toàn bộ repo vào vùng public sau push, làm 403 tái phát. Đã khôi phục public sạch và kiểm tra login 200/CSRF 419/API 401. Người dùng xác nhận đã tắt auto-deploy cũ trong hPanel; các lần cập nhật chuyển sang SSH vào checkout riêng, xem update-hostinger.sh. Không coi việc Git nhận commit mới là đủ để xác nhận portal vẫn chạy.
- Android đã có APK cài thử và mã nguồn trong android/; kiểm thử thiết bị A34 thật còn chờ. Xem mục bản Android 0.1.0 bên dưới.

Hướng dẫn: [DEPLOYMENT.md](DEPLOYMENT.md). Hợp đồng Android: [ANDROID-API.md](ANDROID-API.md).

## Thay đổi yêu cầu: bỏ xác thực hai lớp

Chủ website yêu cầu bỏ bước 2FA. Đăng nhập quản trị chuyển thẳng tới portal bằng email/mật khẩu; URL GET /2fa cũ chuyển về trang chủ, không còn endpoint xác nhận mã hoặc giao diện/CLI thiết lập 2FA. Các cột MFA cũ giữ để tương thích schema nhưng không tham gia xác thực; không cần import lại SQL. Tiêu chí bắt buộc 2FA ban đầu đã được thay đổi theo yêu cầu này. Phân quyền tài khoản, scope dữ liệu, CSRF, cookie và rate limit đăng nhập vẫn được kiểm tra.

Kiểm thử sau khi bỏ 2FA: 23/23 đạt, 153 assertions trên MySQL local; đăng nhập trực tiếp quản trị và điều hướng URL 2FA cũ đã được kiểm tra.

## Bản Android 0.1.0

Đã build release Android minSdk 26 / targetSdk 36, chạy 3 JUnit tests (0 lỗi) và lintRelease (0 errors, 15 warnings về i18n, commit SharedPreferences và phiên bản dependency). APK không debuggable; apksigner xác minh chữ ký v2/v3, zipalign thành công. Composer audit không có advisory ở lần chạy này. Laravel MySQL: 24/24 tests, 174 assertions, gồm capabilities trả về khi ghép, mã hóa metadata mới và cách ly dữ liệu.

APK có chữ ký cài thử, không có secret production; khóa ký riêng được giữ trong runtime local bị ignore. Không có A34 hoặc emulator nối với môi trường này: chưa kiểm thử cài đặt, Android Keystore, quyền bị hạn chế, NotificationListener và đồng bộ nền trên máy thật. Cần hoàn tất bước này khi người dùng cài APK. Không đánh đồng build/lint/unit test với kiểm thử thiết bị.
