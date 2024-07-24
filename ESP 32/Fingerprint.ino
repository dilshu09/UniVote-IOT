#include <WiFi.h>
#include <WebServer.h>
#include <ArduinoJson.h>

const char* ssid = "Toji";
const char* password = "toji2992";

WebServer server(80);
HardwareSerial SerialMega(2); // RX2, TX2 for communication with Arduino Mega

void setup() {
  Serial.begin(115200);
  SerialMega.begin(115200, SERIAL_8N1, 16, 17); // Adjust RX and TX pins as needed

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  Serial.println("Connected to WiFi");

  server.on("/", handleRoot);
  server.on("/ready", HTTP_OPTIONS, handleCors);
  server.on("/ready", HTTP_POST, handleReady);
  server.on("/register_fingerprint", HTTP_OPTIONS, handleCors);
  server.on("/register_fingerprint", HTTP_POST, handleRegisterFingerprint);
  server.on("/compare_fingerprint", HTTP_OPTIONS, handleCors);
  server.on("/compare_fingerprint", HTTP_POST, handleCompareFingerprint);
  server.on("/delete_fingerprints", HTTP_OPTIONS, handleCors);
  server.on("/delete_fingerprints", HTTP_POST, handleDeleteFingerprints);
  server.on("/cancel", HTTP_OPTIONS, handleCors);
  server.on("/cancel", HTTP_POST, handleCancelOperation);
  server.onNotFound(handleNotFound);

  server.begin();
  Serial.println("HTTP server started");
}

void loop() {
  server.handleClient();
}

void handleRoot() {
  server.send(200, "text/plain", "ESP32 Server");
}

void handleNotFound() {
  server.send(404, "text/plain", "Not Found");
}

void handleCors() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "POST, GET, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");
  server.send(204);
}

void handleReady() {
  DynamicJsonDocument doc(1024);
  deserializeJson(doc, server.arg("plain"));
  const char* message = doc["message"];

  if (strcmp(message, "System is ready") == 0) {
    SerialMega.println("System is ready");
    Serial.println("Sending command to Mega: System is ready");
    sendResponse(true);
  } else {
    sendResponse(false);
  }
}

void handleRegisterFingerprint() {
  DynamicJsonDocument doc(1024);
  deserializeJson(doc, server.arg("plain"));
  const char* nic = doc["nic"];
  int fingerprintID = doc["fingerprintID"];

  // Send the fingerprint ID and NIC to the Mega
  SerialMega.print("REGISTER ");
  SerialMega.print(fingerprintID);
  SerialMega.print(" ");
  SerialMega.println(nic);
  Serial.println("Sending fingerprint ID to Mega: " + String(fingerprintID));

  // Wait for response from Mega
  while (SerialMega.available() == 0) {
    delay(100);
  }

  String response = SerialMega.readStringUntil('\n');
  response.trim();

  DynamicJsonDocument responseDoc(1024);
  if (response.startsWith("REGISTERED:")) {
    // Process the response and update the database
    Serial.println("Fingerprint registration successful");
    responseDoc["success"] = true;
    responseDoc["nic"] = nic;
    responseDoc["fingerprintID"] = fingerprintID;
  } else {
    Serial.println("Fingerprint registration failed");
    responseDoc["success"] = false;
  }

  String jsonResponse;
  serializeJson(responseDoc, jsonResponse);
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.send(200, "application/json", jsonResponse);
}

void handleCompareFingerprint() {
  DynamicJsonDocument doc(1024);
  deserializeJson(doc, server.arg("plain"));
  const char* nic = doc["nic"];

  // Send the compare command to the Mega
  SerialMega.println("COMPARE");
  Serial.println("Sending compare command to Mega");

  // Wait for response from Mega
  while (SerialMega.available() == 0) {
    delay(100);
  }

  String response = SerialMega.readStringUntil('\n');
  response.trim();

  DynamicJsonDocument responseDoc(1024);
  if (response.startsWith("MATCHED:")) {
    int matchedID = response.substring(8).toInt();
    responseDoc["matchedID"] = matchedID;
    Serial.println("Fingerprint matched with ID: " + String(matchedID));
  } else {
    responseDoc["matchedID"] = nullptr;
    Serial.println("No matching fingerprint found");
  }

  String jsonResponse;
  serializeJson(responseDoc, jsonResponse);
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.send(200, "application/json", jsonResponse);
}

void handleDeleteFingerprints() {
  SerialMega.println("DELETE_ALL");
  Serial.println("Sending delete all command to Mega");

  // Wait for response from Mega
  while (SerialMega.available() == 0) {
    delay(100);
  }

  String response = SerialMega.readStringUntil('\n');
  response.trim();

  if (response.startsWith("DELETED_ALL")) {
    Serial.println("All fingerprints deleted successfully");
    sendResponse(true);
  } else {
    Serial.println("Failed to delete fingerprints");
    sendResponse(false);
  }
}

void handleCancelOperation() {
  SerialMega.println("CANCEL");
  Serial.println("Sending cancel command to Mega");

  // Wait for response from Mega
  while (SerialMega.available() == 0) {
    delay(100);
  }

  String response = SerialMega.readStringUntil('\n');
  response.trim();

  if (response.startsWith("CANCELLED")) {
    Serial.println("Operation cancelled successfully");
    sendResponse(true);
  } else {
    Serial.println("Failed to cancel operation");
    sendResponse(false);
  }
}

void sendResponse(bool success) {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  if (success) {
    server.send(200, "application/json", "{\"success\": true}");
  } else {
    server.send(400, "application/json", "{\"success\": false}");
  }
}
