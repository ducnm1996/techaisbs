package sbs.techai.metadata;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.ComponentName;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.service.notification.NotificationListenerService;
import android.text.InputType;
import android.view.View;
import android.view.WindowInsets;
import android.widget.*;
import java.text.DateFormat;
import java.util.Date;
import java.util.HashMap;
import java.util.Map;
import org.json.JSONObject;

public final class MainActivity extends Activity {
 private LinearLayout root; private TextView status; private EditText code,name; private CheckBox consent;
 private final Map<String,CheckBox> sources=new HashMap<>(); private boolean busy=false; private boolean screenPaired; private boolean vpnApprovalPending;
 private final Handler handler=new Handler(Looper.getMainLooper());
 private final Runnable ticker=new Runnable(){public void run(){refresh();handler.postDelayed(this,2500);}};
 private int dp(int value){return (int)(value*getResources().getDisplayMetrics().density);}
 @Override public void onCreate(Bundle state){super.onCreate(state);render();}
 @Override public void onResume(){super.onResume();handler.post(ticker);StatusNotice.update(this);}
 @Override public void onPause(){handler.removeCallbacks(ticker);super.onPause();}
 private TextView text(String value,int size){TextView v=new TextView(this);v.setText(value);v.setTextSize(size);v.setTextColor(Color.rgb(24,61,53));v.setPadding(0,dp(8),0,dp(8));root.addView(v);return v;}
 private void button(String value,View.OnClickListener action){Button v=new Button(this);v.setText(value);v.setAllCaps(false);v.setOnClickListener(action);root.addView(v,new LinearLayout.LayoutParams(-1,-2));}
 private EditText input(String hint){EditText v=new EditText(this);v.setHint(hint);v.setSingleLine(true);v.setSaveEnabled(false);root.addView(v,new LinearLayout.LayoutParams(-1,-2));return v;}
 private void render(){
  screenPaired=new SecureStore(this).paired();
  ScrollView scroll=new ScrollView(this);scroll.setFillViewport(true);scroll.setBackgroundColor(Color.rgb(244,247,240));
  root=new LinearLayout(this);root.setOrientation(LinearLayout.VERTICAL);root.setPadding(dp(24),dp(24),dp(24),dp(32));scroll.addView(root);setContentView(scroll);
  if(Build.VERSION.SDK_INT>=30)scroll.setOnApplyWindowInsetsListener((v,insets)->{android.graphics.Insets bars=insets.getInsets(WindowInsets.Type.systemBars());v.setPadding(bars.left,bars.top,bars.right,bars.bottom);return insets;});
  // Android 8-10 use legacy inset values; the modern API is called only on Android 11+.
  if(Build.VERSION.SDK_INT<30){scroll.setOnApplyWindowInsetsListener((v,insets)->{v.setPadding(insets.getSystemWindowInsetLeft(),insets.getSystemWindowInsetTop(),insets.getSystemWindowInsetRight(),insets.getSystemWindowInsetBottom());return insets;});}
  text("TechAI · Agent",28);text("Thiết bị của bạn, quyền do bạn chọn",18);
  text("Chỉ sử dụng trên thiết bị của bạn hoặc có sự đồng ý của người sử dụng. App gửi metadata đến https://techai.sbs khi bạn chủ động bật đồng bộ. Không thu nội dung tin nhắn, tiêu đề email, ảnh, âm thanh hay danh bạ. Thông tin thiết bị và chia sẻ vị trí là các lựa chọn riêng, mặc định tắt.",15);
  text("Zalo / Viber / email: tên gói ứng dụng và thời điểm thông báo mới. SMS / cuộc gọi: có thể thêm số điện thoại; cuộc gọi có chiều gọi và thời lượng nếu Android cấp quyền. Portal có thể ghi IP kết nối nếu bạn đã cho phép khi tạo mã. Không xác định máy chủ/IP của người gửi.",15);
  status=text("",15);
  if(!screenPaired){
   text("1. Ghép với portal",22);text("Đăng nhập techai.sbs → Thiết bị → tạo mã và chọn nguồn được phép. Mã chỉ dùng một lần, hết hạn sau 5 phút.",15);
   name=input("Tên thiết bị");name.setText(Build.MANUFACTURER+" "+Build.MODEL);name.setFilters(new android.text.InputFilter[]{new android.text.InputFilter.LengthFilter(120)});
   code=input("Mã ghép thiết bị");code.setInputType(InputType.TYPE_CLASS_TEXT|InputType.TYPE_TEXT_FLAG_NO_SUGGESTIONS);code.setFilters(new android.text.InputFilter[]{new android.text.InputFilter.LengthFilter(40)});
   button("Ghép thiết bị",v->pair());
  }else{
   text("2. Chọn nguồn",22);text("Chỉ các nguồn được mã ghép cho phép mới bật được. Bỏ chọn sẽ xóa metadata đang chờ của nguồn đó.",15);
   AppState state=new AppState(this);sources.clear();String[] labels={"Zalo · thông báo mới","Viber · thông báo mới","SMS · mới nhận","Cuộc gọi · sự kiện mới","Email · thông báo mới","Agent · pin, bộ nhớ, Android và trạng thái mạng","VPN · IP đích Zalo / Viber / email"};
   for(int i=0;i<AppState.SOURCES.length;i++){
    String source=AppState.SOURCES[i];CheckBox box=new CheckBox(this);box.setText(labels[i]+(state.grants().contains(source)?"":" (portal chưa cấp quyền)"));box.setChecked(state.selected(source));box.setEnabled(state.grants().contains(source));root.addView(box);sources.put(source,box);
    box.setOnCheckedChangeListener((b,selected)->{state.selected(source,selected);if("call".equals(source))state.resetCallCursor();if("network".equals(source)||"zalo".equals(source)||"viber".equals(source)||"email".equals(source)){if(NetworkVpnService.active()||state.vpnRequested())NetworkVpnService.stop(this);}if("device".equals(source)){state.lastTelemetry(0);if(!selected)LocationCaptureService.stop(this);}if(!selected)Capture.IO.execute(()->Capture.queue(this).clearSource(source));});
   }
   text("VPN cục bộ chỉ ghi IP đích, cổng, TCP/UDP, ứng dụng và thời gian. Không chứng minh IP của người nhận tin nhắn; có thể là CDN, máy chủ trung gian hoặc kết nối khác của app. Không đọc DNS/TLS hay lưu payload. Android chỉ cho một VPN mỗi hồ sơ; bật VPN này có thể thay thế VPN đang dùng. Chỉ app được chọn và cấp quyền mới đi qua VPN.",15);
   button("Bật VPN cục bộ lấy IP đích",v->startVpn());
   button("Tắt VPN (giữ metadata đã ghi)",v->{NetworkVpnService.stop(this);refresh();});
   text("3. Cấp quyền có chọn lọc",22);
   button("Cho phép truy cập thông báo",v->new AlertDialog.Builder(this).setTitle("Quyền thông báo của Android").setMessage("Android cấp khả năng truy cập thông báo khá rộng. App này chỉ đọc tên gói và thời gian của nguồn bạn chọn; không đọc extras, tiêu đề hay nội dung. Bạn có thể thu hồi quyền trong Cài đặt bất cứ lúc nào.").setNegativeButton("Hủy",null).setPositiveButton("Mở cài đặt",(d,w)->startActivity(new Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS))).show());
   button("Quyền SMS (tùy chọn)",v->requestMetadataPermission(Manifest.permission.RECEIVE_SMS,21,"Chỉ lấy số gửi và thời gian SMS mới nhận. Android có thể từ chối quyền này. Khi đó app chỉ ghi metadata thông báo SMS nếu được phép."));
   button("Quyền cuộc gọi (tùy chọn)",v->requestMetadataPermission(Manifest.permission.READ_CALL_LOG,22,"Chỉ lấy số, thời gian, chiều và thời lượng các cuộc gọi mới sau khi bật. Không ghi âm. Android có thể không cấp quyền; app không vượt qua hạn chế này."));
   text("Thông tin agent gồm hãng/model, Android/bản vá, pin/sạc/tiết kiệm pin, RAM, dung lượng lưu trữ, loại mạng/tính phí, Internet, múi giờ/ngôn ngữ và thời gian từ lúc khởi động. Không lấy IMEI, serial, MAC, tên Wi-Fi, file hay danh sách app.",15);
   button("Bật chia sẻ vị trí thiết bị",v->startLocation());
   button("Dừng vị trí và xóa mẫu thiết bị đang chờ",v->{LocationCaptureService.stop(this);state.status("Đã dừng chia sẻ vị trí; mẫu thiết bị chưa gửi được xóa. Dữ liệu trên portal vẫn giữ theo thời hạn lưu.");refresh();});
   consent=new CheckBox(this);consent.setText("Tôi đã đọc thông tin trên và đồng ý đồng bộ các nguồn đã chọn đến tài khoản của mình.");root.addView(consent);
   button("Bật đồng bộ",v->startSync());button("Đồng bộ ngay",v->{if(new AppState(this).enabled()){new AppState(this).lastTelemetry(0);SyncControl.sync(this);toast("Đã yêu cầu đồng bộ khi có mạng.");}else toast("Hãy bật đồng bộ trước.");});
   button("Dừng đồng bộ",v->{SyncControl.pause(this);state.status("Bạn đã dừng đồng bộ. Metadata đã chờ vẫn được giữ mã hóa.");refresh();});
   button("Xóa hàng đợi trên máy",v->new AlertDialog.Builder(this).setMessage("Xóa metadata chưa gửi trên máy? Dữ liệu đã lên portal không bị xóa.").setNegativeButton("Hủy",null).setPositiveButton("Xóa",(d,w)->Capture.IO.execute(()->{Capture.queue(this).clear();runOnUiThread(this::refresh);})).show());
   button("Ngắt liên kết thiết bị",v->new AlertDialog.Builder(this).setMessage("Dừng đồng bộ, xóa token và hàng đợi trên máy. Bạn cũng nên thu hồi thiết bị trên portal; gỡ app không tự thu hồi token trên máy chủ.").setNegativeButton("Hủy",null).setPositiveButton("Ngắt liên kết",(d,w)->{SyncControl.pause(this);Capture.IO.execute(()->{synchronized(Capture.queue(this)){Capture.queue(this).clear();new SecureStore(this).clear();state.clearPair();}state.status("Đã ngắt liên kết trên máy.");runOnUiThread(this::render);});}).show());
  }
  text("Thông tin cài thử",20);text("Android 10+ · Samsung A34. Hàng đợi mã hóa tối đa 1.000 sự kiện / 7 ngày. Đồng bộ nền phụ thuộc Android và chế độ tiết kiệm pin; không đảm bảo tức thời. App không đọc lịch sử trước khi bật. Email chỉ hỗ trợ thông báo Gmail, Outlook và Samsung/Android Email.",14);refresh();
 }
 private void pair(){
  if(busy)return;String pairing=code.getText().toString().replaceAll("[\\s-]","").toUpperCase(java.util.Locale.ROOT);String deviceName=name.getText().toString().trim();
  if(!pairing.matches("[A-F0-9]{16}")||deviceName.isEmpty()){toast("Nhập tên thiết bị và mã ghép 16 ký tự từ portal.");return;}
  busy=true;code.setText("");toast("Đang ghép thiết bị…");
  Capture.IO.execute(()->{try{
   ApiClient.Reply reply=ApiClient.post("/api/v1/pair",new JSONObject().put("code",pairing).put("device_name",deviceName),null);
   if(reply.status!=201&&reply.status!=200)throw new IllegalStateException("HTTP "+reply.status);
   org.json.JSONArray grants=reply.json.getJSONArray("sources");String token=reply.json.getString("token"),expiry=reply.json.getString("expires_at");java.time.Instant.parse(expiry);
   synchronized(Capture.queue(this)){Capture.queue(this).clear();new SecureStore(this).token(token);new AppState(this).paired(grants,expiry);new AppState(this).locationAllowed(reply.json.optBoolean("allow_location",false));}new AppState(this).status("Đã ghép. Chọn nguồn và cấp quyền trước khi bật đồng bộ.");
   runOnUiThread(()->{busy=false;render();});
  }catch(Exception ignored){runOnUiThread(()->{busy=false;toast("Chưa ghép được. Kiểm tra mạng, mã còn hạn và tạo mã mới nếu đã sử dụng.");});}});
 }
 private void startVpn(){
  AppState state=new AppState(this);
  if(!state.accepts("network")){toast("Chọn nguồn VPN và bật đồng bộ trước. Mã ghép cần cấp quyền network.");return;}
  if(!state.accepts("zalo")&&!state.accepts("viber")&&!state.accepts("email")){toast("Chọn ít nhất một nguồn Zalo, Viber hoặc email đã được portal cấp quyền.");return;}
  if(!StatusNotice.permitted(this)){toast("Hãy cho phép thông báo trạng thái.");return;}
  if(NetworkVpnService.active()){toast("VPN đang bật. Muốn đổi nguồn, tắt rồi bật lại.");return;}
  new AlertDialog.Builder(this).setTitle("Bật VPN cục bộ để ghi IP đích?").setMessage("Chỉ các app được bạn chọn trong Zalo/Viber/email đi qua bộ chuyển tiếp trên máy. App gửi lên techai.sbs: IP đích, cổng, TCP/UDP, tên gói và thời gian; gộp một endpoint mỗi phút, tối đa 120 endpoint/phút. Byte dữ liệu chỉ được chuyển tiếp trong RAM, không lưu hoặc giải mã. Không cài chứng thư TLS, không có máy chủ VPN bên ngoài. Có thể gián đoạn kết nối và thay VPN đang bật. Tắt VPN ngừng quan sát; dữ liệu đã ghi vẫn chờ đồng bộ, có thể xóa hàng đợi. Bạn có đồng ý?").setNegativeButton("Hủy",null).setPositiveButton("Đồng ý và tiếp tục",(d,w)->{
   Intent approval=android.net.VpnService.prepare(this);
   if(approval!=null){vpnApprovalPending=true;startActivityForResult(approval,30);}else launchVpn();
  }).show();
 }
 private void launchVpn(){
  AppState state=new AppState(this);if(!state.accepts("network")||!StatusNotice.permitted(this))return;
  state.vpnRequested(true);state.vpnStatus("Đang khởi động VPN cục bộ…");
  try{startForegroundService(new Intent(this,NetworkVpnService.class).setAction("START"));}catch(IllegalStateException|SecurityException failure){state.vpnRequested(false);state.vpnStatus("Android chưa cho phép khởi động VPN.");}
  refresh();
 }
 @Override protected void onActivityResult(int request,int result,Intent data){super.onActivityResult(request,result,data);if(request==30&&vpnApprovalPending){vpnApprovalPending=false;if(result==RESULT_OK)launchVpn();else toast("Bạn chưa cho phép VPN; không quan sát kết nối.");}}
 private void startLocation(){
  AppState state=new AppState(this);
  if(!state.accepts("device")){toast("Chọn nguồn Agent và bật đồng bộ trước khi chia sẻ vị trí.");return;}
  if(!state.locationAllowed()){toast("Portal chưa cho phép vị trí. Thu hồi thiết bị cũ và ghép mã mới có nguồn Agent và Cho phép vị trí.");return;}
  if(!StatusNotice.permitted(this)){toast("Cần cho phép thông báo trạng thái.");return;}
  if(!LocationCaptureService.permitted(this)){requestPermissions(new String[]{Manifest.permission.ACCESS_COARSE_LOCATION,Manifest.permission.ACCESS_FINE_LOCATION},24);toast("Chọn vị trí gần đúng hoặc chính xác, rồi bấm bật vị trí lại.");return;}
  new AlertDialog.Builder(this).setTitle("Chia sẻ vị trí thiết bị đến techai.sbs?").setMessage("Trong phiên này, app lấy vị trí khoảng mỗi phút và gửi kèm mẫu Agent khoảng 15 phút/lần hoặc khi bạn bấm đồng bộ. Có thể tiếp tục khi màn hình tắt và tốn pin. Có thông báo thường trực, nút Dừng vị trí; không tự bật lại sau khởi động hoặc khi Android dừng dịch vụ. Chỉ vị trí có tuổi tối đa 5 phút được gửi.").setNegativeButton("Hủy",null).setPositiveButton("Đồng ý chia sẻ",(d,w)->{
   state.selected("location",true);state.locationSharing(true);
   try{startForegroundService(new Intent(this,LocationCaptureService.class));}catch(IllegalStateException|SecurityException error){state.locationSharing(false);toast("Android chưa cho phép phiên vị trí. Hãy mở app và thử lại.");}
  }).show();
 }
 private void requestMetadataPermission(String permission,int request,String explanation){new AlertDialog.Builder(this).setMessage(explanation).setNegativeButton("Hủy",null).setPositiveButton("Yêu cầu quyền",(d,w)->{if(checkSelfPermission(permission)==PackageManager.PERMISSION_GRANTED)toast("Quyền đã được cấp.");else requestPermissions(new String[]{permission},request);}).show();}
 private void startSync(){
  if(consent==null||!consent.isChecked()){toast("Hãy đọc và xác nhận đồng ý trước.");return;}
  AppState state=new AppState(this);boolean any=false;for(String source:AppState.SOURCES)any|=state.selected(source)&&state.grants().contains(source);if(!any){toast("Chọn ít nhất một nguồn.");return;}
  if(!StatusNotice.permitted(this)){
   if(Build.VERSION.SDK_INT>=33&&checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS)!=PackageManager.PERMISSION_GRANTED)requestPermissions(new String[]{Manifest.permission.POST_NOTIFICATIONS},23);
   else startActivity(new Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE,getPackageName()));
   toast("Cần cho phép thông báo trạng thái; sau đó bấm Bật đồng bộ lại.");return;
  }
  if(!state.enabled())SyncControl.start(this);state.status("Đang bật đồng bộ metadata. Bạn có thể dừng bất cứ lúc nào.");refresh();
 }
 @Override public void onRequestPermissionsResult(int request,String[] permissions,int[] results){super.onRequestPermissionsResult(request,permissions,results);toast(results.length>0&&results[0]==PackageManager.PERMISSION_GRANTED?"Đã cấp quyền.":"Android chưa cấp quyền; app không truy cập dữ liệu bị chặn.");if(request==22){new AppState(this).resetCallCursor();NotificationListenerService.requestRebind(new ComponentName(this,MetadataListener.class));}refresh();}
 private void refresh(){if(status==null)return;if(screenPaired!=new SecureStore(this).paired()){render();return;}AppState state=new AppState(this);String access=Settings.Secure.getString(getContentResolver(),"enabled_notification_listeners");ComponentName component=new ComponentName(this,MetadataListener.class);boolean notificationAccess=false;if(access!=null)for(String value:access.split(":"))if(component.equals(ComponentName.unflattenFromString(value)))notificationAccess=true;
  String last=state.lastSync()==0?"chưa có":DateFormat.getDateTimeInstance().format(new Date(state.lastSync()));
  status.setText((state.enabled()?"● ĐANG ĐỒNG BỘ":"○ ĐÃ DỪNG")+"\n"+state.status()+"\nQuyền thông báo nguồn: "+(notificationAccess?"đã cấp":"chưa cấp")+" · SMS: "+(checkSelfPermission(Manifest.permission.RECEIVE_SMS)==PackageManager.PERMISSION_GRANTED?"đã cấp":"chưa cấp")+" · cuộc gọi: "+(CallMetadata.permitted(this)?"đã cấp":"chưa cấp")+"\nVị trí: "+(LocationCaptureService.active()&&state.locationSharing()?"đang chia sẻ":"đã tắt")+" · Agent: "+(state.selected("device")?"đã chọn":"đã tắt")+"\nVPN: "+(NetworkVpnService.active()?"đang bật":"đã tắt")+" · "+state.vpnStatus()+"\nLần đồng bộ: "+last+(new SecureStore(this).paired()?"\nToken hết hạn: "+state.expiry():""));
 }
 private void toast(String value){Toast.makeText(this,value,Toast.LENGTH_LONG).show();}
}
