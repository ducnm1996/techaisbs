package sbs.techai.metadata;

import java.net.InetAddress;
import java.util.Arrays;

/** Reads only network/transport headers. No payload, DNS names or TLS fields are returned. */
public final class PacketHeader {
 public final InetAddress source,destination;public final int sourcePort,destinationPort,protocol;public final boolean connectionStart;
 private PacketHeader(byte[] source,byte[] destination,int sp,int dp,int protocol,boolean start) throws Exception {this.source=InetAddress.getByAddress(source);this.destination=InetAddress.getByAddress(destination);sourcePort=sp;destinationPort=dp;this.protocol=protocol;connectionStart=start;}
 private static int u16(byte[] data,int at){return ((data[at]&255)<<8)|(data[at+1]&255);}
 public static PacketHeader parse(byte[] data,int length){
  if(data==null||length<1||length>data.length)return null;
  try{
   int version=(data[0]&255)>>>4,at,protocol,end;byte[] source,destination;
   if(version==4){
    if(length<20)return null;at=(data[0]&15)*4;end=u16(data,2);if(at<20||at>length||end<at||end>length||(u16(data,6)&0x3fff)!=0)return null;
    protocol=data[9]&255;source=Arrays.copyOfRange(data,12,16);destination=Arrays.copyOfRange(data,16,20);
   }else if(version==6){
    if(length<40)return null;end=40+u16(data,4);if(end>length||end==40)return null;protocol=data[6]&255;at=40;source=Arrays.copyOfRange(data,8,24);destination=Arrays.copyOfRange(data,24,40);
    for(int extensions=0;protocol!=6&&protocol!=17&&extensions<8;extensions++){
     if(protocol==0||protocol==43||protocol==60){if(at+2>end)return null;int next=data[at]&255,size=((data[at+1]&255)+1)*8;if(at+size>end)return null;at+=size;protocol=next;}
     else return null; // Fragment/ESP/AH/unknown headers are forwarded without recording.
    }
   }else return null;
   if(protocol!=6&&protocol!=17)return null;
   int minimum=protocol==6?20:8;if(at+minimum>end)return null;int sp=u16(data,at),dp=u16(data,at+2);if(dp==0)return null;
   if(protocol==6){int header=((data[at+12]&255)>>>4)*4;if(header<20||at+header>end)return null;int flags=data[at+13]&255;return new PacketHeader(source,destination,sp,dp,protocol,(flags&2)!=0&&(flags&16)==0);}
   int udpLength=u16(data,at+4);if(udpLength<8||at+udpLength>end)return null;
   return new PacketHeader(source,destination,sp,dp,protocol,true);
  }catch(Exception ignored){return null;}
 }
 public String tuple(){return protocol+"|"+source.getHostAddress()+"|"+sourcePort+"|"+destination.getHostAddress()+"|"+destinationPort;}
}
