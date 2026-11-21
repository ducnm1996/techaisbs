package sbs.techai.metadata;

import org.json.JSONObject;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.net.URL;
import javax.net.ssl.HttpsURLConnection;

final class ApiClient {
 static final String BASE="https://techai.sbs";
 static final class Reply {
  final int status; final JSONObject json;
  Reply(int status,JSONObject json){this.status=status;this.json=json;}
 }
 static Reply post(String path,JSONObject body,String token) throws Exception {
  byte[] bytes=body.toString().getBytes(StandardCharsets.UTF_8);if(bytes.length>65536)throw new IllegalArgumentException("Batch too large");
  HttpsURLConnection connection=(HttpsURLConnection)new URL(BASE+path).openConnection();
  try {
   connection.setInstanceFollowRedirects(false);connection.setRequestMethod("POST");connection.setConnectTimeout(15000);connection.setReadTimeout(20000);connection.setDoOutput(true);
   connection.setRequestProperty("Content-Type","application/json; charset=utf-8");connection.setRequestProperty("Accept","application/json");connection.setRequestProperty("User-Agent","TechAI-Agent/"+BuildConfig.VERSION_NAME+" Android");
   if(token!=null)connection.setRequestProperty("Authorization","Bearer "+token);
   connection.setFixedLengthStreamingMode(bytes.length);
   try(java.io.OutputStream output=connection.getOutputStream()){output.write(bytes);}
   int status=connection.getResponseCode();
   InputStream stream=status>=400?connection.getErrorStream():connection.getInputStream();ByteArrayOutputStream response=new ByteArrayOutputStream();
   if(stream!=null)try(InputStream input=stream){byte[] buffer=new byte[4096];int read;while((read=input.read(buffer))!=-1){if(response.size()+read>65536)throw new IllegalStateException("Response too large");response.write(buffer,0,read);}}
   JSONObject json;try {json=new JSONObject(response.toString(StandardCharsets.UTF_8.name()));}catch(Exception ignored){json=new JSONObject();}
   return new Reply(status,json);
  } finally { connection.disconnect(); }
 }
}
