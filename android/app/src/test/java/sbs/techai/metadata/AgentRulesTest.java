package sbs.techai.metadata;
import org.junit.Test;
import static org.junit.Assert.*;
public class AgentRulesTest {
 @Test public void locationRequiresBothConsentsAndFreshFix(){
  assertTrue(AgentRules.freshLocation(true,true,1000,2000,300000));
  assertFalse(AgentRules.freshLocation(false,true,1000,2000,0));
  assertFalse(AgentRules.freshLocation(true,false,1000,2000,0));
  assertFalse(AgentRules.freshLocation(true,true,2000,1000,0));
  assertFalse(AgentRules.freshLocation(true,true,1000,2000,300001));
  assertFalse(AgentRules.freshLocation(true,true,1000,2000,-1));
 }
 @Test public void telemetryIsThrottledButFirstSnapshotAndClockResetWork(){
  assertTrue(AgentRules.snapshotDue(0,100));assertFalse(AgentRules.snapshotDue(1000,900999));
  assertTrue(AgentRules.snapshotDue(1000,901000));assertTrue(AgentRules.snapshotDue(2000,1000));
 }
}
