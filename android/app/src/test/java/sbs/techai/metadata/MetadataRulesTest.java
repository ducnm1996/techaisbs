package sbs.techai.metadata;

import org.junit.Test;
import static org.junit.Assert.*;

public class MetadataRulesTest {
 @Test public void onlyExplicitPackagesAreMapped(){
  assertEquals("zalo",MetadataRules.sourceForPackage("com.zing.zalo"));
  assertEquals("viber",MetadataRules.sourceForPackage("com.viber.voip"));
  assertEquals("email",MetadataRules.sourceForPackage("com.google.android.gm"));
  assertEquals("email",MetadataRules.sourceForPackage("com.samsung.android.email.provider"));
  assertEquals("sms",MetadataRules.sourceForPackage("com.samsung.android.messaging"));
  assertEquals("call",MetadataRules.sourceForPackage("com.samsung.android.dialer"));
  assertNull(MetadataRules.sourceForPackage("com.zing.zalo.fake"));assertNull(MetadataRules.sourceForPackage(null));
 }
 @Test public void retryKeepsIdentityButDeviceAndSourceAreSeparated(){
  String id=MetadataRules.eventId("install-a","sms","notification-1",1000);
  assertEquals(id,MetadataRules.eventId("install-a","sms","notification-1",1000));
  assertNotEquals(id,MetadataRules.eventId("install-b","sms","notification-1",1000));
  assertNotEquals(id,MetadataRules.eventId("install-a","email","notification-1",1000));
  assertNotEquals(id,MetadataRules.eventId("install-a","sms","notification-1",1001));
  assertEquals(id,java.util.UUID.fromString(id).toString());
 }
 @Test public void phoneMustBeInternationalAndCannotContainContent(){
  assertEquals("+84901234567",MetadataRules.canonicalPhone("+84 (90) 123-4567"));
  assertNull(MetadataRules.canonicalPhone("0901234567"));assertNull(MetadataRules.canonicalPhone("Bank OTP 123456"));
  assertNull(MetadataRules.canonicalPhone("+1234567890123456"));assertNull(MetadataRules.canonicalPhone(null));
 }
}
