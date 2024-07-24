#include <WiFi.h>
#include <WebServer.h>
#include <ArduinoJson.h>

const char* ssid = "Toji"; // Replace with your WiFi SSID
const char* password = "toji2992"; // Replace with your WiFi password

WebServer server(80); // Create a web server object that listens on port 80

HardwareSerial SerialMega(2); // RX2, TX2 for communication with Arduino Mega

void handleRoot() {
  server.send(200, "text/plain", "ESP32 is ready");
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
    Serial.println(command);  // Print to Serial Monitor
    SerialMega.println(command);  // Send to Arduino Mega
  } else {
    doc["message"] = defaultMessage;
    SerialMega.println(defaultMessage);  // Send to Arduino Mega
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

void handleOptions() {
  server.sendHeader("Access-Control-Allow-Origin", "*");
  server.sendHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  server.sendHeader("Access-Control-Allow-Headers", "Content-Type");
  server.send(204);
}

void setup() {
  Serial.begin(115200);
  SerialMega.begin(115200, SERIAL_8N1, 16, 17); // RX2 is GPIO 16, TX2 is GPIO 17
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
  }
  Serial.println("Connected to WiFi");

  server.on("/", handleRoot); // Define the root endpoint
  server.on("/scan", HTTP_POST, handleScanCommand); // Define the scan command endpoint for POST requests
  server.on("/scan", HTTP_OPTIONS, handleOptions); // Define the scan command endpoint for OPTIONS requests
  server.on("/scanned", HTTP_POST, handleScannedCommand); // Define the scanned command endpoint for POST requests
  server.on("/scanned", HTTP_OPTIONS, handleOptions); // Define the scanned command endpoint for OPTIONS requests
  server.on("/clear", HTTP_POST, handleClearCommand); // Define the clear display command endpoint for POST requests
  server.on("/clear", HTTP_OPTIONS, handleOptions); // Define the clear display command endpoint for OPTIONS requests
  server.on("/scan_face_id", HTTP_POST, handleScanFaceIDCommand); // Define the scan face ID command endpoint for POST requests
  server.on("/scan_face_id", HTTP_OPTIONS, handleOptions); // Define the scan face ID command endpoint for OPTIONS requests
  server.on("/clear_face_id", HTTP_POST, handleClearFaceIDCommand); // Define the clear face ID command endpoint for POST requests
  server.on("/clear_face_id", HTTP_OPTIONS, handleOptions); // Define the clear face ID command endpoint for OPTIONS requests
  server.on("/face_match", HTTP_POST, handleFaceMatchCommand); // Define the face match command endpoint for POST requests
  server.on("/face_match", HTTP_OPTIONS, handleOptions); // Define the face match command endpoint for OPTIONS requests
  server.on("/face_mismatch", HTTP_POST, handleFaceMismatchCommand); // Define the face mismatch command endpoint for POST requests
  server.on("/face_mismatch", HTTP_OPTIONS, handleOptions); // Define the face mismatch command endpoint for OPTIONS requests
  server.on("/face_up", HTTP_POST, handleFaceUpCommand); // Define the face up command endpoint for POST requests
  server.on("/face_up", HTTP_OPTIONS, handleOptions); // Define the face up command endpoint for OPTIONS requests
  server.on("/face_down", HTTP_POST, handleFaceDownCommand); // Define the face down command endpoint for POST requests
  server.on("/face_down", HTTP_OPTIONS, handleOptions); // Define the face down command endpoint for OPTIONS requests
  server.on("/move_left", HTTP_POST, handleMoveLeftCommand); // Define the move left command endpoint for POST requests
  server.on("/move_left", HTTP_OPTIONS, handleOptions); // Define the move left command endpoint for OPTIONS requests
  server.on("/move_right", HTTP_POST, handleMoveRightCommand); // Define the move right command endpoint for POST requests
  server.on("/move_right", HTTP_OPTIONS, handleOptions); // Define the move right command endpoint for OPTIONS requests
  server.onNotFound(handleNotFound); // Handle not found errors

  server.begin(); // Start the server
  Serial.println("Server started"); 
}

void loop() {
  server.handleClient(); // Handle client requests
}
