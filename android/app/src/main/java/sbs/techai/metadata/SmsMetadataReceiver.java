package sbs.techai.metadata;

import android.Manifest;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.provider.Telephony;
import android.telephony.SmsMessage;
import android.telephony.PhoneNumberUtils;
import android.telephony.TelephonyManager;
import java.util.Locale;

public final class SmsMetadataReceiver extends BroadcastReceiver {
 @Override public void onReceive(Context context,Intent intent){
  if(!Telephony.Sms.Intents.SMS_RECEIVED_ACTION.equals(intent.getAction())||context.checkSelfPermission(Manifest.permission.RECEIVE_SMS)!=PackageManager.PERMISSION_GRANTED)return;
  PendingResult pending=goAsync();
  Capture.IO.execute(()->{try {
   for(SmsMessage message:Telephony.Sms.Intents.getMessagesFromIntent(intent)){
    String origin=message.getOriginatingAddress();String phone=MetadataRules.canonicalPhone(origin);
    if(phone==null&&origin!=null){String country=context.getSystemService(TelephonyManager.class).getNetworkCountryIso();if(country!=null&&!country.isEmpty())phone=PhoneNumberUtils.formatNumberToE164(origin,country.toUpperCase(Locale.ROOT));}
    // No body, subject or PDU is retained, inspected or transmitted.
    Capture.record(context,"sms","sms:"+origin,message.getTimestampMillis(),null,phone,null,null);
   }
  }catch(Exception ignored){new AppState(context).status("Không nhận được metadata SMS hợp lệ; không đọc nội dung.");}finally{pending.finish();}});
 }
}
