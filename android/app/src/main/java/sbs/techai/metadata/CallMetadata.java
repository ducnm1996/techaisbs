package sbs.techai.metadata;

import android.Manifest;
import android.content.Context;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.provider.CallLog;
import android.telephony.PhoneNumberUtils;
import android.telephony.TelephonyManager;
import java.util.Locale;

final class CallMetadata {
 static boolean permitted(Context context){return context.checkSelfPermission(Manifest.permission.READ_CALL_LOG)==PackageManager.PERMISSION_GRANTED;}
 static synchronized void scan(Context context) {
  AppState state=new AppState(context);if(!state.accepts("call")||!permitted(context))return;
  try {
   if(state.callCursor()<0){
    try(Cursor cursor=context.getContentResolver().query(CallLog.Calls.CONTENT_URI,new String[]{CallLog.Calls._ID},null,null,CallLog.Calls._ID+" DESC")){
     state.callCursor(cursor!=null&&cursor.moveToFirst()?cursor.getLong(0):0);
    }
    return;
   }
   String country="";try {country=context.getSystemService(TelephonyManager.class).getNetworkCountryIso().toUpperCase(Locale.ROOT);}catch(Exception ignored){}
   String[] columns={CallLog.Calls._ID,CallLog.Calls.DATE,CallLog.Calls.NUMBER,CallLog.Calls.TYPE,CallLog.Calls.DURATION};
   try(Cursor cursor=context.getContentResolver().query(CallLog.Calls.CONTENT_URI,columns,CallLog.Calls._ID+">? AND "+CallLog.Calls.DATE+">=?",new String[]{Long.toString(state.callCursor()),Long.toString(state.since("call"))},CallLog.Calls._ID+" ASC")){
    if(cursor==null)return;
    while(cursor.moveToNext()){
     long id=cursor.getLong(0),time=cursor.getLong(1);String raw=cursor.getString(2);String phone=MetadataRules.canonicalPhone(raw);
     if(phone==null&&raw!=null&&!country.isEmpty())phone=PhoneNumberUtils.formatNumberToE164(raw,country);
     String direction;switch(cursor.getInt(3)){case CallLog.Calls.INCOMING_TYPE:direction="incoming";break;case CallLog.Calls.OUTGOING_TYPE:direction="outgoing";break;case CallLog.Calls.MISSED_TYPE:direction="missed";break;case CallLog.Calls.REJECTED_TYPE:direction="rejected";break;case CallLog.Calls.BLOCKED_TYPE:direction="blocked";break;default:direction="unknown";}
     if(!Capture.record(context,"call","call-log:"+id,time,null,phone,direction,cursor.getLong(4)))break;
     state.callCursor(id);
    }
   }
  }catch(SecurityException ignored){state.status("Máy đã từ chối quyền nhật ký cuộc gọi; không truy cập dữ liệu này.");}
 }
}
