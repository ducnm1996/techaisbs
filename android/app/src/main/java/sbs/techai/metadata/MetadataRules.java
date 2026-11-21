package sbs.techai.metadata;

import java.nio.charset.StandardCharsets;
import java.util.UUID;

public final class MetadataRules {
 private MetadataRules() {}
 public static String sourceForPackage(String pkg) {
  if ("com.zing.zalo".equals(pkg)) return "zalo";
  if ("com.viber.voip".equals(pkg)) return "viber";
  if ("com.google.android.gm".equals(pkg) || "com.microsoft.office.outlook".equals(pkg) || "com.samsung.android.email.provider".equals(pkg) || "com.android.email".equals(pkg)) return "email";
  if ("com.google.android.apps.messaging".equals(pkg) || "com.samsung.android.messaging".equals(pkg) || "com.android.mms".equals(pkg)) return "sms";
  if ("com.google.android.dialer".equals(pkg) || "com.samsung.android.dialer".equals(pkg) || "com.samsung.android.incallui".equals(pkg) || "com.android.dialer".equals(pkg)) return "call";
  return null;
 }
 public static String eventId(String installation, String source, String identity, long time) {
  return UUID.nameUUIDFromBytes((installation+"|"+source+"|"+identity+"|"+time).getBytes(StandardCharsets.UTF_8)).toString();
 }
 public static String canonicalPhone(String value) {
  if (value == null) return null;
  String normalized=value.replaceAll("[\\s()\\-]", "");
  return normalized.matches("\\+[1-9][0-9]{6,14}") ? normalized : null;
 }
}
