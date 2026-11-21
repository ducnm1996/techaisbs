package sbs.techai.metadata;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import org.json.JSONArray;
import org.json.JSONObject;
import java.util.ArrayList;
import java.util.List;

final class EventQueue extends SQLiteOpenHelper {
 private final Context context;
 EventQueue(Context context) { super(context,"metadata_queue.db",null,1);this.context=context.getApplicationContext(); }
 public void onCreate(SQLiteDatabase db) { db.execSQL("CREATE TABLE events (id TEXT PRIMARY KEY, source TEXT NOT NULL, created INTEGER NOT NULL, payload TEXT NOT NULL)"); }
 public void onUpgrade(SQLiteDatabase db,int oldVersion,int newVersion) { throw new IllegalStateException("Unsupported schema migration"); }
 synchronized boolean enqueue(JSONObject event,String session) throws Exception {
  if(!session.equals(new AppState(context).sessionId()))return false;
  ContentValues values=new ContentValues();values.put("id",event.getString("event_id"));values.put("source",event.getString("source"));values.put("created",System.currentTimeMillis());values.put("payload",SecureStore.encrypt(event.toString(),"event"));
  SQLiteDatabase db=getWritableDatabase();db.beginTransaction();try {
   db.insertWithOnConflict("events",null,values,SQLiteDatabase.CONFLICT_IGNORE);
   db.delete("events","created < ?",new String[]{Long.toString(System.currentTimeMillis()-7L*24*60*60*1000)});
   db.execSQL("DELETE FROM events WHERE id IN (SELECT id FROM events WHERE source='network' ORDER BY created DESC LIMIT -1 OFFSET 250)");
   db.execSQL("DELETE FROM events WHERE id IN (SELECT id FROM events ORDER BY CASE WHEN source='network' THEN 1 ELSE 0 END ASC, created DESC LIMIT -1 OFFSET 1000)");
   db.setTransactionSuccessful();
  } finally { db.endTransaction(); }
  return true;
 }
 synchronized List<JSONObject> batch(AppState state,String session) throws Exception {
  if(!session.equals(state.sessionId())||!state.enabled())return new ArrayList<>();
  List<JSONObject> result=new ArrayList<>();SQLiteDatabase db=getWritableDatabase();
  db.delete("events","created < ?",new String[]{Long.toString(System.currentTimeMillis()-7L*24*60*60*1000)});
  for(String source:AppState.SOURCES)if(!state.accepts(source))db.delete("events","source = ?",new String[]{source});
  try(Cursor cursor=db.query("events",new String[]{"payload"},null,null,null,null,"created ASC","40")) {
   while(cursor.moveToNext()){
    JSONObject event=new JSONObject(SecureStore.decrypt(cursor.getString(0),"event"));
    if("network".equals(event.optString("source"))&&!state.accepts(event.getJSONObject("network_metadata").getString("app_source"))){db.delete("events","id = ?",new String[]{event.getString("event_id")});continue;}
    if(!state.locationAllowed()||!state.locationSharing()||!LocationCaptureService.active()||!LocationCaptureService.permitted(context)){event.remove("latitude");event.remove("longitude");event.remove("accuracy_m");}
    result.add(event);
   }
  }
  return result;
 }
 synchronized void acknowledge(List<JSONObject> events) throws Exception { for(JSONObject event:events)getWritableDatabase().delete("events","id = ?",new String[]{event.getString("event_id")}); }
 synchronized void clearSource(String source) { getWritableDatabase().delete("events","source = ?",new String[]{source}); }
 synchronized void clear() { getWritableDatabase().delete("events",null,null); }
 synchronized int count() { try(Cursor cursor=getReadableDatabase().rawQuery("SELECT COUNT(*) FROM events",null)){return cursor.moveToFirst()?cursor.getInt(0):0;} }
}
