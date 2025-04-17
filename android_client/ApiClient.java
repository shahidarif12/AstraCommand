package com.astra.c2client;

import android.util.Log;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Map;

public class ApiClient {
    private static final String TAG = "AstraC2-ApiClient";
    
    /**
     * Send a POST request to the specified endpoint with the given parameters
     * 
     * @param endpoint The API endpoint to call
     * @param params The parameters to send in the request
     * @return The JSON response from the server
     * @throws IOException If a connection error occurs
     * @throws JSONException If the response is not valid JSON
     */
    public static JSONObject sendPostRequest(String endpoint, Map<String, String> params) throws IOException, JSONException {
        URL url = new URL(Config.SERVER_URL + endpoint);
        HttpURLConnection connection = (HttpURLConnection) url.openConnection();
        
        try {
            // Set up the connection
            connection.setRequestMethod("POST");
            connection.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
            connection.setRequestProperty("User-Agent", "AstraC2Client/1.0");
            connection.setDoOutput(true);
            connection.setConnectTimeout(15000);
            connection.setReadTimeout(15000);
            
            // Build the request body
            StringBuilder postData = new StringBuilder();
            for (Map.Entry<String, String> param : params.entrySet()) {
                if (postData.length() != 0) {
                    postData.append('&');
                }
                postData.append(urlEncode(param.getKey()));
                postData.append('=');
                postData.append(urlEncode(param.getValue()));
            }
            
            // Write the request body
            try (OutputStream os = connection.getOutputStream()) {
                byte[] input = postData.toString().getBytes(StandardCharsets.UTF_8);
                os.write(input, 0, input.length);
            }
            
            // Read the response
            int responseCode = connection.getResponseCode();
            
            if (responseCode == HttpURLConnection.HTTP_OK) {
                BufferedReader in = new BufferedReader(new InputStreamReader(connection.getInputStream()));
                StringBuilder response = new StringBuilder();
                String inputLine;
                
                while ((inputLine = in.readLine()) != null) {
                    response.append(inputLine);
                }
                in.close();
                
                // Log the response for debugging
                String responseString = response.toString();
                Log.d(TAG, "Response: " + responseString);
                
                // Parse the response as JSON
                return new JSONObject(responseString);
            } else {
                throw new IOException("Server returned HTTP " + responseCode);
            }
        } finally {
            connection.disconnect();
        }
    }
    
    /**
     * URL encode a string for form data
     * 
     * @param value The string to encode
     * @return The URL encoded string
     */
    private static String urlEncode(String value) {
        try {
            return java.net.URLEncoder.encode(value, StandardCharsets.UTF_8.name());
        } catch (Exception e) {
            return value;
        }
    }
}
