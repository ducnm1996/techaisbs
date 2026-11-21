package sbs.techai.metadata;

import android.Manifest;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.content.pm.ServiceInfo;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.os.Build;
import android.os.Bundle;
import android.os.IBinder;
import android.os.Looper;
import android.os.SystemClock;

/** Explicit, visible sharing session; never restarted automatically after reboot/kill. */
public final class LocationCaptureService extends Service implements LocationListener {
 private static volatile Location latest;
 private static volatile boolean active;
 private LocationManager locations;
 static boolean permitted(Context context){return context.checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION)==PackageManager.PERMISSION_GRANTED||context.checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)==PackageManager.PERMISSION_GRANTED;}
 static boolean active(){return active;}
 static Location current(Context context){
  AppState state=new AppState(context);Location sample=latest;
  if(!active||sample==null||!permitted(context))return null;
  long age=(SystemClock.elapsedRealtimeNanos()-sample.getElapsedRealtimeNanos())/1000000;
  return AgentRules.freshLocation(state.locationSharing()&&state.accepts("device"),state.locationAllowed(),state.since("location"),sample.getTime(),age)?new Location(sample):null;
 }
 private static void discardPendingLocation(Context context){
  Context app=context.getApplicationContext();Capture.IO.execute(()->{synchronized(DeviceTelemetry.class){Capture.queue(app).clearSource("device");}});
 }
 static void stop(Context context){
  AppState state=new AppState(context);boolean sharing=state.locationSharing()||active;
  state.locationSharing(false);latest=null;context.stopService(new Intent(context,LocationCaptureService.class));
  if(sharing)discardPendingLocation(context);
 }
 @Override public int onStartCommand(Intent intent,int flags,int startId){
  AppState state=new AppState(this);
  if(intent!=null&&"STOP".equals(intent.getAction())){stop(this);return START_NOT_STICKY;}
  if(!state.accepts("device")||!state.locationAllowed()||!state.locationSharing()||!permitted(this)||!StatusNotice.permitted(this)){state.locationSharing(false);stopSelf();return START_NOT_STICKY;}
  NotificationManager manager=getSystemService(NotificationManager.class);
  manager.createNotificationChannel(new NotificationChannel("location_sharing","Chia sẻ vị trí TechAI",NotificationManager.IMPORTANCE_LOW));
  if(manager.getNotificationChannel("location_sharing").getImportance()==NotificationManager.IMPORTANCE_NONE){state.locationSharing(false);state.status("Chưa chia sẻ vị trí: hãy cho phép kênh thông báo vị trí.");stopSelf();return START_NOT_STICKY;}
  PendingIntent open=PendingIntent.getActivity(this,0,new Intent(this,MainActivity.class),PendingIntent.FLAG_IMMUTABLE|PendingIntent.FLAG_UPDATE_CURRENT);
  PendingIntent stop=PendingIntent.getService(this,1,new Intent(this,LocationCaptureService.class).setAction("STOP"),PendingIntent.FLAG_IMMUTABLE|PendingIntent.FLAG_UPDATE_CURRENT);
  Notification notification=new Notification.Builder(this,"location_sharing").setSmallIcon(R.drawable.ic_techai).setContentTitle("TechAI đang chia sẻ vị trí thiết bị").setContentText("Gửi kèm mẫu agent khoảng 15 phút/lần. Chạm Dừng vị trí để kết thúc.").setContentIntent(open).setOngoing(true).addAction(new Notification.Action.Builder(null,"Dừng vị trí",stop).build()).build();
  try{
   if(Build.VERSION.SDK_INT>=29)startForeground(1702,notification,ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION);else startForeground(1702,notification);
   if(active)return START_NOT_STICKY;
   locations=getSystemService(LocationManager.class);boolean registered=false;
   if(locations.isProviderEnabled(LocationManager.GPS_PROVIDER)&&checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)==PackageManager.PERMISSION_GRANTED){locations.requestLocationUpdates(LocationManager.GPS_PROVIDER,60000,0,this,Looper.getMainLooper());registered=true;}
   if(locations.isProviderEnabled(LocationManager.NETWORK_PROVIDER)){locations.requestLocationUpdates(LocationManager.NETWORK_PROVIDER,60000,0,this,Looper.getMainLooper());registered=true;}
   if(!registered){state.status("Chưa chia sẻ vị trí: bật Dịch vụ vị trí của Android rồi thử lại.");state.locationSharing(false);stopSelf();return START_NOT_STICKY;}
   active=true;state.status("Đang chia sẻ vị trí. Có thông báo thường trực và nút Dừng vị trí.");
  }catch(SecurityException|IllegalStateException error){state.locationSharing(false);state.status("Android chưa cho phép phiên chia sẻ vị trí. Mở app để bật lại.");stopSelf();}
  return START_NOT_STICKY;
 }
 @Override public void onLocationChanged(Location location){
  AppState state=new AppState(this);
  if(!state.accepts("device")||!state.locationSharing()||!state.locationAllowed()||!permitted(this)||!StatusNotice.permitted(this)){stop(this);return;}
  if(location==null||!location.hasAccuracy()||!Double.isFinite(location.getLatitude())||!Double.isFinite(location.getLongitude()))return;
  boolean first=latest==null;latest=new Location(location);
  if(first){state.lastTelemetry(0);SyncControl.sync(this);}
 }
 @Override public void onProviderDisabled(String provider){}
 @Override public void onProviderEnabled(String provider){}
 @Override public void onStatusChanged(String provider,int status,Bundle extras){}
 @Override public IBinder onBind(Intent intent){return null;}
 @Override public void onDestroy(){
  boolean sharing=active||new AppState(this).locationSharing();active=false;latest=null;new AppState(this).locationSharing(false);
  if(locations!=null)try{locations.removeUpdates(this);}catch(SecurityException ignored){}
  if(sharing)discardPendingLocation(this);stopForeground(STOP_FOREGROUND_REMOVE);super.onDestroy();
 }
}
