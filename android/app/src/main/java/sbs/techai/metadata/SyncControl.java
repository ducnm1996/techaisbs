package sbs.techai.metadata;

import android.content.Context;
import androidx.work.BackoffPolicy;
import androidx.work.Constraints;
import androidx.work.ExistingPeriodicWorkPolicy;
import androidx.work.ExistingWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.PeriodicWorkRequest;
import androidx.work.WorkManager;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.ThreadLocalRandom;

final class SyncControl {
 static final String TAG="techai_sync";
 private static Constraints constraints(){return new Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build();}
 static void start(Context context){
  new AppState(context).start();
  PeriodicWorkRequest periodic=new PeriodicWorkRequest.Builder(SyncWorker.class,15,TimeUnit.MINUTES).setConstraints(constraints()).setBackoffCriteria(BackoffPolicy.EXPONENTIAL,30,TimeUnit.SECONDS).addTag(TAG).build();
  WorkManager.getInstance(context).enqueueUniquePeriodicWork("techai_periodic",ExistingPeriodicWorkPolicy.UPDATE,periodic);
  StatusNotice.update(context);sync(context);
 }
 static void sync(Context context){
  if(!new AppState(context).enabled())return;
  OneTimeWorkRequest request=new OneTimeWorkRequest.Builder(SyncWorker.class).setConstraints(constraints()).setInitialDelay(ThreadLocalRandom.current().nextInt(0,4),TimeUnit.SECONDS).setBackoffCriteria(BackoffPolicy.EXPONENTIAL,30,TimeUnit.SECONDS).addTag(TAG).build();
  WorkManager.getInstance(context).enqueueUniqueWork("techai_immediate",ExistingWorkPolicy.KEEP,request);
 }
 static void pause(Context context){new AppState(context).enabled(false);NetworkVpnService.stop(context);LocationCaptureService.stop(context);WorkManager.getInstance(context).cancelAllWorkByTag(TAG);StatusNotice.update(context);}
}
