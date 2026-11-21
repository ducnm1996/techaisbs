# Triển khai Hostinger

## Phiên bản và điều kiện đã biết

Người dùng xác nhận hosting đã chuyển sang PHP 8.4 hoặc 8.5 (21/11/2026). Laravel 13 yêu cầu PHP >=8.3 và được hỗ trợ bảo mật đến 17/03/2028: https://laravel.com/framework/docs/13.x/releases. Composer khóa nền tảng tối thiểu 8.3.36 để không kéo dependency chỉ chạy PHP mới hơn. Kiểm thử local dùng PHP 8.4.27 và MySQL 8.4.13. Chưa đăng nhập được SSH: khóa máy chủ chưa được đối chiếu; không tự bỏ qua kiểm tra SSH. Cần xác nhận phiên bản PHP web/CLI cụ thể trong hPanel trước khi bật ứng dụng.

Extension: ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo_mysql, session, tokenizer, xml. Đồng bộ đồng hồ NTP cho TOTP. MySQL 8.0+ InnoDB, utf8mb4, UTC. Không dùng MyISAM. Cấu hình PHP không hiển thị lỗi, không đưa đối số vào trace.

## Cài đặt

1. Trỏ document root chính xác đến `<release>/public`. Nếu Hostinger cố định tên thư mục `public_html`, đặt checkout ở thư mục riêng ngoài web (ví dụ `<domain>/portal-app`) rồi cho `public_html` là symlink tới `<domain>/portal-app/public`. Chỉ vùng public được phục vụ; không dùng rewrite để mở toàn bộ repository. Sao lưu/bảo toàn checkout cũ trước khi đổi đường dẫn và kiểm tra Git auto-deploy sau thay đổi. `.htaccess` gốc vẫn từ chối truy cập mã nguồn khi cấu hình sai.
2. Theo yêu cầu mới, dùng lại database hiện có. Cấu hình tên database và tài khoản trong `.env` riêng ngoài Git, đặt `DB_TABLE_PREFIX=portal_`. Toàn bộ bảng mới, gồm cả `portal_migrations`, `portal_users`, session/cache/token, nằm trong namespace này; không dùng hoặc sửa bảng `users`, `meals`, `checkups`, `migrations` cũ. Sao lưu trước, kiểm tra chưa có nhóm bảng `portal_` thuộc ứng dụng khác và dùng `php artisan migrate --pretend` để xem DDL trước khi chạy. Không tự đổi prefix khi portal đã có dữ liệu. Tài khoản cũ không tự được nhập vào portal mới. Tạo user runtime riêng chỉ SELECT/INSERT/UPDATE/DELETE trên các bảng `portal_*` cần thiết; nếu hPanel chỉ cấp quyền toàn database, cần người vận hành giảm quyền theo bảng hoặc ghi nhận giới hạn này trước production. User migrations riêng chỉ cấp DDL cần thiết trong database hiện có, không cấp quyền toàn server. Không dùng root trên hosting.
3. Tạo thư mục shared ngoài `public_html` và checkout, chỉ chủ tài khoản đọc được (directory 700, file 600). Tạo `.env` từ `.env.example` tại đó, điền SMTP và MySQL thật. APP_ENV=production, APP_DEBUG=false, HTTPS URL chính xác, session Secure/HttpOnly/Lax và mã hóa phiên. Tạo APP_KEY bằng `php artisan key:generate` trên `.env` riêng; tạo HISTORY_INDEX_KEY bằng `php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'`, lưu an toàn không ghi vào Git/log/chat. Hai khóa phải độc lập. Không thay khóa nếu chưa có kế hoạch tái mã hóa và tái lập chỉ mục.
4. `shared/storage` chứa app/private, framework/cache/data, framework/sessions, framework/views, logs; chỉ tiến trình PHP và chủ tài khoản được ghi. Không tạo `public/storage` symlink. Sao lưu khóa độc lập với database và backup. Dùng `ops/deploy.sh` hoặc các bước tương đương, cấu hình `SHARED_DIR`. Script không tự chuyển document root, không tự quản lý tài khoản database. Nếu dùng user migration riêng, chạy phần migrate trong ngữ cảnh bí mật riêng, sau đó config:cache bằng runtime credentials.

   Hostinger có thể tắt `proc_open` trong PHP CLI. Script dùng `composer install --no-scripts`, xóa các manifest/cache sinh tự động rồi chạy `php artisan package:discover` trực tiếp, tránh Composer gọi subprocess qua PHP. Không cần bật lại hàm PHP bị nhà cung cấp hạn chế.
5. Từ gốc dự án, chạy `composer install --no-dev --prefer-dist --optimize-autoloader`, `php artisan migrate --pretend`, rồi `php artisan migrate --force` sau khi đối chiếu bảng có tiền tố `portal_`; tiếp tục `php artisan portal:preflight`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`. Tuyệt đối không chạy migrate:fresh, db:wipe hoặc rollback trên database dùng chung của hosting. Chỉ chạy migrations tiến tới; có backup trước mỗi lần thay schema.

   **Nếu import qua phpMyAdmin lần đầu:** chọn database hiện có, kiểm tra chưa có bảng `portal_*`, rồi import `exports/techai-portal-schema.sql`. File tạo 14 bảng mới và 4 dòng ghi nhận migrations, không có dữ liệu người dùng, không DROP bảng và không chọn/tạo database khác. Đã thử import trên MySQL 8.4 và kiểm tra bảng cũ không thay đổi. Các migrations đã ghi nhận sẽ không chạy lại; các cập nhật migrations tiếp theo vẫn dùng `php artisan migrate --force`. Không import chồng file này khi portal đã có bảng/dữ liệu; không tải SQL lên thư mục public.
6. Cấu hình SMTP thật (không dùng mail log), gửi thử email, sau đó `php artisan portal:bootstrap-admin admin@example.com "Quản trị"`. Không có mật khẩu mặc định. Mở liên kết có hạn 60 phút, thiết lập mật khẩu, đăng nhập và bắt buộc xác nhận TOTP trước khi truy cập trang quản trị. Token email nằm trong URL fragment để không đi vào access log. Quên TOTP: người vận hành máy chủ xác minh danh tính ngoài hệ thống rồi dùng `portal:reset-mfa email --force`; thao tác có audit và hủy phiên cũ.
7. Cron mỗi phút: `cd <release> && php artisan schedule:run >/dev/null 2>&1`. Kiểm tra `history:purge` chạy thành công theo lịch 02:00 giờ Việt Nam. Cấu hình HISTORY_RETENTION_DAYS (mặc định 90). Sự kiện mới ngoài khoảng lưu bị từ chối. Dọn token hết hạn và mã ghép đã hết hạn theo cùng lịch.
8. Bật HTTPS và chuyển HTTP sang HTTPS tại Hostinger. Chỉ cấu hình trusted proxies theo IP/CIDR proxy thực tế của nhà cung cấp; không tin toàn bộ `X-Forwarded-*` từ Internet. Thay APP_URL bằng tên miền thật, xác minh web/CLI PHP trùng yêu cầu và `portal:preflight` thành công.

## Backup và dữ liệu đã xóa

`ops/backup.sh` chỉ sao lưu các bảng `portal_*`, không đọc/xuất bảng cũ không có tiền tố. Backup được mã hóa bằng age, không tạo SQL plaintext; dùng file credentials riêng 600 và backup-reader có SELECT trên các bảng portal. Script không xuất triggers/routines vì ứng dụng không sử dụng chúng. Chạy ngoài document root và Git, cài age nếu hosting có hỗ trợ, khóa giải mã giữ ngoài hosting. Mặc định xóa backup do script quản lý quá 7 ngày; dùng cron hàng ngày và theo dõi lỗi. Nếu Hostinger không hỗ trợ age/mysqldump, dùng dịch vụ backup mã hóa tương đương; chưa được coi là hoàn tất khi chưa thử restore. Bảng cũ cần quy trình backup riêng nếu vẫn muốn giữ, không đổi hoặc xóa chúng theo retention của portal mới.

Backup tự động của Hostinger là hệ thống khác: phải đặt thời hạn giữ tối đa đã chấp nhận, xác minh với nhà cung cấp và ghi lại. Xóa trong portal không đồng nghĩa xóa tức thì trong backup; dữ liệu có thể tồn tại đến hạn giữ backup. Sau restore phải chạy `history:purge`, thu hồi token cũ cần thiết và đối chiếu thao tác xóa kể từ thời điểm backup trước khi bật truy cập. Không đưa backup vào thư mục public. Chính sách BACKUP_RETENTION_DAYS không tự thay đổi chính sách backup hPanel.

## Các dữ liệu từ dự án cũ

Repository này có lịch sử từ dự án trước; phiên bản cũ từng chứa `.env`. Bản mới không theo dõi `.env`, vendor, database hoặc khóa. Không tái sử dụng APP_KEY, mật khẩu database/SSH từng đưa vào lịch sử/chat; thay chúng trên hosting trước khi đưa dữ liệu mới vào. Không tự viết lại lịch sử Git trong bản triển khai này.

## Checklist bên ngoài

Kiểm tra TLS certificate, HTTP redirect HTTPS, `/login` 200, đăng ký 404, cookie phiên, 2FA, SMTP và API thật. Kiểm tra `/.env`, `/portal/.env`, `/storage/logs/laravel.log`, `/portal/storage/logs/laravel.log`, `/backup.sql`, `/backup.zip`, `/.git/config` đều 403/404, không chỉ xem status mà còn xác nhận không có nội dung nhạy cảm. Tắt capture body/header của request trong WAF/APM/crash reporter; không log Authorization, pairing code, password, token, số đầy đủ hoặc request body. Ghi nhận thời điểm, phiên bản và deployment ID trong báo cáo. Xem `SECURITY-VERIFICATION.md` cho phần đã làm và còn chờ.

## Trạng thái site techai.sbs sau triển khai

Bố cục đã áp dụng: `<domain>/portal-app` chứa checkout và vendor production; `<domain>/portal-shared/.env` cùng storage là dữ liệu riêng; `<domain>/public_html` trỏ tới `portal-app/public`. Không cần đưa .env/vendor vào Git. Hooks Git trên hosting gọi script triển khai sau merge/checkout; khi đổi cách auto-deploy phải kiểm tra lại worktree và trang đăng nhập sau push.

PHP web thực tế 8.5.13, CLI 8.3.37. Database của gói Hostinger hiện là MariaDB 11.8.9 (kết nối MySQL-compatible), không phải Oracle MySQL. Namespace portal_ đã được kiểm tra khớp file SQL import.

Còn cần thao tác trong hPanel: tạo cron mỗi phút cho scheduler (SSH không có crontab), thu hẹp quyền runtime database đang còn DDL và xác minh chính sách backup. SMTP và email quản trị còn chờ người dùng cung cấp. Không coi portal là đã hoàn tất mọi tiêu chí production chỉ vì trang đăng nhập trả 200; xem báo cáo kiểm tra mới nhất.
