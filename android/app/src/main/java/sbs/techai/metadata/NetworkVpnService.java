package sbs.techai.metadata;

import android.app.*;
import android.content.*;
import android.content.pm.PackageManager;
import android.content.pm.ServiceInfo;
import android.net.*;
import android.os.*;
import android.system.Os;
import android.system.OsConstants;
import hev.htproxy.TProxyService;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.time.Instant;
import java.util.*;
import java.util.concurrent.*;

/** User-started per-app VPN. No gateway, MITM certificates, payload logging or auto-start. */
public final class NetworkVpnService extends VpnService {
 static final String[] PACKAGES={"com.zing.zalo","com.viber.voip","com.google.android.gm","com.microsoft.office.outlook","com.samsung.android.email.provider","com.android.email"};
 private static volatile boolean active;
 private static final Object ENGINE_LOCK=new Object();private static NetworkVpnService engineOwner;
 private volatile boolean stopping;
 private final ExecutorService lifecycle=Executors.newSingleThreadExecutor();
 private final ScheduledExecutorService watchdog=Executors.newSingleThreadScheduledExecutor();
 private ParcelFileDescriptor tun,bridge,nativeSide;
 private LocalSocksProxy proxy;private NetworkObserver observer;private File config;
 private boolean nativeStarted,starting;
 static boolean active(){return active;}
 static void stop(Context context){AppState state=new AppState(context);state.vpnRequested(false);state.vpnStatus("VPN đã tắt. Dữ liệu đã ghi còn chờ vẫn có thể đồng bộ.");context.stopService(new Intent(context,NetworkVpnService.class));}
 private boolean consent(){AppState s=new AppState(this);try{return !stopping&&s.vpnRequested()&&s.accepts("network")&&StatusNotice.permitted(this)&&new SecureStore(this).paired()&&Instant.parse(s.expiry()).isAfter(Instant.now());}catch(RuntimeException invalid){return false;}}
 @Override public int onStartCommand(Intent intent,int flags,int startId){
  if(intent!=null&&"STOP".equals(intent.getAction())){stop(this);stopSelf();return START_NOT_STICKY;}
  if(intent==null||!"START".equals(intent.getAction())||!consent()||VpnService.prepare(this)!=null){stopSelf();return START_NOT_STICKY;}
  if(starting)return START_NOT_STICKY;starting=true;
  NotificationManager manager=getSystemService(NotificationManager.class);manager.createNotificationChannel(new NotificationChannel("network_vpn","VPN cục bộ TechAI",NotificationManager.IMPORTANCE_LOW));
  if(manager.getNotificationChannel("network_vpn").getImportance()==NotificationManager.IMPORTANCE_NONE){new AppState(this).vpnStatus("Hãy bật thông báo kênh VPN trước khi sử dụng.");stopSelf();return START_NOT_STICKY;}
  PendingIntent open=PendingIntent.getActivity(this,3,new Intent(this,MainActivity.class),PendingIntent.FLAG_UPDATE_CURRENT|PendingIntent.FLAG_IMMUTABLE);
  PendingIntent stop=PendingIntent.getService(this,4,new Intent(this,NetworkVpnService.class).setAction("STOP"),PendingIntent.FLAG_UPDATE_CURRENT|PendingIntent.FLAG_IMMUTABLE);
  Notification notice=new Notification.Builder(this,"network_vpn").setSmallIcon(R.drawable.ic_techai).setContentTitle("TechAI: VPN cục bộ đang bật").setContentText("Chỉ metadata IP đích Zalo/Viber/email. Không lưu nội dung.").setContentIntent(open).setOngoing(true).addAction(new Notification.Action.Builder(null,"Tắt VPN",stop).build()).build();
  try{if(Build.VERSION.SDK_INT>=34)startForeground(1703,notice,ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE);else startForeground(1703,notice);}
  catch(SecurityException|IllegalStateException failure){new AppState(this).vpnStatus("Android chưa cho phép dịch vụ VPN. Mở app để thử lại.");stopSelf();return START_NOT_STICKY;}
  lifecycle.execute(this::startSession);return START_NOT_STICKY;
 }
 private void startSession(){
  try{
   AppState state=new AppState(this);if(!consent()||Instant.parse(state.expiry()).isBefore(Instant.now()))throw new IOException("Consent/token unavailable");
   Map<Integer,String> owners=new HashMap<>();List<String> allowed=new ArrayList<>();
   for(String pkg:PACKAGES){String source=MetadataRules.sourceForPackage(pkg);if(!state.accepts(source))continue;try{
    android.content.pm.PackageInfo info=getPackageManager().getPackageInfo(pkg,0);if(info.sharedUserId!=null||info.applicationInfo==null)continue;
    int uid=info.applicationInfo.uid;if(uid<10000)continue;String[] shared=getPackageManager().getPackagesForUid(uid);
    if(shared==null||shared.length!=1||!pkg.equals(shared[0]))continue;owners.put(uid,pkg);allowed.add(pkg);
   }catch(PackageManager.NameNotFoundException ignored){}}
   if(allowed.isEmpty())throw new IOException("No selected supported apps");
   byte[] random=new byte[24];new SecureRandom().nextBytes(random);String password=android.util.Base64.encodeToString(random,android.util.Base64.NO_WRAP);Arrays.fill(random,(byte)0);
   proxy=new LocalSocksProxy(new LocalSocksProxy.Protector(){public boolean protect(java.net.Socket socket){return NetworkVpnService.this.protect(socket);}public boolean protect(java.net.DatagramSocket socket){return NetworkVpnService.this.protect(socket);}},"techai",password);
   config=new File(getCacheDir(),"local-vpn.yml");
   String yaml="tunnel:\n  name: techai\n  mtu: 1500\n  ipv4: 198.18.0.1\n  ipv6: 'fd00:7465:6368::1'\nsocks5:\n  address: 127.0.0.1\n  port: "+proxy.port()+"\n  udp: 'udp'\n  username: 'techai'\n  password: '"+password+"'\nmisc:\n  max-session-count: 64\n  connect-timeout: 15000\n  tcp-read-write-timeout: 300000\n  udp-read-write-timeout: 120000\n  log-file: '/dev/null'\n  log-level: error\n";
   try(FileOutputStream output=new FileOutputStream(config)){output.write(yaml.getBytes(StandardCharsets.UTF_8));}
   Builder builder=new Builder().setSession("TechAI · IP đích cục bộ").setMtu(1500).setBlocking(false).addAddress("198.18.0.1",32).addAddress("fd00:7465:6368::1",128).addRoute("0.0.0.0",0).addRoute("::",0);
   // Use the existing network's DNS, not a third-party resolver or a DNS payload inspector.
   ConnectivityManager connectivity=getSystemService(ConnectivityManager.class);Network physical=connectivity.getActiveNetwork();LinkProperties link=physical==null?null:connectivity.getLinkProperties(physical);int dns=0;
   if(link!=null)for(java.net.InetAddress address:link.getDnsServers()){builder.addDnsServer(address.getHostAddress());dns++;}
   if(dns==0)throw new IOException("No system DNS available");
   for(String pkg:allowed)builder.addAllowedApplication(pkg);
   if(!consent())throw new IOException("Stopped");tun=builder.establish();if(tun==null)throw new IOException("VPN not established");
   java.io.FileDescriptor first=new java.io.FileDescriptor(),second=new java.io.FileDescriptor();Os.socketpair(OsConstants.AF_UNIX,OsConstants.SOCK_DGRAM|OsConstants.SOCK_NONBLOCK,0,first,second);
   try{bridge=ParcelFileDescriptor.dup(first);nativeSide=ParcelFileDescriptor.dup(second);}finally{Os.close(first);Os.close(second);}
   observer=new NetworkObserver(this,owners);
   if(!consent())throw new IOException("Stopped");synchronized(ENGINE_LOCK){if(engineOwner!=null)throw new IOException("Previous VPN closing");engineOwner=this;nativeStarted=TProxyService.TProxyStartService(config.getAbsolutePath(),nativeSide.getFd());if(!nativeStarted)throw new IOException("Native tunnel unavailable");}
   active=true;
   new AppState(this).vpnStatus("VPN cục bộ đang bật cho "+allowed.size()+" ứng dụng. IP đích được gộp theo phút; kết nối không xác định được app sẽ bỏ qua.");
   Thread outgoing=new Thread(()->relay(true),"techai-tun-out"),incoming=new Thread(()->relay(false),"techai-tun-in");outgoing.setDaemon(true);incoming.setDaemon(true);outgoing.start();incoming.start();
   watchdog.scheduleWithFixedDelay(()->{NotificationChannel channel=getSystemService(NotificationManager.class).getNotificationChannel("network_vpn");if(!consent()||channel==null||channel.getImportance()==NotificationManager.IMPORTANCE_NONE||!TProxyService.TProxyIsRunning()||!outgoing.isAlive()||!incoming.isAlive()){new AppState(this).vpnStatus("VPN đã dừng; mở app để bật lại. Kết nối thông thường được khôi phục.");stopSelf();}},2,2,TimeUnit.SECONDS);
  }catch(Exception|LinkageError failure){new AppState(this).vpnStatus("Không khởi động được VPN. Kiểm tra nguồn đã chọn, app đã cài, mạng/DNS và quyền VPN rồi thử lại.");stopSelf();}
 }
 private void relay(boolean outgoing){
  byte[] buffer=new byte[65535];
  try{java.io.FileDescriptor from=(outgoing?tun:bridge).getFileDescriptor(),to=(outgoing?bridge:tun).getFileDescriptor();
   while(!stopping){if(!ready(from,OsConstants.POLLIN))continue;int size;
    try{size=Os.read(from,buffer,0,buffer.length);}catch(android.system.ErrnoException failure){if(failure.errno==OsConstants.EAGAIN||failure.errno==OsConstants.EINTR)continue;throw failure;}
    if(size<=0)break;
    if(outgoing&&observer!=null)observer.observe(PacketHeader.parse(buffer,size));
    while(!stopping){if(!ready(to,OsConstants.POLLOUT))continue;try{if(Os.write(to,buffer,0,size)!=size)throw new IOException("Incomplete packet");break;}catch(android.system.ErrnoException failure){if(failure.errno!=OsConstants.EAGAIN&&failure.errno!=OsConstants.EINTR)throw failure;}}
    Arrays.fill(buffer,0,size,(byte)0);
   }
  }catch(Exception failure){if(!stopping){new AppState(this).vpnStatus("VPN đã dừng do lỗi chuyển tiếp; kết nối thông thường được khôi phục.");stopSelf();}}
  finally{Arrays.fill(buffer,(byte)0);}
 }
 private boolean ready(java.io.FileDescriptor fd,int events) throws android.system.ErrnoException {
  android.system.StructPollfd poll=new android.system.StructPollfd();poll.fd=fd;poll.events=(short)events;
  return Os.poll(new android.system.StructPollfd[]{poll},250)>0;
 }
 @Override public void onRevoke(){new AppState(this).vpnStatus("Quyền VPN đã bị thu hồi. Phiên quan sát kết thúc.");stopSelf();}
 @Override public void onTaskRemoved(Intent rootIntent){new AppState(this).vpnStatus("VPN đã tắt khi đóng tác vụ ứng dụng.");stopSelf();}
 @Override public void onDestroy(){
  stopping=true;active=false;new AppState(this).vpnRequested(false);watchdog.shutdownNow();if(observer!=null)observer.close();
  // Closing TUN promptly restores normal routing even if the native worker is slow to exit.
  if(tun!=null)try{tun.close();}catch(IOException ignored){}
  lifecycle.execute(()->{
   if(observer!=null)observer.close();if(proxy!=null)proxy.close();
   synchronized(ENGINE_LOCK){if(engineOwner==this){try{if(nativeStarted)TProxyService.TProxyStopService();}catch(LinkageError ignored){}finally{engineOwner=null;}}}
   if(bridge!=null){try{Os.shutdown(bridge.getFileDescriptor(),OsConstants.SHUT_RDWR);}catch(Exception ignored){}try{bridge.close();}catch(IOException ignored){}}
   if(nativeSide!=null)try{nativeSide.close();}catch(IOException ignored){}
   if(tun!=null)try{tun.close();}catch(IOException ignored){}
   if(config!=null)config.delete();
  });lifecycle.shutdown();stopForeground(STOP_FOREGROUND_REMOVE);super.onDestroy();
 }
}
