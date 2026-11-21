package sbs.techai.metadata;

/** Pure rules: no coordinates are reused after five minutes or from before consent. */
public final class AgentRules {
 private AgentRules(){}
 public static boolean freshLocation(boolean consent,boolean portalAllows,long consentTime,long fixTime,long ageMillis){
  return consent&&portalAllows&&fixTime>=consentTime&&ageMillis>=0&&ageMillis<=300000;
 }
 public static boolean snapshotDue(long last,long now){return last==0||now<last||now-last>=900000;}
}
