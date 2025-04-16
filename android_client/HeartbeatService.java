package com.astra.c2client;

import android.app.Service;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.IBinder;
import android.util.Log;

import androidx.annotation.Nullable;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.HashMap;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class HeartbeatService extends Service {
    private static final String TAG = "AstraC2-Heartbeat";
    private static final int HEARTBEAT_INTERVAL_SECONDS = 300; // 5 minutes
    
    private ScheduledExecutorService scheduler;
    private SharedPreferences sharedPreferences;
    private boolean isRunning = false;
    
    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "HeartbeatService created");
        scheduler = Executors.newSingleThreadScheduledExecutor();
        sharedPreferences = getSharedPreferences(Config.PREFS_NAME, MODE_PRIVATE);
    }
    
    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "HeartbeatService started");
        
        if (!isRunning) {
            startHeartbeat();
            isRunning = true;
        }
        
        // If service is killed, it will be restarted with last intent
        return START_STICKY;
    }
    
    private void startHeartbeat() {
        // Schedule the heartbeat task
        scheduler.scheduleAtFixedRate(
            this::sendHeartbeat,
            0,  // start immediately
            HEARTBEAT_INTERVAL_SECONDS,
            TimeUnit.SECONDS
        );
        
        logToMain("Heartbeat service started, interval: " + HEARTBEAT_INTERVAL_SECONDS + " seconds");
    }
    
    private void sendHeartbeat() {
        try {
            // Check if device is registered
            String deviceId = sharedPreferences.getString(Config.PREF_DEVICE_ID, null);
            String authToken = sharedPreferences.getString(Config.PREF_AUTH_TOKEN, null);
            
            if (deviceId == null || authToken == null) {
                logToMain("Device not registered. Cannot send heartbeat.");
                return;
            }
            
            // Prepare request parameters
            Map<String, String> params = new HashMap<>();
            params.put("device_id", deviceId);
            params.put("auth_token", authToken);
            
            // Send the heartbeat request
            try {
                JSONObject response = ApiClient.sendPostRequest(Config.ENDPOINT_HEARTBEAT, params);
                
                if (response.has("status") && response.getString("status").equals("success")) {
                    // Update last heartbeat time
                    String timestamp = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(new Date());
                    sharedPreferences.edit().putString(Config.PREF_LAST_HEARTBEAT, timestamp).apply();
                    
                    Log.d(TAG, "Heartbeat sent successfully");
                } else {
                    logToMain("Heartbeat failed: " + response.optString("error", "Unknown error"));
                }
            } catch (IOException | JSONException e) {
                logToMain("Heartbeat error: " + e.getMessage());
                Log.e(TAG, "Heartbeat error", e);
            }
        } catch (Exception e) {
            Log.e(TAG, "Unexpected error in heartbeat", e);
        }
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
    
    @Override
    public void onDestroy() {
        if (scheduler != null && !scheduler.isShutdown()) {
            scheduler.shutdownNow();
            logToMain("Heartbeat service stopped");
        }
        
        isRunning = false;
        super.onDestroy();
    }
    
    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
