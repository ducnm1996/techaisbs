package sbs.techai.metadata;

import android.content.Context;
import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;
import org.json.JSONArray;
import org.json.JSONObject;
import java.util.List;
import java.time.Instant;

public final class SyncWorker extends Worker {
 public SyncWorker(@NonNull Context context,@NonNull WorkerParameters parameters){super(context,parameters);}
 @NonNull public Result doWork(){
  Context context=getApplicationContext();AppState state=new AppState(context);
  if(!state.enabled())return Result.success();
  if(!StatusNotice.permitted(context)){SyncControl.pause(context);state.status("Đã dừng: cần cho phép thông báo trạng thái.");return Result.success();}
  StatusNotice.update(context);
  try {
   SecureStore store=new SecureStore(context);String token=store.token();
   if(token==null||(!state.expiry().isEmpty()&&Instant.parse(state.expiry()).isBefore(Instant.now()))){SyncControl.pause(context);state.status("Token hết hạn. Hãy ngắt liên kết và ghép lại.");return Result.failure();}
   CallMetadata.scan(context);
   for(int batch=0;batch<10&&!isStopped()&&state.enabled();batch++){
    List<JSONObject> events=Capture.queue(context).batch(state);if(events.isEmpty()){state.status("Đã đồng bộ; không còn sự kiện chờ.");state.synced();return Result.success();}
    JSONArray data=new JSONArray();for(JSONObject event:events)data.put(event);
    ApiClient.Reply reply=ApiClient.post("/api/v1/events",new JSONObject().put("events",data),token);
    if(reply.status==200||reply.status==201){Capture.queue(context).acknowledge(events);state.synced();state.status("Đã gửi "+events.size()+" metadata, không kèm nội dung.");continue;}
    if(reply.status==401||reply.status==403){SyncControl.pause(context);store.clear();state.status("Thiết bị/token không còn hợp lệ. Ghép lại để tiếp tục.");return Result.failure();}
    if(reply.status==429||reply.status>=500){state.status("Máy chủ bận hoặc giới hạn tốc độ; sẽ thử lại có giãn cách.");return Result.retry();}
    SyncControl.pause(context);state.status("Đã dừng vì dữ liệu bị từ chối (HTTP "+reply.status+"). Kiểm tra giờ máy/nguồn, xóa hàng đợi hoặc ghép lại.");return Result.failure();
   }
   return state.enabled()?Result.retry():Result.success();
  }catch(Exception ignored){state.status("Chưa kết nối được hoặc kho khóa chưa sẵn sàng; sẽ thử lại.");return Result.retry();}
 }
}
