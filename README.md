# Mầm · Nhật ký thai kỳ

Ứng dụng Laravel 13, PHP 8.4, MySQL 8.4, Blade và Bootstrap 5.3.8. Giao diện tiếng Việt, thích ứng điện thoại và máy tính.

## Sử dụng local trên máy này

Chạy PowerShell trong thư mục dự án và thực hiện:

```powershell
.\start-local.ps1
```

Mở http://127.0.0.1:8000 rồi tạo tài khoản. MySQL chạy ở 127.0.0.1:3307 với dữ liệu trong .runtime/mysql-data. Cấu hình local và khóa ứng dụng nằm trong app/.env.local. Khi chạy Artisan trên máy này, thêm --env=local để dùng database local.

## Chức năng

- Đăng ký, đăng nhập, đăng xuất bằng session Laravel, mật khẩu băm, CSRF và giới hạn tần suất đăng nhập.
- Mỗi tài khoản có một hành trình thai kỳ, lấy ngày đầu kỳ kinh cuối làm mốc. Có thể điều chỉnh ngày mốc trong hồ sơ.
- Bữa ăn theo ngày và loại bữa, có món ăn, khẩu phần/cảm nhận dạng ghi chú, chỉnh sửa và xóa.
- Hồ sơ khám theo ngày, cơ sở khám, ghi chú kết quả/lời dặn, ngày hẹn tiếp theo.
- Mỗi hồ sơ khám có một tệp PDF/JPG/PNG tối đa 10 MB, có thể tải, thay thế hoặc gỡ.
- Bộ lọc theo ngày, tam cá nguyệt 1 (tuần 0–13), 2 (14–27), 3 (từ 28).
- Tệp lưu tại storage/app/private, tải qua route có xác thực và kiểm tra chủ sở hữu.

## Cài trên máy khác

Cần PHP >= 8.3 với curl, fileinfo, mbstring, openssl, pdo_mysql và zip; Composer; MySQL.

```sh
cd app
composer install
cp .env.example .env
php artisan key:generate
# Đặt DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD trong .env.
php artisan migrate
php artisan serve
```

PHP cần upload_max_filesize ít nhất 10M và post_max_size lớn hơn giới hạn đó. Phục vụ web từ thư mục app/public. Khi triển khai dùng HTTPS, APP_DEBUG=false và SESSION_SECURE_COOKIE=true.

Bootstrap được lưu sẵn trong public/assets. Font Google có font hệ thống dự phòng. Chưa cấu hình gửi email xác minh địa chỉ hoặc khôi phục mật khẩu. Ứng dụng đang dùng đăng nhập email/mật khẩu.

## Hostinger đồng bộ Git vào public_html

Giữ toàn bộ cấu trúc repository khi đồng bộ nhánh main vào public_html. Tệp .htaccess ở gốc chuyển request tới app/public; tệp app/.htaccess chặn truy cập trực tiếp vào mã nguồn, cấu hình và hồ sơ riêng. Website vẫn có thể dùng document root app/public nếu hosting hỗ trợ cấu hình đó.

Theo lựa chọn của chủ dự án, repository hiện chứa app/vendor production và app/.env của Hostinger. Cấu hình app/.env.local dành cho máy phát triển được loại khỏi Git. Những người có quyền đọc repository và lịch sử Git có thể đọc thông tin kết nối và khóa ứng dụng trong app/.env.

Composer đã đặt platform.php=8.3.0 để tạo lock file tương thích PHP 8.3 trên hosting, kể cả khi máy phát triển dùng PHP 8.4. Sau khi đồng bộ composer.lock mới, cần chạy lại composer install trên hosting để cập nhật vendor.

Thiết lập lần đầu trong hPanel:

1. Chọn PHP từ 8.3 trở lên, bật pdo_mysql, mbstring, fileinfo, openssl, curl, zip.
2. Tạo MySQL database/user. Tạo app/.env trên hosting từ app/.env.example và điền thông tin MySQL của hosting.
3. Đặt APP_ENV=production, APP_DEBUG=false, APP_URL=https://techai.sbs và SESSION_SECURE_COOKIE=true.
4. Trong terminal hosting, đi đến thư mục repository rồi chạy `bash deploy-hostinger.sh`. Lệnh cài thư viện production, chỉ tạo APP_KEY khi chưa có, chạy migration và làm mới cache.
5. Cấu hình bước này trong quy trình deploy sau mỗi lần đồng bộ Git nếu hosting có hỗ trợ hook. Đồng bộ mã nguồn qua Git chưa thay thế bước cài thư viện và cập nhật database.

Nếu / trả 403 nhưng /app/public/assets/mam.css trả 200, cần kiểm tra .htaccess ở gốc đã được đồng bộ. Nếu /login trả 500 sau đó, xem PHP error log và app/storage/logs/laravel.log; kiểm tra vendor, .env, APP_KEY, MySQL và quyền ghi storage/bootstrap/cache.

## Kiểm thử ứng dụng

```sh
php artisan test
```

Mặc định bộ kiểm thử dùng SQLite in-memory để tách biệt dữ liệu. Đã chạy cùng bộ kiểm thử với MySQL trên database mam_testing. Các bài kiểm thử kiểm tra auth, phân quyền tệp, CRUD, bộ lọc giai đoạn và tệp tải lên giả dạng PDF.

Thư mục .runtime, app/.env.local, hồ sơ tải lên và bản sao database được loại khỏi Git. Tài khoản demo được tạo riêng bằng DemoSeeder ở môi trường local và được đánh dấu dữ liệu minh họa.
