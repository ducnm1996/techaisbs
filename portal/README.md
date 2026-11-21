# TechAI Portal

Ứng dụng Laravel trong thư mục này. Hướng dẫn đầy đủ ở `../docs/DEPLOYMENT.md`; API Android ở `../docs/ANDROID-API.md`.

Chạy kiểm thử với một MySQL database **riêng, có thể xóa** bằng các biến DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, sau đó `php vendor/phpunit/phpunit/phpunit`. Bộ test MySQL dùng migrate:fresh và kiểm tra đồng thời bằng hai tiến trình PHP, tuyệt đối không dùng database hosting/thật.

Để thử nhanh bằng SQLite: `php vendor/phpunit/phpunit/phpunit`; hai test khóa đồng thời sẽ được bỏ qua, không thay thế kiểm thử MySQL. PHPUnit sử dụng khóa giả chỉ dành cho test, không sử dụng lại các khóa này ở bất kỳ môi trường có dữ liệu thật nào.

Không cần npm/Vite để chạy portal; CSS và JavaScript phục vụ từ public/assets. Nếu chạy local, cấu hình .env riêng, cài Composer dependencies và migrate trước; `php artisan serve --host=127.0.0.1` dùng HTTP local nên cần SESSION_SECURE_COOKIE=false. Không đưa thiết lập local này lên hosting.
