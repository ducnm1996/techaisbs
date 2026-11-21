# App Android TechAI Metadata

Mã nguồn: `android/` (Java, minSdk 26, target/compileSdk 36). Kết nối cố định HTTPS đến techai.sbs. Không có đăng nhập web bên trong app; token thiết bị chỉ có quyền gửi sự kiện.

## Cài thử trên Samsung A34

1. Chuyển APK có chữ ký sang điện thoại, mở và cho phép cài từ nguồn bạn đang dùng khi Android hỏi. Đây là APK cài thử, chưa phát hành qua Play Store.
2. Đăng nhập portal → Thiết bị → chọn nguồn và tạo mã mới. Nhập tên máy và mã trong app trong vòng 5 phút. Mã chỉ dùng một lần; không gửi mã/token cho người khác.
3. Chọn từng nguồn trong app, đọc thông tin thu thập rồi cấp quyền cần thiết. Mọi nguồn mặc định tắt, quyền trên app không vượt quá quyền mã ghép.
4. Bật truy cập thông báo trong Cài đặt Android cho Zalo, Viber, email và nguồn thông báo dự phòng. Android có thể yêu cầu xác nhận “Cho phép cài đặt bị hạn chế” đối với app cài thủ công; chỉ thực hiện nếu bạn tin cậy bản APK này, không tắt các bảo vệ khác.
5. Xác nhận đồng ý → Bật đồng bộ. Cho phép thông báo trạng thái riêng của app. Tạo một thông báo/cuộc gọi/SMS mới rồi bấm Đồng bộ ngay; xem Lịch sử trên portal. Sự kiện trước thời điểm bật không được nhập lại.
6. Dừng đồng bộ hoặc xóa hàng đợi bất cứ lúc nào. Khi đổi tài khoản/ngắt liên kết, token và hàng đợi trên máy bị xóa. Thu hồi thiết bị trên portal để vô hiệu token trên máy chủ; gỡ app không tự thu hồi token.

Không có thiết bị A34 nối USB tại thời điểm build; cần kiểm tra quyền và chế độ tiết kiệm pin trên máy thật. Nếu One UI hạn chế chạy nền, lịch gửi có thể chậm. WorkManager gửi khi có mạng, lịch định kỳ tối thiểu 15 phút; không đảm bảo tức thời. Yêu cầu đang gửi có thể hoàn tất sau khi bấm dừng.

## Dữ liệu thực sự thu nhận

| Nguồn | Dữ liệu | Giới hạn |
|---|---|---|
| Zalo, Viber | Gói ứng dụng, thời gian thông báo | Không đọc người gửi, nội dung, extras; một thông báo không tương đương một tin nhắn |
| Email | Gói Gmail, Outlook, Samsung/Android Email và thời gian | Không truy cập hộp thư, địa chỉ email, tiêu đề, nội dung hay máy chủ email |
| SMS | Thời gian, số gửi E.164 nếu chuẩn hóa được | Chỉ SMS mới nhận với RECEIVE_SMS; không đọc body/subject. Nếu không được cấp quyền, dùng metadata thông báo SMS |
| Cuộc gọi | Thời gian, số E.164 nếu có, chiều gọi, thời lượng | Chỉ bản ghi mới với READ_CALL_LOG; không ghi âm. Nếu không được cấp quyền, dùng metadata thông báo điện thoại, không có chiều/thời lượng |

Quyền RECEIVE_SMS/READ_CALL_LOG có thể bị Android hạn chế cứng, phụ thuộc bộ cài và hệ điều hành; app không vượt qua hạn chế, không tự nhận vai trò SMS/điện thoại mặc định. Số ẩn hoặc không chuẩn hóa được bị bỏ qua. Không yêu cầu GPS, danh bạ, Accessibility, quyền đọc màn hình hay VPN. Không suy đoán vị trí/IP của người liên hệ. IP quan sát trên portal chỉ là IP kết nối của điện thoại nếu chủ tài khoản đã cho phép; có thể là NAT/VPN.

Android cấp khả năng đọc thông báo rộng hơn dữ liệu app cần. Implementation chỉ dùng packageName, postTime, key để tạo ID, và flags để bỏ thông báo tổng nhóm; không truy cập Notification.extras/title/text/MessagingStyle. Các lần cập nhật thông báo có thể tạo nhiều sự kiện. Đây là lịch sử sự kiện metadata, không phải bản sao toàn bộ hoạt động trong từng ứng dụng.

## Bảo vệ và giới hạn

Token và payload trong SQLite được mã hóa AES-GCM bằng khóa không xuất được trong Android Keystore. Không ghi token, số, mã ghép hoặc payload vào log. Backup và cleartext HTTP bị tắt; xác thực TLS dùng CA hệ thống. Hàng đợi giữ tối đa 1.000 sự kiện và 7 ngày; sự kiện quá hạn/quá số lượng bị loại. Mất khóa hoặc token hết hạn cần xóa hàng đợi/ngắt liên kết và ghép lại. Nguồn bỏ chọn bị xóa khỏi hàng đợi. Không gửi dữ liệu trước lúc bật hoặc trong lúc dừng.

Lô tối đa 40 sự kiện, giữ event_id khi gửi lại. 429/5xx dùng backoff; 401/403 dừng, xóa token; lỗi hợp đồng 422 dừng để người dùng kiểm tra giờ máy/nguồn và xử lý hàng đợi. Hàng đợi chưa gửi không có giao diện xem nội dung trên máy. Máy đã root hoặc bị kiểm soát không có bảo đảm bảo mật tuyệt đối.

## Build

Dùng JDK 17, Android SDK Platform 36, Build Tools 35/36, Gradle wrapper 8.13 và AGP 8.13.0. Tạo `android/local.properties` với sdk.dir của máy (không commit). Từ thư mục android:

```sh
./gradlew assembleRelease testDebugUnitTest lintRelease
```

APK release chưa ký nằm trong app/build/outputs/apk/release/. Dùng zipalign/apksigner của SDK để ký với khóa riêng nằm ngoài Git; không bật debuggable. Bản cài thử được xuất vào exports/TechAI-Metadata-0.1.0.apk (không commit APK/keystore). Giữ nguyên khóa ký để cài bản cập nhật; mất khóa phải gỡ app và ghép lại. Khóa ký APK khác khóa Android Keystore trên điện thoại. Không có secret production trong source.

Tài liệu nền tảng: [NotificationListenerService](https://developer.android.com/reference/android/service/notification/NotificationListenerService), [quyền Android](https://developer.android.com/reference/android/Manifest.permission), [WorkManager](https://developer.android.com/develop/background-work/background-tasks/persistent-work).
