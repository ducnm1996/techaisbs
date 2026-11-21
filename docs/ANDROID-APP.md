# App Android TechAI Metadata

Mã nguồn: `android/` (Java, minSdk 29, target/compileSdk 36). Kết nối cố định HTTPS đến techai.sbs. Không có đăng nhập web bên trong app; token thiết bị chỉ có quyền gửi sự kiện.

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

Quyền RECEIVE_SMS/READ_CALL_LOG có thể bị Android hạn chế cứng, phụ thuộc bộ cài và hệ điều hành; app không vượt qua hạn chế, không tự nhận vai trò SMS/điện thoại mặc định. Số ẩn hoặc không chuẩn hóa được bị bỏ qua. GPS chỉ hoạt động trong phiên chia sẻ vị trí được bật riêng (xem phần Agent bên dưới). Không yêu cầu danh bạ, Accessibility, quyền đọc màn hình hay VPN. Không suy đoán vị trí/IP của người liên hệ. IP quan sát trên portal chỉ là IP kết nối của điện thoại nếu chủ tài khoản đã cho phép; có thể là NAT/VPN.

Android cấp khả năng đọc thông báo rộng hơn dữ liệu app cần. Implementation chỉ dùng packageName, postTime, key để tạo ID, và flags để bỏ thông báo tổng nhóm; không truy cập Notification.extras/title/text/MessagingStyle. Các lần cập nhật thông báo có thể tạo nhiều sự kiện. Đây là lịch sử sự kiện metadata, không phải bản sao toàn bộ hoạt động trong từng ứng dụng.

## Bảo vệ và giới hạn

Token và payload trong SQLite được mã hóa AES-GCM bằng khóa không xuất được trong Android Keystore. Không ghi token, số, mã ghép hoặc payload vào log. Backup và cleartext HTTP bị tắt; xác thực TLS dùng CA hệ thống. Hàng đợi giữ tối đa 1.000 sự kiện và 7 ngày; sự kiện quá hạn/quá số lượng bị loại. Mất khóa hoặc token hết hạn cần xóa hàng đợi/ngắt liên kết và ghép lại. Nguồn bỏ chọn bị xóa khỏi hàng đợi. Không gửi dữ liệu trước lúc bật hoặc trong lúc dừng.

Lô tối đa 40 sự kiện, giữ event_id khi gửi lại. 429/5xx dùng backoff; 401/403 dừng, xóa token; lỗi hợp đồng 422 dừng để người dùng kiểm tra giờ máy/nguồn và xử lý hàng đợi. Hàng đợi chưa gửi không có giao diện xem nội dung trên máy. Máy đã root hoặc bị kiểm soát không có bảo đảm bảo mật tuyệt đối.

## Build

Dùng JDK 17, Android SDK Platform 36, Build Tools 35/36, Gradle wrapper 8.13 và AGP 8.13.0. Tạo `android/local.properties` với sdk.dir của máy (không commit). Từ thư mục android:

```sh
./gradlew assembleRelease testDebugUnitTest lintRelease
```

APK release chưa ký nằm trong app/build/outputs/apk/release/. Dùng zipalign/apksigner của SDK để ký với khóa riêng nằm ngoài Git; không bật debuggable. Bản cài thử được xuất vào exports/TechAI-Agent-0.3.0.apk (không commit APK/keystore). Giữ nguyên khóa ký để cài bản cập nhật; mất khóa phải gỡ app và ghép lại. Khóa ký APK khác khóa Android Keystore trên điện thoại. Không có secret production trong source.

Tài liệu nền tảng: [NotificationListenerService](https://developer.android.com/reference/android/service/notification/NotificationListenerService), [quyền Android](https://developer.android.com/reference/android/Manifest.permission), [WorkManager](https://developer.android.com/develop/background-work/background-tasks/persistent-work).


## Agent 0.2.0

Trong portal, tạo mã mới có nguồn **THÔNG TIN THIẾT BỊ / AGENT** (`device`). Chỉ đánh dấu Lưu IP / Cho phép vị trí khi cần. Bản cũ không được tự mở rộng quyền: nếu mã ghép trước không có nguồn device, thu hồi thiết bị cũ và ghép lại. APK 0.2.0 dùng cùng applicationId và chứng thư với 0.1.0 để cập nhật tại chỗ; nguồn mới vẫn mặc định tắt.

Agent lấy hãng/model, Android/SDK/bản vá, phiên bản app, pin/sạc/tiết kiệm pin, RAM tổng và khả dụng, dung lượng filesystem tổng và khả dụng, loại mạng (Wi-Fi/di động/VPN/Ethernet), mạng tính phí, trạng thái Internet do Android xác nhận, múi giờ, ngôn ngữ và uptime. Không lấy IMEI, serial, MAC, SSID, danh sách ứng dụng, file, clipboard, camera, micro, nội dung màn hình, nội dung thông báo/tin nhắn, mật khẩu hay OTP. IP chỉ là IP kết nối máy chủ quan sát nếu được chủ tài khoản cấp. Agent không tìm máy chủ/IP của người liên hệ.

Chọn nguồn Agent trong app → đồng ý → Bật đồng bộ. Mẫu mới được lấy khi Worker chạy với kết nối mạng, khoảng tối thiểu 15 phút giữa các mẫu; bấm Đồng bộ ngay để yêu cầu mẫu mới. WorkManager chịu giới hạn pin/mạng của Android, không hứa thời gian thực hoặc chạy liên tục 24/7. Không có cơ chế điều khiển từ xa, tải/chạy lệnh từ portal, tự cấp quyền hay tự tắt bảo vệ hệ điều hành. Portal hiển thị mẫu cuối, pin, mạng và thời điểm lấy/nhận; “có cập nhật gần đây” không chứng minh máy đang online.

### Chia sẻ vị trí tùy chọn

Sau khi bật Agent và portal đã cho phép vị trí, bấm Bật chia sẻ vị trí thiết bị. Android cho chọn vị trí gần đúng hoặc chính xác; app chấp nhận cả hai. Sau khi cấp quyền, bấm nút lại và xác nhận lời giải thích riêng. App khởi động foreground service có thông báo thường trực và nút Dừng vị trí; yêu cầu mẫu vị trí khoảng mỗi phút, gửi kèm mẫu device mỗi khoảng 15 phút hoặc theo yêu cầu đồng bộ. Mẫu vị trí phải được lấy sau khi đồng ý và còn mới trong 5 phút tại thời điểm tạo sự kiện. Không dùng last-known location, không yêu cầu ACCESS_BACKGROUND_LOCATION, không tự bật vị trí sau reboot/kill.

Bấm Dừng vị trí, Dừng đồng bộ, bỏ chọn Agent hoặc ngắt liên kết để kết thúc phiên. Dừng vị trí xóa tọa độ trong RAM và toàn bộ mẫu device chưa gửi; dữ liệu đã nhận trên portal vẫn giữ theo retention và có thể xóa bằng tài khoản sở hữu. Trước khi gửi, app bỏ tọa độ khỏi lô nếu phiên đã tắt, quyền bị thu hồi hoặc dịch vụ không còn chạy. Yêu cầu đã truyền đi có thể hoàn tất sau khi bấm dừng. Chia sẻ khi màn hình tắt có thể tốn pin; nếu hệ thống dừng service, người dùng phải mở app để bật lại.

### Cảnh báo cài thử

Bản 0.1.0 đã bị Play Protect chặn trên máy người dùng. Bản agent vẫn có RECEIVE_SMS, READ_CALL_LOG và NotificationListener nên không khẳng định việc nâng phiên bản sẽ hết chặn. Cần kiểm tra chính sách/quyền tối thiểu và dùng quy trình xem xét của Google khi bị phân loại nhầm; không có mã lách bảo vệ hay xin quyền ngầm trong app. Việc build thành công không chứng minh APK được Play Protect chấp nhận. Chưa có A34 hoặc emulator kết nối để xác nhận chạy thực tế, GPS, Keystore hay chạy nền.

Nền tảng: [foreground service vị trí](https://developer.android.com/develop/background-work/services/fgs/service-types), [hạn chế khởi động service](https://developer.android.com/develop/background-work/services/fgs/restrictions-bg-start), [Play Protect cho nhà phát triển](https://developers.google.com/android/play-protect/warning-dev-guidance).

## VPN cục bộ 0.3.0: IP đích Zalo/Viber/email

Bản 0.3.0 yêu cầu Android 10+ (minSdk 29), phù hợp Samsung A34. Cùng applicationId/chứng thư với bản trước; có thể cập nhật tại chỗ nếu Android cho phép cài. APK: exports/TechAI-Agent-0.3.0.apk. Đây là build cài thử, không tuyên bố Play Protect đã chấp nhận.

### Bật/tắt và quyền

1. Thu hồi liên kết cũ trên portal nếu cần, tạo mã mới có nguồn **VPN / IP ĐÍCH ZALO, VIBER, EMAIL** (`network`) và chọn thêm từng nguồn Zalo, Viber, email muốn quan sát. Quyền IP kết nối điện thoại là lựa chọn riêng; không nhầm với quyền network/IP đích.
2. Ghép app bằng mã mới. Chọn VPN và từng nguồn tương ứng, xác nhận đồng ý rồi Bật đồng bộ.
3. Bấm **Bật VPN cục bộ lấy IP đích**, đọc xác nhận riêng về phạm vi app và ảnh hưởng mạng, sau đó chấp thuận hộp thoại VPN chính thức của Android. Android chỉ cho một VPN trên mỗi hồ sơ; phiên này có thể thay thế VPN đang dùng.
4. Mở Zalo/Viber/email để tạo kết nối mới, rồi xem Lịch sử → nguồn VPN / IP đích. IP mới không được bổ sung ngược cho SMS, cuộc gọi hay thông báo cũ.
5. Bấm **Tắt VPN** trong app hoặc thông báo thường trực. Việc tắt ngừng quan sát mới; metadata đã ghi vẫn được đồng bộ nếu nguồn còn được chọn. Bỏ chọn VPN/nguồn tương ứng sẽ xóa các sự kiện đang chờ của nguồn đó (các nguồn phụ của network cũng được lọc lại khi gửi). Có nút xóa toàn bộ hàng đợi. Dữ liệu đã lên portal có thể xóa riêng và theo retention.

VPN không bật tự động sau reboot/kill, không hỗ trợ always-on. Đổi lựa chọn Zalo/Viber/email khi VPN đang chạy sẽ dừng phiên để người dùng chủ động bật lại với danh sách mới. Dừng đồng bộ, token hết hạn/401/403, mất thông báo trạng thái, thu hồi quyền VPN hoặc vuốt tắt task sẽ dừng phiên. Các lỗi native/chuyển tiếp được watchdog phát hiện và đóng TUN để khôi phục định tuyến thông thường. Không có cơ chế cấp quyền ngầm hoặc tắt Play Protect.

### Metadata và giới hạn diễn giải

Chỉ gửi `source=network`, app_package, app_source, destination_ip (IPv4/IPv6 literal), destination_port (1–65535), protocol (tcp/udp), thời gian và event_id. Backend bắt buộc cả quyền network lẫn app_source tương ứng và tên gói trong whitelist. IP, cổng, giao thức được lưu dưới network_metadata mã hóa. Không có URL, DNS query, SNI, hostname suy đoán, packet payload, chứng thư, mật khẩu, nội dung ứng dụng, số điện thoại đối tác hoặc tài khoản email trong event VPN.

Header parser chỉ đọc IP/TCP/UDP. Gói tin được chuyển tiếp trong RAM qua engine Hev/lwIP đến SOCKS5 server trên 127.0.0.1 có mật khẩu ngẫu nhiên theo phiên; socket ra Internet được VpnService.protect để tránh vòng lặp. Không có máy chủ VPN bên ngoài, không cài CA, không MITM hoặc giải mã TLS. Payload vẫn phải đi qua bộ chuyển tiếp trong RAM để mạng hoạt động, nhưng không được phân tích ở cấp ứng dụng, lưu file/log hay tải lên portal. Native log đi /dev/null, các buffer chuyển tiếp được xóa khi kết thúc.

Chỉ whitelist com.zing.zalo, com.viber.voip, Gmail, Outlook, Samsung/Android Email. Không yêu cầu QUERY_ALL_PACKAGES. App chưa cài/bị ẩn hoặc có UID dùng chung với gói khác sẽ không được thêm vào VPN. getConnectionOwnerUid xác định ứng dụng sở hữu tuple; nếu không xác định được thì không ghi, không đoán. TCP chỉ quan sát SYN mở kết nối; UDP dùng cache tuple tối đa một phút. Không bảo đảm thấy mọi kết nối, đặc biệt shared/isolated UID, kết nối trước khi bật, hoặc gói phân mảnh/extension không hỗ trợ. Các gói không phân tích được vẫn được chuyển tiếp qua engine; không suy đoán metadata thiếu.

Một endpoint (app/IP/cổng/giao thức) được gộp mỗi phút, tối đa 120 endpoint mới/phút, cache giới hạn 2.048, hàng đợi xử lý header 128 và hàng đợi sự kiện chung 1.000/7 ngày. VPN tối đa 250 sự kiện đang chờ và bị loại trước các nguồn khác khi hàng đợi đầy, để hạn chế lấn chỗ SMS/cuộc gọi. Khi quá tải, metadata được bỏ thay vì chặn việc chuyển tiếp. Proxy/native giới hạn 64 session, timeout TCP nhàn rỗi 5 phút và UDP 2 phút; có thể gây ngắt/kết nối lại, cần thử thực tế trước khi dùng lâu dài. DNS dùng các DNS server của mạng hiện tại, không tự chuyển sang DNS bên thứ ba. Không đọc DNS query. Có thể cần tắt/bật lại VPN khi đổi mạng hoặc DNS.

IP đích có thể là CDN, máy chủ trung gian, telemetry hoặc peer của ứng dụng. Không chứng minh một người cụ thể dùng IP đó, máy chủ đã nhận gói hay IP thuộc một tin nhắn. Không xác định IP người nhận bằng cách ghép với số điện thoại. VPN làm thay đổi đường đi qua một bộ chuyển tiếp local; dữ liệu đo phản ánh phiên này, không phải bản ghi nguyên trạng của hệ điều hành.

### Kiểm thử còn chờ trên A34

Chưa có thiết bị/emulator trong adb devices tại lúc build. Cần kiểm tra: cấp/từ chối/thu hồi VPN; bật/tắt khôi phục Internet; Zalo/Viber/email giữ kết nối TCP/UDP/IPv6; attribution app; DNS/Private DNS/QUIC; chuyển Wi-Fi/di động; tiết kiệm pin, kill/reboot; xóa nguồn/token; không có payload trong queue/log; chạy chung với cài đặt VPN khác. Pure Java relay tests và backend tests không thay thế các bước này. Không gọi bản cài thử là VPN production hoặc đã xác nhận hoạt động trên A34.

Nguồn: [Android VPN](https://developer.android.com/develop/connectivity/vpn), [getConnectionOwnerUid](https://developer.android.com/reference/android/net/ConnectivityManager#getConnectionOwnerUid(int,%20java.net.InetSocketAddress,%20java.net.InetSocketAddress)), [dependency pin và license](../android/third-party/README.md).

## Phiên ghép và xử lý phản hồi muộn

App 0.3 tạo marker phiên mới mỗi lần ghép. Ghép/ngắt liên kết, đọc token và lấy lô cùng dùng khóa hàng đợi; producer ghi với marker đã chụp và bị từ chối nếu marker đổi. Worker cũ không lấy lô mới, không acknowledge dữ liệu mới hoặc xóa token mới khi phản hồi cũ về muộn. Marker là UUID local, không phải token, không gửi lên portal. Chưa có kiểm thử instrumentation chạy race ghép lại trên máy thật; cần thử trong danh sách bàn giao Android. Dừng đồng bộ giữ dữ liệu đang chờ, không tự xóa do batch đọc đồng thời.
