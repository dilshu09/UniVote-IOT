#include <WiFi.h>
#include <HTTPClient.h>
#include <WebServer.h>
#include <ArduinoJson.h>

const char* ssid = "Toji";
const char* password = "toji2992";

WebServer server(80);
HardwareSerial SerialMega(2); // RX2, TX2 for communication with Arduino Mega

const char* serverNameVote = "http://192.168.43.2/rothila/validate-vote.php";
const char* serverNamePin = "http://192.168.43.2/rothila/send-pin.php";

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
  server.on("/disable-voting", HTTP_GET, handleDisableVoting);
  server.on("/disable-voting", HTTP_OPTIONS, handleOptions);
  server.on("/start-voting", HTTP_GET, handleVotingStarted);
  server.on("/start-voting", HTTP_OPTIONS, handleOptions);
  server.on("/send-pin", HTTP_POST, handleSendPin);
  server.on("/send-pin", HTTP_OPTIONS, handleOptions);

  server.on("/submitPin", HTTP_POST, handlePostRequest);
  server.on("/submitPin", HTTP_OPTIONS, handleOptions);
  server.on("/resetSystem", HTTP_POST, handleResetRequest);
  server.on("/resetSystem", HTTP_OPTIONS, handleOptions);
  server.on("/getRemainingTime", HTTP_GET, handleGetRemainingTimeRequest);

  server.on("/scan", HTTP_POST, handleScanCommand);
  server.on("/scan", HTTP_OPTIONS, handleOptions);
  server.on("/scanned", HTTP_POST, handleScannedCommand);
  server.on("/scanned", HTTP_OPTIONS, handleOptions);
  server.on("/clear", HTTP_POST, handleClearCommand);
  server.on("/clear", HTTP_OPTIONS, handleOptions);
  server.on("/scan_face_id", HTTP_POST, handleScanFaceIDCommand);
  server.on("/scan_face_id", HTTP_OPTIONS, handleOptions);
  server.on("/clear_face_id", HTTP_POST, handleClearFaceIDCommand);
  server.on("/clear_face_id", HTTP_OPTIONS, handleOptions);
  server.on("/face_match", HTTP_POST, handleFaceMatchCommand);
  server.on("/face_match", HTTP_OPTIONS, handleOptions);
  server.on("/face_mismatch", HTTP_POST, handleFaceMismatchCommand);
  server.on("/face_mismatch", HTTP_OPTIONS, handleOptions);
  server.on("/face_up", HTTP_POST, handleFaceUpCommand);
  server.on("/face_up", HTTP_OPTIONS, handleOptions);
  server.on("/face_down", HTTP_POST, handleFaceDownCommand);
  server.on("/face_down", HTTP_OPTIONS, handleOptions);
  server.on("/move_left", HTTP_POST, handleMoveLeftCommand);
  server.on("/move_left", HTTP_OPTIONS, handleOptions);
  server.on("/move_right", HTTP_POST, handleMoveRightCommand);
  server.on("/move_right", HTTP_OPTIONS, handleOptions);

  server.on("/ready", HTTP_OPTIONS, handleOptions);
  server.on("/ready", HTTP_POST, handleReady);
  server.on("/register_fingerprint", HTTP_OPTIONS, handleOptions);
  server.on("/register_fingerprint", HTTP_POST, handleRegisterFingerprint);
  server.on("/compare_fingerprint", HTTP_OPTIONS, handleOptions);
  server.on("/compare_fingerprint", HTTP_POST, handleCompareFingerprint);
  server.on("/delete_fingerprints", HTTP_OPTIONS, handleOptions);
  server.on("/delete_fingerprints", HTTP_POST, handleDeleteFingerprints);
  server.on("/cancel", HTTP_OPTIONS, handleOptions);
  server.on("/cancel", HTTP_POST, handleCancelOperation);
  
  server.onNotFound(handleNotFound);

  server.begin();
  Serial.println("Web server started. Waiting for requests...");
}

void loop() {
  server.handleClient();

  if (SerialMega.available()) {
    String receivedNumber = SerialMega.readStringUntil('\n');
    receivedNumber.trim();
    Serial.print("Received 3-digit number from Arduino Mega: ");
    Serial.println(receivedNumber);

    if (receivedNumber.startsWith("DISABLE:")) {
      String disableNumber = receivedNumber.substring(8);
      if (sendNumberToServer(disableNumber)) {
        Serial.println("Vote sent successfully.");
        sendNotification(true, "Vote sent successfully.");
      } else {
        Serial.println("Failed to send vote.");
        sendNotification(false, "Failed to send vote.");
      }
    } else if (receivedNumber.startsWith("VOTE:")) {
      String voteNumber = receivedNumber.substring(5);
      sendPinToBackend(voteNumber.c_str());
    }
  }
}

void handleRoot() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.send(200, "text/plain", "ESP32 is ready");
}

void handleDisableVoting() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  SerialMega.println("DISABLE_VOTING");
  server.send(200, "application/json", "{\"message\":\"Disable Voting signal sent to Arduino Mega\"}");
  sendNotification(true, "Disable Voting signal sent to Arduino Mega");
  Serial.println("Disable Voting signal sent to Arduino Mega.");
}

void handleVotingStarted() {
  StaticJsonDocument<200> doc;
  doc["signal"] = "VOTING_STARTED";

  String response;
  serializeJson(doc, response);

  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");

  server.send(200, "application/json", response);

  SerialMega.println("VOTING_STARTED");
  Serial.println("Voting Started signal received and sent to Arduino Mega.");
  delay(100);
}

void handleSendPin() {
  if (server.method() == HTTP_OPTIONS) {
    handleOptions();
    return;
  }

  String body = server.arg("plain");
  StaticJsonDocument<200> doc;
  DeserializationError error = deserializeJson(doc, body);

  if (error) {
    server.send(400, "application/json", "{\"error\":\"Invalid JSON\"}");
    return;
  }

  const char* pin = doc["pin"];
  SerialMega.println("PIN:" + String(pin));

  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.send(200, "application/json", "{\"message\":\"Vote Captured\"}");
  Serial.println("Vote Captured");

  sendPinToBackend(pin);
}

void sendPinToBackend(const char* pin) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverNamePin);

    http.addHeader("Content-Type", "application/json");

    StaticJsonDocument<200> doc;
    doc["pin"] = pin;
    String requestBody;
    serializeJson(doc, requestBody);

    int httpResponseCode = http.POST(requestBody);

    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("HTTP Response code: " + String(httpResponseCode));
      Serial.println("Response: " + response);

      StaticJsonDocument<200> responseDoc;
      deserializeJson(responseDoc, response);
      int status = responseDoc["status"];

      if (status == 1) {
        SerialMega.println("VOTE_ACCEPTED");
      } else {
        SerialMega.println("VOTE_REJECTED");
      }
    } else {
      Serial.println("Error on sending POST: " + String(httpResponseCode));
      Serial.println("Error message: " + String(http.errorToString(httpResponseCode)));
      SerialMega.println("VOTE_REJECTED");
    }

    http.end();
  } else {
    Serial.println("WiFi Disconnected");
    SerialMega.println("VOTE_REJECTED");
  }
}

void handleOptions() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");
  server.send(204);
}

void handlePostRequest() {
  addCORSHeaders();
  
  String body = server.arg("plain");
  DynamicJsonDocument doc(200);
  deserializeJson(doc, body);

  const char* receivedPin = doc["pin"];

  Serial.println(receivedPin);

  SerialMega.print(receivedPin);
  SerialMega.print("\n");
  Serial.println("PIN sent to Arduino Mega");

  String response = "{\"success\":true}";
  server.send(200, "application/json", response);
}

void handleResetRequest() {
  addCORSHeaders();

  Serial.println("Reset request received");

  SerialMega.print("RESET\n");
  Serial.println("Reset command sent to Arduino Mega");

  String response = "{\"success\":true}";
  server.send(200, "application/json", response);
}

void handleGetRemainingTimeRequest() {
  addCORSHeaders();

  SerialMega.print("GET_TIME\n");

  String remainingTime = "";
  unsigned long startMillis = millis();
  while ((millis() - startMillis) < 5000) {
    if (SerialMega.available() > 0) {
      remainingTime = SerialMega.readStringUntil('\n');
      break;
    }
  }
  if (remainingTime == "") {
    Serial.println("Timeout waiting for response from Arduino Mega");
    server.send(500, "application/json", "{\"error\":\"Timeout waiting for response from Arduino Mega\"}");
    return;
  }

  Serial.println("Received remaining time from Arduino Mega: " + remainingTime);

  DynamicJsonDocument doc(200);
  deserializeJson(doc, remainingTime);
  int hours = doc["hours"];
  int minutes = doc["minutes"];
  int seconds = doc["seconds"];

  String response = "{\"hours\":" + String(hours) + ",\"minutes\":" + String(minutes) + ",\"seconds\":" + String(seconds) + "}";
  server.send(200, "application/json", response);
}

void addCORSHeaders() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "POST, GET, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");
}

void handleScanCommand() {
  handleCommand("Scan the QR code");
}

void handleScannedCommand() {
  handleCommand("QR code scanned");
}

void handleClearCommand() {
  handleCommand("Clear display");
}

void handleScanFaceIDCommand() {
  handleCommand("Scan the Face ID");
}

void handleClearFaceIDCommand() {
  handleCommand("Clear Face ID");
}

void handleFaceMatchCommand() {
  handleCommand("Face ID Matched");
}

void handleFaceMismatchCommand() {
  handleCommand("Face ID Not Matched");
}

void handleFaceUpCommand() {
  handleCommand("Face Up");
}

void handleFaceDownCommand() {
  handleCommand("Face Down");
}

void handleMoveLeftCommand() {
  handleCommand("Move Left");
}

void handleMoveRightCommand() {
  handleCommand("Move Right");
}

void handleCommand(const char* defaultMessage) {
  StaticJsonDocument<200> doc;
  if (server.hasArg("plain")) {
    String message = server.arg("plain");
    deserializeJson(doc, message);
    const char* command = doc["message"];
    Serial.println(command);
    SerialMega.println(command);
  } else {
    doc["message"] = defaultMessage;
    SerialMega.println(defaultMessage);
  }
  
  String response;
  serializeJson(doc, response);
  
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.send(200, "application/json", response);

  Serial.println(String(defaultMessage) + " command received");
}

void handleNotFound() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");
  server.send(404, "text/plain", "Not Found");
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

  SerialMega.print("REGISTER ");
  SerialMega.print(fingerprintID);
  SerialMega.print(" ");
  SerialMega.println(nic);
  Serial.println("Sending fingerprint ID to Mega: " + String(fingerprintID));

  while (SerialMega.available() == 0) {
    delay(100);
  }

  String response = SerialMega.readStringUntil('\n');
  response.trim();

  DynamicJsonDocument responseDoc(1024);
  if (response.startsWith("REGISTERED:")) {
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

  SerialMega.println("COMPARE");
  Serial.println("Sending compare command to Mega");

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

bool sendNumberToServer(const String& disableNumber) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverNameVote);

    http.addHeader("Content-Type", "application/json");

    StaticJsonDocument<200> doc;
    doc["number"] = disableNumber;
    String requestBody;
    serializeJson(doc, requestBody);

    int httpResponseCode = http.POST(requestBody);

    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("HTTP Response code: " + String(httpResponseCode));
      Serial.println("Response: " + response);

      StaticJsonDocument<200> responseDoc;
      deserializeJson(responseDoc, response);
      int status = responseDoc["status"];

      http.end();

      return status == 1;
    } else {
      Serial.println("Error on sending POST: " + String(httpResponseCode));
      Serial.println("Error message: " + String(http.errorToString(httpResponseCode)));
      http.end();
      return false;
    }
  } else {
    Serial.println("WiFi Disconnected");
    return false;
  }
}

void sendNotification(bool success, const String& message) {
  StaticJsonDocument<200> doc;
  doc["success"] = success;
  doc["message"] = message;

  String response;
  serializeJson(doc, response);

  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.send(200, "application/json", response);

  Serial.println("Notification sent: " + message);
}
