#include <Adafruit_Fingerprint.h>
#include <SPI.h>
#include <Adafruit_GFX.h>
#include <MCUFRIEND_kbv.h>
#include <Fonts/FreeSansBold24pt7b.h>

#define LCD_CS A3
#define LCD_CD A2
#define LCD_WR A1
#define LCD_RD A0
#define LCD_RESET A4

MCUFRIEND_kbv tft;
Adafruit_Fingerprint finger = Adafruit_Fingerprint(&Serial3);

// Color definitions
#define BLACK 0x0000
#define WHITE 0xFFFF
#define GREEN 0x07E0
#define RED 0xF800

String currentNIC = "";
bool cancelOperation = false;

void setup() {
  Serial.begin(115200);
  Serial2.begin(115200); // Communication with ESP32

  uint16_t ID = tft.readID();
  Serial.print("TFT ID: 0x");
  Serial.println(ID, HEX);
  tft.begin(ID);
  tft.setRotation(1); // Adjust rotation if needed
  tft.fillScreen(WHITE); // Set background to white
  tft.setFont(&FreeSansBold24pt7b); // Set the font to FreeSansBold24pt7b

  // Initialize fingerprint sensor
  finger.begin(57600);
  if (finger.verifyPassword()) {
    Serial.println("Found fingerprint sensor!");
  } else {
    Serial.println("Did not find fingerprint sensor :(");
    while (1) { delay(1); }
  }
}

void loop() {
  if (Serial2.available()) {
    String command = Serial2.readStringUntil('\n');
    command.trim(); // Remove any leading or trailing whitespace
    Serial.print("Received command: '");
    Serial.print(command);
    Serial.println("'");

    if (command == "System is ready") {
      displayMultilineText("Ready\nfor\nfingerprint scan", BLACK);
    } else if (command.startsWith("REGISTER")) {
      cancelOperation = false; // Reset cancel operation flag
      displayMultilineText("Preparing to register fingerprint.\nPlease wait...", BLACK);
      delay(2000); // Give user some time to read the message

      int spaceIndex = command.indexOf(' ', 9);
      int id = command.substring(9, spaceIndex).toInt();
      currentNIC = command.substring(spaceIndex + 1);
      Serial.print("Registering fingerprint with ID: ");
      Serial.println(id);
      registerFingerprint(id);
    } else if (command == "COMPARE") {
      cancelOperation = false; // Reset cancel operation flag
      compareFingerprint();
    } else if (command == "DELETE_ALL") {
      deleteAllFingerprints();
    } else if (command == "CANCEL") {
      cancelOperation = true;
      Serial2.println("CANCELLED");
    }
  }

  delay(100); // Small delay to avoid busy looping
}

void displayMultilineText(String text, uint16_t color) {
  tft.fillScreen(WHITE);
  tft.setTextColor(color, WHITE);
  
  // Split the text into lines
  int16_t x1, y1;
  uint16_t w, h;
  tft.setTextWrap(false); // Disable text wrap
  int lineHeight = 40; // Approximate height of each line, adjust as needed

  int numLines = 1;
  for (char c : text) {
    if (c == '\n') {
      numLines++;
    }
  }

  int startY = (tft.height() - lineHeight * numLines) / 2;

  String line = "";
  int lineIndex = 0;

  for (char c : text) {
    if (c == '\n') {
      tft.getTextBounds(line.c_str(), 0, 0, &x1, &y1, &w, &h);
      tft.setCursor((tft.width() - w) / 2, startY + lineIndex * lineHeight);
      tft.print(line);
      line = "";
      lineIndex++;
    } else {
      line += c;
    }
  }

  // Print the last line
  tft.getTextBounds(line.c_str(), 0, 0, &x1, &y1, &w, &h);
  tft.setCursor((tft.width() - w) / 2, startY + lineIndex * lineHeight);
  tft.print(line);
}

void registerFingerprint(int id) {
  displayMultilineText("Place\nfinger on sensor", BLACK);

  // Extend the time for placing the finger on the sensor
  int attempts = 0;
  while (finger.getImage() != FINGERPRINT_OK) {
    if (cancelOperation) {
      displayMultilineText("Registration\nCancelled", RED);
      Serial2.println("REGISTER_FAILED");
      return;
    }
    if (++attempts > 100) { // Allow more attempts
      displayMultilineText("Failed to capture\nfingerprint", RED);
      Serial2.println("REGISTER_FAILED");
      return;
    }
    delay(200); // Add delay to avoid busy looping
  }

  if (finger.image2Tz(1) != FINGERPRINT_OK) {
    displayMultilineText("Image conversion\nfailed", RED);
    Serial.println("Image conversion failed");
    Serial2.println("REGISTER_FAILED");
    return;
  }

  displayMultilineText("Remove\nfinger", BLACK);
  delay(2000);

  displayMultilineText("Place\nsame finger again", BLACK);

  attempts = 0;
  while (finger.getImage() != FINGERPRINT_OK) {
    if (cancelOperation) {
      displayMultilineText("Registration\nCancelled", RED);
      Serial2.println("REGISTER_FAILED");
      return;
    }
    if (++attempts > 100) { // Allow more attempts
      displayMultilineText("Failed to capture\nfingerprint", RED);
      Serial2.println("REGISTER_FAILED");
      return;
    }
    delay(200); // Add delay to avoid busy looping
  }

  if (finger.image2Tz(2) != FINGERPRINT_OK) {
    displayMultilineText("Image conversion\nfailed", RED);
    Serial.println("Image conversion failed");
    Serial2.println("REGISTER_FAILED");
    return;
  }

  displayMultilineText("Processing", BLACK);

  if (finger.createModel() == FINGERPRINT_OK) {
    if (finger.storeModel(id) == FINGERPRINT_OK) {
      displayMultilineText("Fingerprint\nstored!", GREEN);
      Serial.println("Fingerprint stored!");

      // Send acknowledgment back to ESP32
      Serial2.println("REGISTERED:" + String(id) + " " + currentNIC);
    } else {
      displayMultilineText("Storage\nfailed", RED);
      Serial.println("Storage failed");

      // Send failure message back to ESP32
      Serial2.println("REGISTER_FAILED");
    }
  } else {
    displayMultilineText("Creation\nfailed", RED);
    Serial.println("Creation failed");

    // Send failure message back to ESP32
    Serial2.println("REGISTER_FAILED");
  }
}

void compareFingerprint() {
  displayMultilineText("Place\nfinger on sensor", BLACK);

  // Extend the time for placing the finger on the sensor
  int attempts = 0;
  while (finger.getImage() != FINGERPRINT_OK) {
    if (cancelOperation) {
      displayMultilineText("Comparison\nCancelled", RED);
      Serial2.println("COMPARE_FAILED:IMAGE_CONVERSION");
      return;
    }
    if (++attempts > 100) { // Allow more attempts
      displayMultilineText("Failed to capture\nfingerprint", RED);
      Serial2.println("COMPARE_FAILED:IMAGE_CONVERSION");
      return;
    }
    delay(200); // Add delay to avoid busy looping
  }

  if (finger.image2Tz(1) != FINGERPRINT_OK) {
    displayMultilineText("Image conversion\nfailed", RED);
    Serial.println("Image conversion failed");
    Serial2.println("COMPARE_FAILED:IMAGE_CONVERSION");
    return;
  }

  displayMultilineText("Processing", BLACK);

  int p = finger.fingerFastSearch(); // Use fingerFastSearch for better performance
  if (p == FINGERPRINT_OK) {
    displayMultilineText("Fingerprint\nmatched!", GREEN);
    Serial.print("Found ID #");
    Serial.print(finger.fingerID);
    Serial.print(" with confidence ");
    Serial.println(finger.confidence);

    // Send matched template ID back to ESP32
    Serial2.println("MATCHED:" + String(finger.fingerID));
  } else {
    displayMultilineText("No match\nfound", RED);
    Serial.println("No match found");

    // Send no match message back to ESP32
    Serial2.println("NO_MATCH");
  }
}

void deleteAllFingerprints() {
  Serial.println("Deleting all fingerprints...");
  bool success = true;

  for (uint8_t id = 1; id < 128; id++) {
    if (finger.deleteModel(id) != FINGERPRINT_OK) {
      success = false;
    }
  }

  if (success) {
    displayMultilineText("All fingerprints\ndeleted!", GREEN);
    Serial.println("All fingerprints deleted successfully");
    Serial2.println("DELETED_ALL");
  } else {
    displayMultilineText("Failed to delete\nall fingerprints", RED);
    Serial.println("Failed to delete fingerprints");
    Serial2.println("DELETE_ALL_FAILED");
  }
}
