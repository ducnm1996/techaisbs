package sbs.techai.metadata;

import android.content.Context;
import android.content.SharedPreferences;
import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.util.Base64;
import java.nio.charset.StandardCharsets;
import java.security.GeneralSecurityException;
import java.security.KeyStore;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

final class SecureStore {
 private static final String ALIAS="techai.metadata.private.v1";
 private final SharedPreferences preferences;
 SecureStore(Context context) { preferences=context.getSharedPreferences("secret_token",Context.MODE_PRIVATE); }
 private static synchronized SecretKey key() throws GeneralSecurityException, java.io.IOException {
  KeyStore store=KeyStore.getInstance("AndroidKeyStore");store.load(null);
  if(store.containsAlias(ALIAS))return (SecretKey)store.getKey(ALIAS,null);
  KeyGenerator generator=KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES,"AndroidKeyStore");
  generator.init(new KeyGenParameterSpec.Builder(ALIAS,KeyProperties.PURPOSE_ENCRYPT|KeyProperties.PURPOSE_DECRYPT).setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).setRandomizedEncryptionRequired(true).build());
  return generator.generateKey();
 }
 static String encrypt(String plain, String purpose) throws Exception {
  Cipher cipher=Cipher.getInstance("AES/GCM/NoPadding");cipher.init(Cipher.ENCRYPT_MODE,key());cipher.updateAAD(purpose.getBytes(StandardCharsets.UTF_8));
  return Base64.encodeToString(cipher.getIV(),Base64.NO_WRAP)+":"+Base64.encodeToString(cipher.doFinal(plain.getBytes(StandardCharsets.UTF_8)),Base64.NO_WRAP);
 }
 static String decrypt(String value, String purpose) throws Exception {
  String[] parts=value.split(":",2);if(parts.length!=2)throw new GeneralSecurityException("Invalid encrypted record");
  Cipher cipher=Cipher.getInstance("AES/GCM/NoPadding");cipher.init(Cipher.DECRYPT_MODE,key(),new GCMParameterSpec(128,Base64.decode(parts[0],Base64.NO_WRAP)));cipher.updateAAD(purpose.getBytes(StandardCharsets.UTF_8));
  return new String(cipher.doFinal(Base64.decode(parts[1],Base64.NO_WRAP)),StandardCharsets.UTF_8);
 }
 void token(String token) throws Exception { if(!preferences.edit().putString("token",encrypt(token,"token")).commit())throw new GeneralSecurityException("Token not saved"); }
 String token() throws Exception { String token=preferences.getString("token",null);return token==null?null:decrypt(token,"token"); }
 boolean paired() { return preferences.contains("token"); }
 void clear() { preferences.edit().clear().commit(); }
}
