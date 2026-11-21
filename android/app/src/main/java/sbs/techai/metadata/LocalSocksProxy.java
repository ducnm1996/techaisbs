package sbs.techai.metadata;

import java.io.*;
import java.net.*;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.*;
import java.util.concurrent.*;
import java.util.concurrent.atomic.AtomicBoolean;
import java.util.concurrent.atomic.AtomicReference;

/** Loopback-only RFC 1928 relay. Bytes are forwarded, never parsed as application content. */
final class LocalSocksProxy implements AutoCloseable {
 interface Protector { boolean protect(Socket socket); boolean protect(DatagramSocket socket); }
 private final Protector protector;
 private final byte[] username,password;
 private final ServerSocket listener;
 private final Set<Closeable> open=ConcurrentHashMap.newKeySet();
 private final Semaphore sessions=new Semaphore(64);
 private final ExecutorService workers=new ThreadPoolExecutor(0,194,30,TimeUnit.SECONDS,new SynchronousQueue<>(),r->{Thread t=new Thread(null,r,"techai-local-relay",262144);t.setDaemon(true);return t;},new ThreadPoolExecutor.AbortPolicy());
 private final AtomicBoolean closed=new AtomicBoolean();
 LocalSocksProxy(Protector protector,String username,String password) throws IOException {
  this.protector=protector;this.username=username.getBytes(StandardCharsets.UTF_8);this.password=password.getBytes(StandardCharsets.UTF_8);
  listener=new ServerSocket();listener.bind(new InetSocketAddress(InetAddress.getByName("127.0.0.1"),0));open.add(listener);
  workers.execute(this::accept);
 }
 int port(){return listener.getLocalPort();}
 private void accept(){while(!closed.get())try{
  Socket client=listener.accept();client.setSoTimeout(10000);client.setTcpNoDelay(true);
  if(!sessions.tryAcquire()){client.close();continue;}
  open.add(client);
  try{workers.execute(()->{try{serve(client);}catch(IOException|RuntimeException ignored){}finally{closeOne(client);sessions.release();}});}catch(RejectedExecutionException error){closeOne(client);sessions.release();}
 }catch(IOException error){if(!closed.get())close();break;}}
 private void serve(Socket client) throws IOException {
  InputStream input=client.getInputStream();OutputStream output=client.getOutputStream();
  if(read(input)!=5)return;int count=read(input);boolean auth=false;for(int i=0;i<count;i++)auth|=read(input)==2;
  output.write(new byte[]{5,(byte)(auth?2:255)});output.flush();if(!auth)return;
  if(read(input)!=1)return;byte[] user=bytes(input,read(input)),pass=bytes(input,read(input));
  boolean valid=MessageDigest.isEqual(user,username)&MessageDigest.isEqual(pass,password);Arrays.fill(pass,(byte)0);
  output.write(new byte[]{1,(byte)(valid?0:1)});output.flush();if(!valid)return;
  if(read(input)!=5)return;int command=read(input);if(read(input)!=0)return;Endpoint endpoint=readEndpoint(input);
  if(command==1)tcp(client,input,output,endpoint);
  else if(command==3)udp(client,input,output);
  else reply(output,7,InetAddress.getLoopbackAddress(),0);
 }
 private void tcp(Socket client,InputStream input,OutputStream output,Endpoint endpoint) throws IOException {
  Socket remote=new Socket();open.add(remote);boolean replied=false;
  try{
   remote.bind(new InetSocketAddress(0));if(!protector.protect(remote))throw new IOException("Protect failed");
   remote.connect(endpoint.address,15000);remote.setTcpNoDelay(true);remote.setKeepAlive(true);remote.setSoTimeout(300000);client.setSoTimeout(300000);
   reply(output,0,remote.getLocalAddress(),remote.getLocalPort());replied=true;
   CountDownLatch finished=new CountDownLatch(1);
   workers.execute(()->{try{copy(remote.getInputStream(),output);try{client.shutdownOutput();}catch(IOException ignored){}}catch(IOException ignored){closeOne(client);closeOne(remote);}finally{finished.countDown();}});
   try{copy(input,remote.getOutputStream());try{remote.shutdownOutput();}catch(IOException ignored){}finished.await(300,TimeUnit.SECONDS);}catch(InterruptedException error){Thread.currentThread().interrupt();}
  }catch(IOException|RejectedExecutionException error){try{if(!replied)reply(output,5,InetAddress.getLoopbackAddress(),0);}catch(IOException ignored){}throw new IOException("Relay unavailable");}
  finally{closeOne(remote);}
 }
 private void udp(Socket client,InputStream input,OutputStream output) throws IOException {
  DatagramSocket relay=new DatagramSocket(new InetSocketAddress(InetAddress.getByName("127.0.0.1"),0));
  DatagramSocket upstream=new DatagramSocket();open.add(relay);open.add(upstream);
  AtomicBoolean done=new AtomicBoolean();AtomicReference<InetSocketAddress> peer=new AtomicReference<>();Set<InetSocketAddress> destinations=Collections.synchronizedSet(new LinkedHashSet<>());
  Runnable stop=()->{if(done.compareAndSet(false,true)){closeOne(relay);closeOne(upstream);closeOne(client);}};
  try{
   if(!protector.protect(upstream))throw new IOException("Protect failed");
   relay.setSoTimeout(120000);upstream.setSoTimeout(120000);client.setSoTimeout(0);reply(output,0,relay.getLocalAddress(),relay.getLocalPort());
   workers.execute(()->{byte[] buffer=new byte[65535];try{while(!done.get()){
    DatagramPacket packet=new DatagramPacket(buffer,buffer.length);relay.receive(packet);InetSocketAddress from=(InetSocketAddress)packet.getSocketAddress();if(!from.getAddress().isLoopbackAddress())continue;
    peer.compareAndSet(null,from);if(!peer.get().equals(from))continue;
    ByteArrayInputStream frame=new ByteArrayInputStream(buffer,0,packet.getLength());if(read(frame)!=0||read(frame)!=0||read(frame)!=0)continue;
    Endpoint target;try{target=readEndpoint(frame);}catch(IOException invalid){continue;}
    if(target.address.getPort()==0||target.address.getAddress().isAnyLocalAddress()||target.address.getAddress().isMulticastAddress())continue;
    synchronized(destinations){if(destinations.size()>=256&&!destinations.contains(target.address))continue;destinations.add(target.address);}
    int length=frame.available(),offset=packet.getLength()-length;upstream.send(new DatagramPacket(buffer,offset,length,target.address));
   }}catch(IOException ignored){}finally{Arrays.fill(buffer,(byte)0);stop.run();}});
   workers.execute(()->{byte[] buffer=new byte[65535];try{while(!done.get()){
    DatagramPacket packet=new DatagramPacket(buffer,buffer.length);upstream.receive(packet);InetSocketAddress from=(InetSocketAddress)packet.getSocketAddress();InetSocketAddress destination=peer.get();
    if(destination==null||!destinations.contains(from))continue;
    ByteArrayOutputStream frame=new ByteArrayOutputStream(packet.getLength()+22);frame.write(new byte[]{0,0,0});writeEndpoint(frame,from.getAddress(),from.getPort());frame.write(buffer,0,packet.getLength());byte[] data=frame.toByteArray();
    try{relay.send(new DatagramPacket(data,data.length,destination));}finally{Arrays.fill(data,(byte)0);}
   }}catch(IOException ignored){}finally{Arrays.fill(buffer,(byte)0);stop.run();}});
   while(input.read()!=-1&&!done.get()){} // RFC 1928: control TCP connection owns UDP lifetime.
  }finally{stop.run();}
 }
 private static void copy(InputStream input,OutputStream output) throws IOException {byte[] buffer=new byte[16384];try{int count;while((count=input.read(buffer))!=-1){output.write(buffer,0,count);output.flush();}}finally{Arrays.fill(buffer,(byte)0);}}
 static final class Endpoint {final InetSocketAddress address;Endpoint(InetSocketAddress address){this.address=address;}}
 static Endpoint readEndpoint(InputStream input) throws IOException {
  int type=read(input);if(type!=1&&type!=4)throw new IOException("Literal IP required");
  InetAddress address=InetAddress.getByAddress(bytes(input,type==1?4:16));int port=(read(input)<<8)|read(input);
  return new Endpoint(new InetSocketAddress(address,port));
 }
 static void writeEndpoint(OutputStream output,InetAddress address,int port) throws IOException {byte[] ip=address.getAddress();output.write(ip.length==4?1:4);output.write(ip);output.write(port>>>8);output.write(port&255);}
 private static void reply(OutputStream output,int status,InetAddress address,int port) throws IOException {output.write(new byte[]{5,(byte)status,0});writeEndpoint(output,address,port);output.flush();}
 static int read(InputStream input) throws IOException {int value=input.read();if(value<0)throw new EOFException();return value;}
 static byte[] bytes(InputStream input,int count) throws IOException {byte[] result=new byte[count];int offset=0;while(offset<count){int read=input.read(result,offset,count-offset);if(read<0)throw new EOFException();offset+=read;}return result;}
 private void closeOne(Closeable resource){open.remove(resource);try{resource.close();}catch(IOException ignored){}}
 @Override public void close(){if(closed.compareAndSet(false,true)){for(Closeable resource:open)closeOne(resource);workers.shutdownNow();}}
}
