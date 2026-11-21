package sbs.techai.metadata;

import android.Manifest;
import android.app.Notification;
import android.content.pm.PackageManager;
import android.database.ContentObserver;
import android.os.Handler;
import android.os.Looper;
import android.provider.CallLog;
import android.service.notification.NotificationListenerService;
import android.service.notification.StatusBarNotification;

public final class MetadataListener extends NotificationListenerService {
 private final ContentObserver calls=new ContentObserver(new Handler(Looper.getMainLooper())){public void onChange(boolean selfChange){if(new AppState(MetadataListener.this).accepts("call"))SyncControl.sync(MetadataListener.this);}};
 @Override public void onListenerConnected(){
  try {getContentResolver().registerContentObserver(CallLog.Calls.CONTENT_URI,true,calls);}catch(SecurityException ignored){}
  StatusNotice.update(this);
 }
 @Override public void onDestroy(){getContentResolver().unregisterContentObserver(calls);super.onDestroy();}
 @Override public void onNotificationPosted(StatusBarNotification notification){
  if(notification==null||(notification.getNotification().flags&Notification.FLAG_GROUP_SUMMARY)!=0)return;
  String source=MetadataRules.sourceForPackage(notification.getPackageName());if(source==null)return;
  if("sms".equals(source)&&checkSelfPermission(Manifest.permission.RECEIVE_SMS)==PackageManager.PERMISSION_GRANTED)return;
  if("call".equals(source)&&CallMetadata.permitted(this))return;
  // Deliberately do not read Notification.extras, title, text or MessagingStyle.
  Capture.event(this,source,"notification:"+notification.getKey(),notification.getPostTime(),notification.getPackageName(),null,null,null);
 }
}
