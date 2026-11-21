package sbs.techai.metadata;

import android.content.Context;
import java.time.Instant;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import org.json.JSONObject;

final class Capture {
 static final ExecutorService IO=Executors.newSingleThreadExecutor();
 private static EventQueue queue;
 static synchronized EventQueue queue(Context context) { if(queue==null)queue=new EventQueue(context.getApplicationContext());return queue; }
 static void event(Context context,String source,String identity,long time,String pkg,String phone,String direction,Long duration) {
  Context app=context.getApplicationContext();
  IO.execute(()->record(app,source,identity,time,pkg,phone,direction,duration));
 }
 static boolean record(Context app,String source,String identity,long time,String pkg,String phone,String direction,Long duration) {
   try {
    AppState state=new AppState(app);
    if(!state.accepts(source)||!new SecureStore(app).paired()||time<state.since(source))return false;
    if(!StatusNotice.permitted(app)){SyncControl.pause(app);state.status("Đã dừng: thông báo trạng thái không được phép.");return false;}
    JSONObject event=new JSONObject().put("event_id",MetadataRules.eventId(state.installId(),source,identity,time)).put("source",source).put("occurred_at",Instant.ofEpochMilli(time).toString());
    if(pkg!=null)event.put("app_package",pkg);
    String canonical=MetadataRules.canonicalPhone(phone);if(canonical!=null)event.put("sender_phone",canonical);
    if("call".equals(source)&&direction!=null)event.put("call_direction",direction);
    if("call".equals(source)&&duration!=null)event.put("duration_seconds",Math.max(0,Math.min(604800,duration)));
    queue(app).enqueue(event);SyncControl.sync(app);return true;
   } catch(Exception ignored){new AppState(app).status("Chưa lưu được metadata vào hàng đợi bảo vệ. Mở app để kiểm tra.");return false;}
 }
}
