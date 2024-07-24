#include <WiFi.h>
#include <WebServer.h>
#include <ArduinoJson.h>

// Replace with your network credentials
const char* ssid = "Toji";
const char* password = "toji2992";

WebServer server(80);

void setup() {
  Serial.begin(115200); // For debugging with the serial monitor
  Serial2.begin(115200, SERIAL_8N1, 16, 17); // TX to RX of Mega, RX to TX of Mega

  // Connect to Wi-Fi
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  Serial.println("Connected to WiFi");

  // Route to handle POST requests to /submitPin
  server.on("/submitPin", HTTP_POST, handlePostRequest);

  // Route to handle OPTIONS requests for CORS preflight
  server.on("/submitPin", HTTP_OPTIONS, handleOptionsRequest);

  // Route to handle POST requests to /resetSystem
  server.on("/resetSystem", HTTP_POST, handleResetRequest);

  // Route to handle OPTIONS requests for CORS preflight
  server.on("/resetSystem", HTTP_OPTIONS, handleOptionsRequest);

  // Route to handle GET requests to /getRemainingTime
  server.on("/getRemainingTime", HTTP_GET, handleGetRemainingTimeRequest);

  // Start server
  server.begin();
}

void handlePostRequest() {
  // Add CORS headers
  addCORSHeaders();
  
  // Read the request body
  String body = server.arg("plain");
  DynamicJsonDocument doc(200);
  deserializeJson(doc, body);

  // Extract the PIN from the request body
  const char* receivedPin = doc["pin"];

  // Print the received PIN to the Serial Monitor
  Serial.println(receivedPin);

  // Send the PIN to the Arduino Mega
  Serial2.print(receivedPin);
  Serial2.print("\n");
  Serial.println("PIN sent to Arduino Mega");

  // Respond with a success message
  String response = "{\"success\":true}";
  server.send(200, "application/json", response);
}

void handleResetRequest() {
  // Add CORS headers
  addCORSHeaders();

  // Print reset message to Serial Monitor
  Serial.println("Reset request received");

  // Send reset command to Arduino Mega
  Serial2.print("RESET\n");
  Serial.println("Reset command sent to Arduino Mega");

  // Respond with a success message
  String response = "{\"success\":true}";
  server.send(200, "application/json", response);
}

void handleGetRemainingTimeRequest() {
  // Add CORS headers
  addCORSHeaders();

  // Request remaining time from Arduino Mega
  Serial2.print("GET_TIME\n");

  // Wait for the response from Arduino Mega
  String remainingTime = "";
  unsigned long startMillis = millis();
  while ((millis() - startMillis) < 5000) {  // Wait for up to 5 seconds
    if (Serial2.available() > 0) {
      remainingTime = Serial2.readStringUntil('\n');
      break;
    }
  }
  if (remainingTime == "") {
    Serial.println("Timeout waiting for response from Arduino Mega");
    server.send(500, "application/json", "{\"error\":\"Timeout waiting for response from Arduino Mega\"}");
    return;
  }

  Serial.println("Received remaining time from Arduino Mega: " + remainingTime);

  // Parse the remaining time
  DynamicJsonDocument doc(200);
  deserializeJson(doc, remainingTime);
  int hours = doc["hours"];
  int minutes = doc["minutes"];
  int seconds = doc["seconds"];

  // Respond with the remaining time
  String response = "{\"hours\":" + String(hours) + ",\"minutes\":" + String(minutes) + ",\"seconds\":" + String(seconds) + "}";
  server.send(200, "application/json", response);
}

void handleOptionsRequest() {
  // Add CORS headers
  addCORSHeaders();
  server.send(204); // No content for OPTIONS request
}

void addCORSHeaders() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "POST, GET, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");
}

void loop() {
  server.handleClient();
}
