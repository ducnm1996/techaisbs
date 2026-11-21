package sbs.techai.metadata;

import android.Manifest;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Build;

final class StatusNotice {
 static final int ID=1701;
 static boolean permitted(Context context) {
  if(Build.VERSION.SDK_INT>=33 && context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS)!=PackageManager.PERMISSION_GRANTED)return false;
  NotificationManager manager=context.getSystemService(NotificationManager.class);
  NotificationChannel channel=manager.getNotificationChannel("sync_status");
  return manager.areNotificationsEnabled()&&(channel==null||channel.getImportance()!=NotificationManager.IMPORTANCE_NONE);
 }
 static void update(Context context) {
  NotificationManager manager=context.getSystemService(NotificationManager.class);
  manager.createNotificationChannel(new NotificationChannel("sync_status","Trạng thái đồng bộ TechAI",NotificationManager.IMPORTANCE_LOW));
  if(!new AppState(context).enabled() || !permitted(context)){manager.cancel(ID);return;}
  PendingIntent open=PendingIntent.getActivity(context,0,new Intent(context,MainActivity.class),PendingIntent.FLAG_UPDATE_CURRENT|PendingIntent.FLAG_IMMUTABLE);
  Notification notice=new Notification.Builder(context,"sync_status").setSmallIcon(R.drawable.ic_techai).setContentTitle("TechAI đang đồng bộ metadata").setContentText("Chạm để xem nguồn dữ liệu hoặc dừng đồng bộ.").setContentIntent(open).setOngoing(true).setOnlyAlertOnce(true).build();
  manager.notify(ID,notice);
 }
}
