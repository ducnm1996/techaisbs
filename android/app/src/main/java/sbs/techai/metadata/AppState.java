package sbs.techai.metadata;

import android.content.Context;
import android.content.SharedPreferences;
import org.json.JSONArray;
import org.json.JSONException;
import java.util.HashSet;
import java.util.Set;
import java.util.UUID;

final class AppState {
 static final String[] SOURCES={"zalo","viber","sms","call","email","device","network"};
 private final SharedPreferences prefs;
 private static final Object SESSION_LOCK=new Object();
 AppState(Context context) { prefs=context.getSharedPreferences("settings",Context.MODE_PRIVATE); }
 boolean enabled() { return prefs.getBoolean("enabled",false); }
 void enabled(boolean value) { prefs.edit().putBoolean("enabled",value).apply(); }
 boolean selected(String source) { return prefs.getBoolean("source_"+source,false); }
 void selected(String source, boolean value) { prefs.edit().putBoolean("source_"+source,value).putLong("since_"+source,System.currentTimeMillis()).commit(); }
 long since(String source) { return Math.max(started(),prefs.getLong("since_"+source,0)); }
 Set<String> grants() { return new HashSet<>(prefs.getStringSet("grants",new HashSet<>())); }
 boolean accepts(String source) { return enabled() && selected(source) && grants().contains(source); }
 String sessionId() { synchronized(SESSION_LOCK){String id=prefs.getString("pair_session",null);if(id==null){id=UUID.randomUUID().toString();if(!prefs.edit().putString("pair_session",id).commit())throw new IllegalStateException("Session unavailable");}return id;} }
 boolean vpnRequested() { return prefs.getBoolean("vpn_requested",false); }
 void vpnRequested(boolean value) { prefs.edit().putBoolean("vpn_requested",value).commit(); }
 String vpnStatus() { return prefs.getString("vpn_status","VPN đang tắt."); }
 void vpnStatus(String value) { prefs.edit().putString("vpn_status",value).apply(); }
 boolean locationAllowed() { return prefs.getBoolean("allow_location",false); }
 void locationAllowed(boolean value) { prefs.edit().putBoolean("allow_location",value).commit(); }
 boolean locationSharing() { return prefs.getBoolean("location_sharing",false); }
 void locationSharing(boolean value) { prefs.edit().putBoolean("location_sharing",value).commit(); }
 long lastTelemetry() { return prefs.getLong("last_telemetry",0); }
 void lastTelemetry(long value) { prefs.edit().putLong("last_telemetry",value).commit(); }
 String installId() {
  String id=prefs.getString("install_id",null);
  if(id==null) { id=UUID.randomUUID().toString(); prefs.edit().putString("install_id",id).commit(); }
  return id;
 }
 void paired(JSONArray grants, String expiry) throws JSONException {
  Set<String> set=new HashSet<>();for(int i=0;i<grants.length();i++)set.add(grants.getString(i));
  SharedPreferences.Editor editor=prefs.edit().putString("pair_session",UUID.randomUUID().toString()).putStringSet("grants",set).putString("expires",expiry).putBoolean("enabled",false).putBoolean("vpn_requested",false).putBoolean("location_sharing",false).putBoolean("allow_location",false).putLong("last_telemetry",0).putLong("started",0).putLong("call_cursor",-1);
  for(String source:SOURCES) editor.putBoolean("source_"+source,false);
  editor.commit();
 }
 String expiry() { return prefs.getString("expires",""); }
 long started() { return prefs.getLong("started",0); }
 void start() { prefs.edit().putLong("started",System.currentTimeMillis()).putLong("last_telemetry",0).putLong("call_cursor",0).commit(); enabled(true); }
 long callCursor() { return prefs.getLong("call_cursor",-1); }
 void callCursor(long id) { prefs.edit().putLong("call_cursor",id).commit(); }
 void resetCallCursor() { prefs.edit().putLong("call_cursor",0).commit(); }
 String status() { return prefs.getString("status","Chưa ghép thiết bị."); }
 void status(String message) { prefs.edit().putString("status",message).apply(); }
 void synced() { prefs.edit().putLong("last_sync",System.currentTimeMillis()).apply(); }
 long lastSync() { return prefs.getLong("last_sync",0); }
 void clearPair() { enabled(false); prefs.edit().remove("pair_session").remove("grants").remove("expires").putBoolean("vpn_requested",false).putBoolean("location_sharing",false).putBoolean("allow_location",false).putLong("last_telemetry",0).putLong("started",0).putLong("call_cursor",-1).commit(); }
}
