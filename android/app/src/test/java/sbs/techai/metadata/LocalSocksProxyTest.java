package sbs.techai.metadata;
import org.junit.Test;
import static org.junit.Assert.*;
import java.io.*;
import java.net.*;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.*;
import java.util.concurrent.atomic.AtomicInteger;
public class LocalSocksProxyTest {
 private final AtomicInteger protectedSockets=new AtomicInteger();
 private LocalSocksProxy proxy() throws Exception{return new LocalSocksProxy(new LocalSocksProxy.Protector(){public boolean protect(Socket s){protectedSockets.incrementAndGet();return true;}public boolean protect(DatagramSocket s){protectedSockets.incrementAndGet();return true;}},"unit-user","unit-secret");}
 private Socket auth(LocalSocksProxy proxy) throws Exception {Socket client=new Socket("127.0.0.1",proxy.port());client.setSoTimeout(3000);OutputStream out=client.getOutputStream();InputStream in=client.getInputStream();out.write(new byte[]{5,1,2});out.flush();assertArrayEquals(new byte[]{5,2},LocalSocksProxy.bytes(in,2));byte[] user="unit-user".getBytes(StandardCharsets.UTF_8),pass="unit-secret".getBytes(StandardCharsets.UTF_8);out.write(1);out.write(user.length);out.write(user);out.write(pass.length);out.write(pass);out.flush();assertArrayEquals(new byte[]{1,0},LocalSocksProxy.bytes(in,2));return client;}
 @Test(timeout=15000) public void tcpOpaqueBytesAreForwardedWithoutChanges() throws Exception {
  ExecutorService server=Executors.newSingleThreadExecutor();
  try(ServerSocket echo=new ServerSocket(0,5,InetAddress.getByName("127.0.0.1"));LocalSocksProxy proxy=proxy()){
   Future<?> echoTask=server.submit(()->{try(Socket s=echo.accept()){byte[] body=LocalSocksProxy.bytes(s.getInputStream(),6);s.getOutputStream().write(body);s.getOutputStream().flush();}catch(IOException e){throw new RuntimeException(e);}});
   try(Socket client=auth(proxy)){OutputStream out=client.getOutputStream();InputStream in=client.getInputStream();out.write(new byte[]{5,1,0});LocalSocksProxy.writeEndpoint(out,InetAddress.getByName("127.0.0.1"),echo.getLocalPort());out.flush();assertArrayEquals(new byte[]{5,0,0},LocalSocksProxy.bytes(in,3));LocalSocksProxy.readEndpoint(in);byte[] body={0,1,22,3,(byte)255,0};out.write(body);out.flush();assertArrayEquals(body,LocalSocksProxy.bytes(in,6));}
   echoTask.get(5,TimeUnit.SECONDS);assertEquals(1,protectedSockets.get());
  }finally{server.shutdownNow();}
 }
 @Test(timeout=15000) public void udpOpaqueBytesAndAddressAreForwarded() throws Exception {
  ExecutorService server=Executors.newSingleThreadExecutor();
  try(DatagramSocket echo=new DatagramSocket(new InetSocketAddress(InetAddress.getByName("127.0.0.1"),0));LocalSocksProxy proxy=proxy();Socket control=auth(proxy);DatagramSocket client=new DatagramSocket()){
   echo.setSoTimeout(5000);client.setSoTimeout(5000);Future<?> echoTask=server.submit(()->{try{byte[] bytes=new byte[100];DatagramPacket p=new DatagramPacket(bytes,bytes.length);echo.receive(p);echo.send(new DatagramPacket(bytes,p.getLength(),p.getSocketAddress()));}catch(IOException e){throw new RuntimeException(e);}});
   OutputStream out=control.getOutputStream();InputStream in=control.getInputStream();out.write(new byte[]{5,3,0});LocalSocksProxy.writeEndpoint(out,InetAddress.getByName("0.0.0.0"),0);out.flush();assertArrayEquals(new byte[]{5,0,0},LocalSocksProxy.bytes(in,3));InetSocketAddress relay=LocalSocksProxy.readEndpoint(in).address;
   ByteArrayOutputStream frame=new ByteArrayOutputStream();frame.write(new byte[]{0,0,0});LocalSocksProxy.writeEndpoint(frame,InetAddress.getByName("127.0.0.1"),echo.getLocalPort());byte[] body={1,0,(byte)255,22,3};frame.write(body);byte[] packet=frame.toByteArray();client.send(new DatagramPacket(packet,packet.length,relay));
   byte[] response=new byte[100];DatagramPacket received=new DatagramPacket(response,response.length);client.receive(received);ByteArrayInputStream data=new ByteArrayInputStream(response,0,received.getLength());assertArrayEquals(new byte[]{0,0,0},LocalSocksProxy.bytes(data,3));assertEquals(echo.getLocalPort(),LocalSocksProxy.readEndpoint(data).address.getPort());assertArrayEquals(body,LocalSocksProxy.bytes(data,body.length));echoTask.get(5,TimeUnit.SECONDS);assertEquals(1,protectedSockets.get());
  }finally{server.shutdownNow();}
 }
 @Test(timeout=10000) public void proxyRequiresSessionAuthentication() throws Exception {
  try(LocalSocksProxy proxy=proxy();Socket client=new Socket("127.0.0.1",proxy.port())){client.setSoTimeout(3000);client.getOutputStream().write(new byte[]{5,1,0});client.getOutputStream().flush();assertArrayEquals(new byte[]{5,(byte)255},LocalSocksProxy.bytes(client.getInputStream(),2));assertEquals(0,protectedSockets.get());}
 }
 @Test(timeout=15000) public void ipv6TcpAndUdpRelayUseLiteralAddresses() throws Exception {
  InetAddress address=InetAddress.getByName("::1");ExecutorService server=Executors.newFixedThreadPool(2);
  try(ServerSocket tcp=new ServerSocket(0,5,address);DatagramSocket udp=new DatagramSocket(new InetSocketAddress(address,0));LocalSocksProxy proxy=proxy()){
   Future<?> one=server.submit(()->{try(Socket s=tcp.accept()){s.getOutputStream().write(LocalSocksProxy.bytes(s.getInputStream(),3));s.getOutputStream().flush();}catch(IOException e){throw new RuntimeException(e);}});
   try(Socket client=auth(proxy)){OutputStream out=client.getOutputStream();InputStream in=client.getInputStream();out.write(new byte[]{5,1,0});LocalSocksProxy.writeEndpoint(out,address,tcp.getLocalPort());out.flush();assertArrayEquals(new byte[]{5,0,0},LocalSocksProxy.bytes(in,3));LocalSocksProxy.readEndpoint(in);out.write(new byte[]{9,8,7});out.flush();assertArrayEquals(new byte[]{9,8,7},LocalSocksProxy.bytes(in,3));}one.get(5,TimeUnit.SECONDS);
   udp.setSoTimeout(5000);Future<?> two=server.submit(()->{try{byte[] b=new byte[64];DatagramPacket p=new DatagramPacket(b,b.length);udp.receive(p);udp.send(new DatagramPacket(b,p.getLength(),p.getSocketAddress()));}catch(IOException e){throw new RuntimeException(e);}});
   try(Socket control=auth(proxy);DatagramSocket client=new DatagramSocket()){
    client.setSoTimeout(5000);OutputStream out=control.getOutputStream();InputStream in=control.getInputStream();out.write(new byte[]{5,3,0});LocalSocksProxy.writeEndpoint(out,InetAddress.getByName("0.0.0.0"),0);out.flush();assertArrayEquals(new byte[]{5,0,0},LocalSocksProxy.bytes(in,3));InetSocketAddress relay=LocalSocksProxy.readEndpoint(in).address;
    ByteArrayOutputStream frame=new ByteArrayOutputStream();frame.write(new byte[]{0,0,0});LocalSocksProxy.writeEndpoint(frame,address,udp.getLocalPort());frame.write(new byte[]{6,5,4});byte[] bytes=frame.toByteArray();client.send(new DatagramPacket(bytes,bytes.length,relay));DatagramPacket response=new DatagramPacket(new byte[100],100);client.receive(response);ByteArrayInputStream data=new ByteArrayInputStream(response.getData(),0,response.getLength());LocalSocksProxy.bytes(data,3);assertEquals(16,LocalSocksProxy.readEndpoint(data).address.getAddress().getAddress().length);assertArrayEquals(new byte[]{6,5,4},LocalSocksProxy.bytes(data,3));
   }two.get(5,TimeUnit.SECONDS);
  }finally{server.shutdownNow();}
 }
 @Test(timeout=10000) public void unprotectedSocketIsNotAllowedToConnect() throws Exception {
  try(LocalSocksProxy proxy=new LocalSocksProxy(new LocalSocksProxy.Protector(){public boolean protect(Socket s){return false;}public boolean protect(DatagramSocket s){return false;}},"unit-user","unit-secret");Socket client=auth(proxy)){
   OutputStream out=client.getOutputStream();InputStream in=client.getInputStream();out.write(new byte[]{5,1,0});LocalSocksProxy.writeEndpoint(out,InetAddress.getByName("127.0.0.1"),9);out.flush();assertArrayEquals(new byte[]{5,5,0},LocalSocksProxy.bytes(in,3));
  }
 }
}
