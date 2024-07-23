#include <WiFi.h>
#include <HTTPClient.h>
#include <WebServer.h>
#include <ArduinoJson.h>

const char* ssid = "Toji"; // Replace with your WiFi SSID
const char* password = "toji2992"; // Replace with your WiFi password

WebServer server(80); // Create a web server object that listens on port 80
HardwareSerial SerialMega(2); // RX2, TX2 for communication with Arduino Mega

void handleRoot() {
    server.sendHeader("Access-Control-Allow-Origin", "*");
    server.send(200, "text/plain", "ESP32 is ready");
}

void handleDisableVoting() {
    server.sendHeader("Access-Control-Allow-Origin", "*");
    SerialMega.println("Disable_Voting");
    server.send(200, "application/json", "{\"message\":\"Disable Voting signal sent to Arduino Mega\"}");
    Serial.println("Disable Voting signal sent to Arduino Mega.");
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
    while (WiFi.status() != WL_CONNECTED) {
        delay(1000);
        Serial.println("Connecting to WiFi...");
    }
    Serial.println("Connected to WiFi");

    server.on("/", handleRoot);
    server.on("/disable-voting", HTTP_GET, handleDisableVoting);
    server.on("/disable-voting", HTTP_OPTIONS, handleOptions);

    server.begin();
    Serial.println("Server started");
}

void loop() {
    server.handleClient();

    if (SerialMega.available()) {
        String receivedNumber = SerialMega.readStringUntil('\n');
        receivedNumber.trim();
        Serial.print("Received 3-digit number from Arduino Mega: ");
        Serial.println(receivedNumber);

        // Send the received number to the server
        if (sendNumberToServer(receivedNumber)) {
            Serial.println("Vote sent successfully.");
        } else {
            Serial.println("Failed to send vote.");
        }
    }
}

bool sendNumberToServer(const String& number) {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin("http://192.168.43.2/Candidates/validate-vote.php"); // Specify your server URL
        http.addHeader("Content-Type", "application/json");

        StaticJsonDocument<200> jsonDoc;
        jsonDoc["disable3digit"] = number;
        String requestBody;
        serializeJson(jsonDoc, requestBody);

        int httpResponseCode = http.POST(requestBody);

        if (httpResponseCode > 0) {
            String response = http.getString();
            Serial.print("Server response: ");
            Serial.println(response);

            StaticJsonDocument<200> responseJson;
            DeserializationError error = deserializeJson(responseJson, response);

            if (!error) {
                int status = responseJson["status"];
                if (status == 1) {
                    SerialMega.println("SUCCESS");
                } else {
                    SerialMega.println("INVALID");
                }
                return true;
            } else {
                Serial.println("Failed to parse response JSON.");
                return false;
            }
        } else {
            Serial.print("Error on sending POST: ");
            Serial.println(httpResponseCode);
            return false;
        }

        http.end();
    } else {
        Serial.println("WiFi not connected.");
        return false;
    }
}

