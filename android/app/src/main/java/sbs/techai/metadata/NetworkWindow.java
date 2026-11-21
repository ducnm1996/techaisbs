package sbs.techai.metadata;

import java.util.LinkedHashMap;
import java.util.Map;

/** Bounded metadata deduplication; contains no payload and no persisted traffic log. */
public final class NetworkWindow {
 private final LinkedHashMap<String,Long> entries=new LinkedHashMap<>(256,0.75f,true);
 private long minute=-1;private int accepted;
 public synchronized boolean allow(String key,long now){
  long current=now/60000;if(current!=minute){minute=current;accepted=0;}
  Long previous=entries.get(key);if(previous!=null&&previous==current)return false;
  if(accepted>=120)return false;
  entries.put(key,current);while(entries.size()>2048)entries.remove(entries.keySet().iterator().next());accepted++;return true;
 }
 public synchronized int size(){return entries.size();}
}
