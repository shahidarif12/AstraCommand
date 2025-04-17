package com.astra.c2client;

public class Config {
    // Server configuration
    // IMPORTANT: Change this URL to your actual server domain when deploying
    // For Hostinger deployment use: https://your-domain.com/c2
    public static final String SERVER_URL = "http://your-domain.com/c2";
    
    // API endpoints
    public static final String ENDPOINT_REGISTER = "/api/device_register.php";
    public static final String ENDPOINT_HEARTBEAT = "/api/heartbeat.php";
    public static final String ENDPOINT_GET_COMMAND = "/api/get_command.php";
    public static final String ENDPOINT_SEND_LOG = "/api/send_log.php";
    
    // Shared preferences
    public static final String PREFS_NAME = "AstraC2Prefs";
    public static final String PREF_DEVICE_ID = "device_id";
    public static final String PREF_AUTH_TOKEN = "auth_token";
    public static final String PREF_LAST_HEARTBEAT = "last_heartbeat";
    public static final String PREF_SERVICES_RUNNING = "services_running";
    
    // Broadcast actions
    public static final String ACTION_LOG_MESSAGE = "com.astra.c2client.LOG_MESSAGE";
    public static final String EXTRA_LOG_MESSAGE = "log_message";
}
