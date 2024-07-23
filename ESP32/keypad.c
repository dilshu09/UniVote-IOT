#include <WiFi.h>
#include <WebServer.h>
#include <ArduinoJson.h>
#include <HTTPClient.h>

const char* ssid = "Toji";
const char* password = "toji2992";

WebServer server(80);

HardwareSerial SerialMega(2); // RX2, TX2 for communication with Arduino Mega

const char* serverName = "http://192.168.43.2/Candidates/send-pin.php"; // Updated with the correct path

void handleVotingStarted() {
  StaticJsonDocument<200> doc;
  doc["signal"] = "Voting_Started";

  String response;
  serializeJson(doc, response);

  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");

  server.send(200, "application/json", response);

  SerialMega.println("Voting_Started");
  Serial.println("Voting Started signal received and sent to Arduino Mega.");
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
  SerialMega.println(pin);

  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.send(200, "application/json", "{\"message\":\"Vote Captured\"}");
  Serial.println("Vote Captured");

  // Send PIN to backend
  sendPinToBackend(pin);
}

void sendPinToBackend(const char* pin) {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(serverName);

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
      
      // Parse the response
      StaticJsonDocument<200> responseDoc;
      deserializeJson(responseDoc, response);
      int status = responseDoc["status"];
      
      if (status == 1) {
        SerialMega.println("1");  // Send 1 for Vote Accepted
      } else {
        SerialMega.println("0");  // Send 0 for Enter ID Again
      }
    } else {
      Serial.println("Error on sending POST: " + String(httpResponseCode));
      Serial.println("Error message: " + String(http.errorToString(httpResponseCode)));
      SerialMega.println("0");  // Send 0 for Enter ID Again
    }

    http.end();
  } else {
    Serial.println("WiFi Disconnected");
    SerialMega.println("0");  // Send 0 for Enter ID Again
  }
}

void handleOptions() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");
  server.send(204);
}

void setup() {
  Serial.begin(115200);
  SerialMega.begin(115200, SERIAL_8N1, 16, 17);

  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.print(".");
  }
  Serial.println("Connected!");

  server.on("/start-voting", HTTP_GET, handleVotingStarted);
  server.on("/start-voting", HTTP_OPTIONS, handleOptions);
  server.on("/send-pin", HTTP_POST, handleSendPin);
  server.on("/send-pin", HTTP_OPTIONS, handleOptions);
  server.begin();
  Serial.println("Web server started. Waiting for requests...");
}

void loop() {
  server.handleClient();
  if (SerialMega.available()) {
    String pin = SerialMega.readStringUntil('\n');
    pin.trim();
    Serial.println("Received PIN: ****");
    // Send PIN to backend
    sendPinToBackend(pin.c_str());
  }
}



