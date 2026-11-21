# Hợp đồng Android (thiết bị do chủ tài khoản cho phép)

Không có chức năng thu thập ẩn. Android phải giải thích từng quyền, hiển thị trạng thái đồng bộ, cho phép dừng, xóa token và thu hồi liên kết. Không tìm IP/vị trí của người liên hệ từ Zalo/Viber, không giải mã hoặc can thiệp TLS. Máy chủ nhận metadata đã lọc; không cung cấp bộ thu thập nội dung.

## Ghép thiết bị

1. Người dùng đăng nhập portal, chọn nguồn trong `sms`, `call`, `zalo`, `viber`, `email`; vị trí và IP thiết bị mặc định tắt. Tạo mã sử dụng một lần, hết hạn sau 5 phút.
2. `POST /api/v1/pair` JSON: `{"code":"mã từ portal","device_name":"Pixel của tôi"}`. Nhận `token`, `expires_at`, `device_id`, `sources`, `allow_ip`, `allow_location`. App phải dùng giao của nguồn được portal cấp và nguồn người dùng chủ động bật. Chỉ nhận token lần này. Mỗi thiết bị có một token `events:write`, mặc định 30 ngày.
3. Dùng `Authorization: Bearer <token>` cho `POST /api/v1/events`. Không truyền user_id/device_id; backend lấy chủ sở hữu từ token.
4. Khi 401/403, dừng gửi và yêu cầu ghép lại. Khi 429, dùng exponential backoff + jitter. Giữ nguyên event_id khi thử lại.

```json
{"events":[{"event_id":"019c1122-1234-7123-8123-123456789abc","source":"sms","occurred_at":"2026-11-21T09:15:00+07:00","sender_phone":"+84901234567","sender_name":"Tên hiển thị","server_domain":null}]}
```

Lô tối đa 50 sự kiện, JSON tối đa 64 KiB. event_id là UUID. occurred_at phải có múi giờ, không ở tương lai quá 5 phút, nằm trong thời hạn lưu cấu hình. Các trường tùy chọn: sender_phone (E.164), sender_name (120 ký tự), server_domain (hostname, 253 ký tự), latitude/longitude/accuracy_m, app_package (tên gói Android, tối đa 160 ký tự), call_direction (incoming/outgoing/missed/rejected/blocked/unknown) và duration_seconds (số nguyên 0–604800, chỉ cho source=call). Ba trường metadata Android mới cũng được mã hóa khi lưu. Vị trí chỉ được gửi nếu đã bật trên portal và cấp quyền Android; không gửi nội dung, subject, body, snippet, raw, token hoặc trường khác. IP nếu được bật là IP kết nối mà server quan sát, có thể là NAT/VPN, không phải IP người liên hệ. server_domain là hostname ứng dụng khai báo, không phải server đã được backend xác minh.

Sự kiện gửi lại cùng thiết bị + event_id được bỏ qua, không ghi đè bản cũ. Một lô không hợp lệ bị từ chối toàn bộ. Không tự giả định metadata không có trong API Android. Zalo/Viber thường chỉ có thông tin từ notification do người dùng cấp quyền; email cần tích hợp được ủy quyền và không yêu cầu mật khẩu email gửi về portal. Quyền SMS/cuộc gọi phụ thuộc Android và chính sách phân phối; không lách giới hạn hệ thống.

## Bảo vệ token trên Android

Tạo khóa AES/GCM/NoPadding không xuất được trong Android Keystore (`KeyGenParameterSpec`, PURPOSE_ENCRYPT | PURPOSE_DECRYPT, randomizedEncryptionRequired=true). Mã hóa token bằng IV mới cho mỗi lần ghi, lưu IV+ciphertext trong vùng riêng của ứng dụng; chỉ giải mã trong bộ nhớ khi gửi HTTPS. Không lưu token plaintext trong SharedPreferences, SQLite, logcat, crash report, clipboard hoặc backup. Loại trừ tệp token khỏi Auto Backup/data extraction rules; xử lý mất/đổi khóa bằng cách xóa ciphertext và ghép lại. Không ghim khóa mã hóa trong APK. Máy đã root/đang mở khóa không được xem là ranh giới bảo vệ tuyệt đối.

App Android hoàn chỉnh nằm trong `android/`; xem [hướng dẫn cài thử và build](ANDROID-APP.md). Thư mục `android-integration/` chỉ giữ mẫu tích hợp cũ để tham khảo. App hiện không gửi vị trí hoặc server_domain; không suy đoán các trường không có trong API Android.
