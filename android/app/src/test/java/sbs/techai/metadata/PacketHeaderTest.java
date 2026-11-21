package sbs.techai.metadata;
import org.junit.Test;
import static org.junit.Assert.*;
import java.net.*;
import java.util.Arrays;
public class PacketHeaderTest {
 private byte[] ipv4(int protocol,int payload){byte[] p=new byte[20+(protocol==6?20:8)+payload];p[0]=0x45;p[2]=(byte)(p.length>>>8);p[3]=(byte)p.length;p[9]=(byte)protocol;p[12]=(byte)192;p[13]=0;p[14]=2;p[15]=10;p[16]=(byte)203;p[17]=0;p[18]=113;p[19]=20;p[20]=0x30;p[21]=0x39;p[22]=1;p[23]=(byte)187;if(protocol==6){p[32]=0x50;p[33]=2;}else{int n=8+payload;p[24]=(byte)(n>>>8);p[25]=(byte)n;}return p;}
 @Test public void ipv4TcpAndUdpExposeOnlyEndpointHeaders(){byte[] p=ipv4(6,20);PacketHeader h=PacketHeader.parse(p,p.length);assertNotNull(h);assertEquals("203.0.113.20",h.destination.getHostAddress());assertEquals(443,h.destinationPort);assertEquals(12345,h.sourcePort);assertTrue(h.connectionStart);Arrays.fill(p,40,p.length,(byte)99);assertEquals(h.tuple(),PacketHeader.parse(p,p.length).tuple());p[33]=16;assertFalse(PacketHeader.parse(p,p.length).connectionStart);p=ipv4(17,10);assertEquals(17,PacketHeader.parse(p,p.length).protocol);}
 @Test public void ipv6AndExtensionHeadersAreSupported(){byte[] p=new byte[56];p[0]=0x60;p[5]=16;p[6]=0;p[8]=0x20;p[9]=1;p[24]=0x20;p[25]=1;p[39]=2;p[40]=17;p[41]=0;p[48]=0x30;p[49]=0x39;p[50]=1;p[51]=(byte)187;p[53]=8;PacketHeader h=PacketHeader.parse(p,p.length);assertNotNull(h);assertEquals(16,h.destination.getAddress().length);assertEquals(443,h.destinationPort);p[6]=44;assertNull(PacketHeader.parse(p,p.length));}
 @Test public void malformedTruncatedAndFragmentsAreSkipped(){byte[] p=ipv4(17,8);for(int n=0;n<p.length;n++)assertNull(PacketHeader.parse(p,n));p[6]=0x20;assertNull(PacketHeader.parse(p,p.length));p[6]=0;p[25]=1;assertNull(PacketHeader.parse(p,p.length));assertNull(PacketHeader.parse(null,0));assertNull(PacketHeader.parse(new byte[]{0x45},100));}
 @Test public void dedupAndRateLimitStayBounded(){NetworkWindow window=new NetworkWindow();assertTrue(window.allow("same",60000));assertFalse(window.allow("same",60001));for(int i=1;i<120;i++)assertTrue(window.allow("e"+i,60000));assertFalse(window.allow("overflow",60000));assertTrue(window.allow("same",120000));for(int i=0;i<3000;i++)assertTrue(window.allow("new"+i,180000L+i*60000L));assertTrue(window.size()<=2048);}
}
