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

Kiểm tra trực tiếp techai.sbs sau triển khai: ghép mã một lần trả đúng năm nguồn và quyền IP/vị trí mặc định tắt; gửi metadata giả lập cả năm nguồn thành công; gửi lại có 5 duplicates; trường body bị từ chối 422. Chi tiết metadata mới hiển thị trên portal. Đã xóa cả 5 bản ghi giả lập và thu hồi thiết bị kiểm tra, xác nhận token trả 401. HTTPS hoạt động và các đường dẫn .env, .git/config, storage/logs/laravel.log, composer.json bị chặn 403/404. Không dùng kết quả này để khẳng định APK đã chạy trên điện thoại thật.

APK 0.1.0: 1.883.702 byte; SHA-256 `df477cc796c1e7eaeb553bfff3872e00b82e863a9989b726a12ef08b2cc868bf`. Chứng thư ký cài thử SHA-256 `6231f91e4ce9bef3b587bd8036bc16fcad64e5291e9430474df1fb4d8513bf77`.

## Bản Agent Android 0.2.0

Đã chạy 26/26 Laravel tests trên MySQL local, 206 assertions: cấp sáu nguồn khi ghép; snapshot chỉ nhận khi có quyền device; whitelist metadata và giới hạn trường; mã hóa JSON; escaped Blade; không đọc/xóa/hiển thị snapshot chéo tài khoản; vị trí bắt buộc allow_location. Migrations và SQL schema sạch được kiểm tra trên database riêng (14 bảng portal_, 6 migration records), giữ nguyên bảng legacy. Composer audit không có advisory.

Android: assembleRelease, testDebugUnitTest (5 tests, 0 failures) và lintRelease (0 errors, 19 warnings về i18n, commit SharedPreferences, phiên bản dependency) đạt. Các pure unit tests kiểm tra cả hai đồng ý vị trí, không dùng tọa độ trước lúc đồng ý/quá 5 phút và khoảng cách lấy mẫu 15 phút. Chưa phải kiểm thử integration service/permission trên Android thật.

APK 0.2.0 minSdk 26 / targetSdk 36, versionCode 2; release không debuggable, chữ ký v2/v3 được apksigner xác minh và cùng chứng thư 0.1.0. File 1.887.798 byte; SHA-256 `71f62d918473f4607d59e57e90dd1de3734975a8ab03e515b4d81d2a847a7e28`. APK và khóa ký bị ignore, mã nguồn có trên Git.

Người dùng cung cấp ảnh cho thấy 0.1.0 bị Play Protect chặn. 0.2.0 chưa được kiểm tra cài đặt trên A34 hoặc được Google xem xét; không tuyên bố đã hết chặn. Agent không có lệnh điều khiển từ xa, không dùng Accessibility/đọc màn hình, không tự cấp quyền/tắt bảo vệ. Vị trí chỉ khởi động từ Activity theo thao tác và xác nhận riêng; foreground service không exported, START_NOT_STICKY, không tự khởi động lại sau reboot/kill. Dừng chia sẻ xóa tọa độ RAM/mẫu device đang chờ; batch bỏ tọa độ khi phiên/quyền đã tắt. Yêu cầu đã truyền đi có thể hoàn tất sau khi dừng. Cần kiểm tra thực tế các đường pause/revoke/offline/GPS/kill trên A34 trước khi coi là sẵn sàng sản xuất.

## Hiển thị metadata trong lịch sử

Bảng lịch sử hiển thị chiều gọi tiếng Việt, thời lượng, thiết bị sở hữu, app_package, IP/vị trí/sai số nếu đã lưu, máy chủ khai báo nếu có, mẫu agent và thời điểm máy chủ nhận. Nút Chi tiết dẫn đến toàn bộ metadata. Không xin thêm quyền, không thay APK, không bổ sung ngược trường chưa thu thập. Quan hệ event/device được eager-load và lọc theo tài khoản, policy vẫn kiểm tra trang chi tiết. Blade tiếp tục thoát tên, gói ứng dụng và metadata. Laravel MySQL: 26/26 tests, 212 assertions đạt; Pint đạt.

## VPN cục bộ 0.3.0

Laravel MySQL local: 27/27 tests đạt, 242 assertions. Ca mới kiểm tra IP v4/v6, quyền kép network + app_source, package whitelist, literal IP/cổng/protocol, từ chối payload/hostname và nguồn không được phép, mã hóa JSON, idempotency và không đọc/xóa/xem dữ liệu chéo tài khoản. Mã ghép chỉ có network bị từ chối. SQL schema sạch đã được import kiểm chứng trên database disposable: 14 bảng portal_, 7 migration records, không có application data; legacy được giữ. Pint và Composer audit đạt (không advisory).

Android: assembleRelease, testDebugUnitTest (14 tests, 0 failures/skips) và lintRelease (0 errors, 22 warnings) đạt. Test bao gồm relay TCP/UDP thực qua loopback cho IPv4/IPv6, byte giữ nguyên, xác thực SOCKS theo phiên và không kết nối nếu bảo vệ socket thất bại; header parser bỏ gói lỗi/phân mảnh, đọc IPv4/IPv6, không phụ thuộc payload; dedup/rate/cache bounded. Đây là kiểm thử Java trên JDK, không phải TUN/Android kernel hay app Zalo/Viber/email thật. Marker phiên/khóa hàng đợi được bổ sung để chặn producer/worker cũ gửi hoặc acknowledge hàng đợi mới khi ghép lại; instrumentation race trên A34 còn chờ.

Native AAR upstream 2.20.0 được pin commit/asset SHA-256, kèm license/provenance trong android/third-party và app assets. Không rebuild/audit đầy đủ native C ở lần này; checksum chứng minh artifact khớp release, không chứng minh không có lỗi. ELF arm64-v8a/x86_64 được kiểm tra load alignment tối thiểu 16KB. APK release versionCode 3, minSdk 29 / targetSdk 36, không debuggable; ký cùng chứng thư cài thử và verify cho SDK 29–36, zipalign 16KB đạt. APK và khóa riêng ngoài Git.

adb devices không có thiết bị. Chưa xác minh VPN khởi động/khôi phục mạng, UID attribution, native bridge qua Unix datagram, DNS/Private DNS, QUIC, switching network, foreground permission, stop/revoke/kill/reboot hoặc chạy dài trên A34. Không tuyên bố APK đã được Play Protect chấp nhận hoặc VPN đã sẵn sàng sản xuất. Các bước thử còn chờ trong ANDROID-APP.md. Không giải mã TLS, cài CA, ghi payload/PCAP, URL, DNS/SNI, hoặc liên kết IP đích với người nhận cụ thể; VPN chỉ quan sát các package được chọn. Lỗi chuyển tiếp/native đóng TUN và kết thúc phiên, cần xác minh hành vi này trên máy thật.

APK VPN 0.3.0: 6.668.583 byte, SHA-256 b4df755bc164d27b6cf80b26d2925e7390bfc196d0c04d3d0f22b5f4c37e3c4b; apksigner xác minh chữ ký v3 cho API 29–36, cùng chứng thư 6231f91e4ce9bef3b587bd8036bc16fcad64e5291e9430474df1fb4d8513bf77.
