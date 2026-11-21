package sbs.techai.metadata;

import android.app.ActivityManager;
import android.content.Context;
import android.location.Location;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.os.BatteryManager;
import android.os.Build;
import android.os.PowerManager;
import android.os.StatFs;
import android.os.SystemClock;
import java.time.Instant;
import java.util.Locale;
import java.util.TimeZone;
import org.json.JSONObject;

final class DeviceTelemetry {
 static synchronized void capture(Context context) throws Exception {
  AppState state=new AppState(context);long now=System.currentTimeMillis();
  String session=state.sessionId();
  if(!state.accepts("device")||!StatusNotice.permitted(context)||!AgentRules.snapshotDue(state.lastTelemetry(),now))return;
  JSONObject data=new JSONObject();
  data.put("manufacturer",Build.MANUFACTURER).put("model",Build.MODEL).put("android_release",Build.VERSION.RELEASE).put("sdk",Build.VERSION.SDK_INT).put("app_version",BuildConfig.VERSION_NAME);
  if(Build.VERSION.SECURITY_PATCH!=null&&Build.VERSION.SECURITY_PATCH.matches("\\d{4}-\\d{2}-\\d{2}"))data.put("security_patch",Build.VERSION.SECURITY_PATCH);
  BatteryManager battery=context.getSystemService(BatteryManager.class);int level=battery.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY);
  data.put("battery_percent",level>=0&&level<=100?level:JSONObject.NULL).put("charging",battery.isCharging());
  data.put("power_save",context.getSystemService(PowerManager.class).isPowerSaveMode());
  ActivityManager.MemoryInfo memory=new ActivityManager.MemoryInfo();context.getSystemService(ActivityManager.class).getMemoryInfo(memory);
  StatFs storage=new StatFs(context.getFilesDir().getAbsolutePath());
  data.put("ram_total_bytes",memory.totalMem).put("ram_available_bytes",memory.availMem).put("storage_total_bytes",storage.getTotalBytes()).put("storage_available_bytes",storage.getAvailableBytes());
  ConnectivityManager connectivity=context.getSystemService(ConnectivityManager.class);Network network=connectivity.getActiveNetwork();NetworkCapabilities caps=network==null?null:connectivity.getNetworkCapabilities(network);
  String type="none";if(caps!=null){if(caps.hasTransport(NetworkCapabilities.TRANSPORT_VPN))type="vpn";else if(caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI))type="wifi";else if(caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR))type="cellular";else if(caps.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET))type="ethernet";else type="other";}
  data.put("network_type",type).put("network_metered",connectivity.isActiveNetworkMetered()).put("internet_validated",caps!=null&&caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED));
  data.put("timezone",TimeZone.getDefault().getID()).put("locale",Locale.getDefault().toLanguageTag()).put("uptime_seconds",SystemClock.elapsedRealtime()/1000);
  JSONObject event=new JSONObject().put("event_id",MetadataRules.eventId(state.installId(),"device","snapshot",now)).put("source","device").put("occurred_at",Instant.ofEpochMilli(now).toString()).put("device_metadata",data);
  Location location=LocationCaptureService.current(context);
  if(location!=null)event.put("latitude",location.getLatitude()).put("longitude",location.getLongitude()).put("accuracy_m",Math.min(100000,Math.max(0,location.getAccuracy())));
  // Recheck after collecting; a concurrent pause must prevent any new enqueue.
  if(!state.accepts("device"))return;
  if(!state.locationSharing()||!state.locationAllowed()){event.remove("latitude");event.remove("longitude");event.remove("accuracy_m");}
  if(Capture.queue(context).enqueue(event,session)&&session.equals(state.sessionId()))state.lastTelemetry(now);
 }
}
