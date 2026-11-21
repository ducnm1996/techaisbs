package sbs.techai.metadata;

import android.content.Context;
import android.net.ConnectivityManager;
import java.net.InetSocketAddress;
import java.time.Instant;
import java.util.*;
import java.util.concurrent.*;
import org.json.JSONObject;

final class NetworkObserver implements AutoCloseable {
 private final Context context;private final Map<Integer,String> owners;
 private final String session;
 private final NetworkWindow endpoints=new NetworkWindow();
 private final LinkedHashMap<String,Long> lookups=new LinkedHashMap<>();
 private final ThreadPoolExecutor io=new ThreadPoolExecutor(1,1,0,TimeUnit.SECONDS,new ArrayBlockingQueue<>(128),r->{Thread t=new Thread(r,"techai-network-metadata");t.setDaemon(true);return t;},new ThreadPoolExecutor.DiscardPolicy());
 private volatile boolean closed;
 NetworkObserver(Context context,Map<Integer,String> owners){this.context=context.getApplicationContext();this.owners=new HashMap<>(owners);session=new AppState(context).sessionId();}
 void observe(PacketHeader header){if(!closed&&header!=null&&header.connectionStart)io.execute(()->record(header));}
 private void record(PacketHeader header){
  AppState state=new AppState(context);if(closed||!session.equals(state.sessionId())||!state.vpnRequested()||!state.accepts("network")||!NetworkVpnService.active()||!StatusNotice.permitted(context))return;
  long now=System.currentTimeMillis();String tuple=header.tuple();Long until=lookups.get(tuple);if(until!=null&&until>now)return;
  lookups.put(tuple,now+2000);while(lookups.size()>2048)lookups.remove(lookups.keySet().iterator().next());
  try{
   ConnectivityManager manager=context.getSystemService(ConnectivityManager.class);
   int uid=manager.getConnectionOwnerUid(header.protocol,new InetSocketAddress(header.source,header.sourcePort),new InetSocketAddress(header.destination,header.destinationPort));
   String pkg=owners.get(uid);if(pkg==null)return;String source=MetadataRules.sourceForPackage(pkg);
   if(!state.accepts(source))return;lookups.put(tuple,now+60000);
   String protocol=header.protocol==6?"tcp":"udp";
   String identity=pkg+"|"+header.destination.getHostAddress()+"|"+header.destinationPort+"|"+protocol;
   if(!endpoints.allow(identity,now))return;
   JSONObject metadata=new JSONObject().put("app_source",source).put("destination_ip",header.destination.getHostAddress()).put("destination_port",header.destinationPort).put("protocol",protocol);
   JSONObject event=new JSONObject().put("event_id",MetadataRules.eventId(state.installId(),"network",identity,(now/60000)*60000)).put("source","network").put("occurred_at",Instant.ofEpochMilli(now).toString()).put("app_package",pkg).put("network_metadata",metadata);
   // No raw packets are handed to the queue; consent is rechecked immediately before saving.
   if(!closed&&state.vpnRequested()&&state.accepts("network")&&state.accepts(source)&&now>=state.since("network")&&now>=state.since(source)){if(Capture.queue(context).enqueue(event,session))SyncControl.sync(context);}
  }catch(Exception ignored){/* Unattributable connections are skipped, never guessed. */}
 }
 @Override public void close(){closed=true;io.shutdownNow();}
}
