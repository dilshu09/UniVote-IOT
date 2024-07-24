#include <Wire.h>
#include <PCF8574.h>
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

#define BLACK 0x0000
#define WHITE 0xFFFF
#define DARK_RED 0x8000

const byte ROWS = 4;
const byte COLS = 3;

char keys[ROWS][COLS] = {
  {'1','2','3'},
  {'4','5','6'},
  {'7','8','9'},
  {'*','0','#'}
};

uint8_t rowPins[ROWS] = {1, 6, 5, 3}; // P0 to P3
uint8_t colPins[COLS] = {2, 0, 4};    // P4 to P6

PCF8574 pcf8574(0x20);

String pin = "";
bool votingStarted = false;
bool pinEntered = false;
bool confirmPageDisplayed = false;
bool waitingForDoubleStar = false;

// Variables for debouncing
unsigned long lastDebounceTime = 0;
unsigned long debounceDelay = 200; // 200 milliseconds debounce delay

// Store the previous state of each key
bool previousKeyState[ROWS][COLS] = {false};

void setup() {
  Wire.begin();
  Serial.begin(115200);
  Serial2.begin(115200); // RX2 = Serial2 RX, TX2 = Serial2 TX on Mega (pins 16 and 17)
  pcf8574.begin();

  // Initialize the row pins as INPUT with pull-ups and column pins as OUTPUT
  for (byte i = 0; i < ROWS; i++) {
    pcf8574.write(rowPins[i], HIGH); // Ensure default state is HIGH (not pressed)
  }
  for (byte i = 0; i < COLS; i++) {
    pcf8574.write(colPins[i], HIGH); // Set columns as HIGH (inactive state)
  }

  uint16_t ID = tft.readID();
  tft.begin(ID);
  tft.setRotation(1);
  tft.fillScreen(WHITE);
  tft.setTextColor(BLACK);
  tft.setFont(&FreeSansBold24pt7b);

  Serial.println("Setup complete. Waiting for signal...");
}

void loop() {
  if (Serial2.available()) {
    String command = Serial2.readStringUntil('\n');
    command.trim();
    Serial.print("Received command: ");
    Serial.println(command);

    if (command == "Voting_Started" && !votingStarted) {
      Serial.println("Voting started signal received");
      votingStarted = true;
      displayEnterCandidateID();
    } else if (command == "OK") {
      terminateProcess();
    }
  }

  if (votingStarted && !pinEntered) {
    char key = getKeyFromKeypad();
    if (key != '\0') {
      Serial.print("Key pressed: ");
      Serial.println(key);
      if (isDigit(key)) {
        pin += key;
        Serial.print("PIN so far: ");
        for (int i = 0; i < pin.length(); i++) {
          Serial.print("*");
        }
        Serial.println();
        updatePINDisplay();
        waitingForDoubleStar = false;

        if (pin.length() == 3) {
          pinEntered = true;
          displayConfirmationPage();
        }
      } else if (key == '*') {
        if (waitingForDoubleStar) {
          pin = "";
        } else {
          if (pin.length() > 0) {
            pin.remove(pin.length() - 1);
          }
          waitingForDoubleStar = true;
        }
        updatePINDisplay();
      } else {
        waitingForDoubleStar = false;
      }
    }
  } else if (confirmPageDisplayed) {
    char key = getKeyFromKeypad();
    if (key != '\0') {
      Serial.print("Key pressed: ");
      Serial.println(key);
      if (key == '*') {
        Serial.println("Going back to enter candidate ID.");
        pinEntered = false;
        confirmPageDisplayed = false;
        pin = ""; // Clear the PIN
        displayEnterCandidateID();
      } else if (key == '#') {
        Serial.println("PIN confirmed: " + pin);
        sendPinToESP32();
      }
    }
  }
}

void displayEnterCandidateID() {
  tft.fillScreen(WHITE);
  String message = "Enter Candidate ID";
  int16_t x1, y1;
  uint16_t w, h;
  tft.setTextWrap(false);
  tft.getTextBounds(message, 0, 0, &x1, &y1, &w, &h);
  int16_t x = (tft.width() - w) / 2;
  int16_t y = (tft.height() - h) / 2 - 80;

  tft.setCursor(x, y);
  tft.setTextColor(BLACK, WHITE);
  tft.print(message);

  updatePINDisplay();
  Serial.println("Enter Candidate ID page displayed");
}

void updatePINDisplay() {
  Serial.println("Updating PIN display");
  int16_t x = (tft.width() - 3 * 140) / 2;
  int16_t y = (tft.height() / 2) - 60;

  for (int i = 0; i < 3; i++) {
    if (i < pin.length()) {
      drawStylishSquare(x + i * 140, y, DARK_RED);
    } else {
      drawEmptySquare(x + i * 140, y);
    }
  }

  String clearMessage = "Clear = '*'";
  int16_t x1, y1;
  uint16_t w, h;
  tft.setFont(&FreeSansBold24pt7b);
  tft.getTextBounds(clearMessage, 0, 0, &x1, &y1, &w, &h);
  x = tft.width() - w - 20;
  y = (tft.height() / 2) + 140;
  tft.fillRect(0, y - h, tft.width(), h + 10, WHITE);
  tft.setCursor(x, y);
  tft.print(clearMessage);
  tft.setFont(&FreeSansBold24pt7b);
  Serial.println("PIN display updated");
}

void drawStylishSquare(int16_t x, int16_t y, uint16_t color) {
  int16_t w = 120;
  int16_t h = 120;
  int16_t padding = 10;

  tft.fillRect(x + padding, y + padding, w - padding * 2, h - padding * 2, color);
  tft.drawRect(x, y, w, h, BLACK);
  tft.drawRect(x + padding, y + padding, w - padding * 2, h - padding * 2, WHITE);
}

void drawEmptySquare(int16_t x, int16_t y) {
  int16_t w = 120;
  int16_t h = 120;

  tft.fillRect(x, y, w, h, WHITE);
  tft.drawRect(x, y, w, h, BLACK);
}

void displayConfirmationPage() {
  tft.fillScreen(WHITE);
  String message1 = "Entered ID:";
  String pinMessage = pin;
  String message2 = "Press * to try again";
  String message3 = "Press # to confirm";
  int16_t x1, y1;
  uint16_t w, h;

  tft.setTextWrap(false);
  
  tft.getTextBounds(message1, 0, 0, &x1, &y1, &w, &h);
  int16_t x = (tft.width() - w) / 2;
  int16_t y = (tft.height() / 2) - 120;
  tft.setCursor(x, y);
  tft.setTextColor(BLACK, WHITE);
  tft.print(message1);

  tft.getTextBounds(pinMessage, 0, 0, &x1, &y1, &w, &h);
  x = (tft.width() - w) / 2;
  y = (tft.height() / 2) - 60;
  tft.setCursor(x, y);
  tft.setTextColor(DARK_RED, WHITE);
  tft.print(pinMessage);

  tft.getTextBounds(message2, 0, 0, &x1, &y1, &w, &h);
  x = (tft.width() - w) / 2;
  y = (tft.height() / 2);
  tft.setCursor(x, y);
  tft.setTextColor(BLACK, WHITE);
  tft.print(message2);

  tft.getTextBounds(message3, 0, 0, &x1, &y1, &w, &h);
  x = (tft.width() - w) / 2;
  y = (tft.height() / 2) + 60;
  tft.setCursor(x, y);
  tft.setTextColor(BLACK, WHITE);
  tft.print(message3);

  confirmPageDisplayed = true;
  Serial.println("Confirmation page displayed");
}

void sendPinToESP32() {
  tft.fillScreen(WHITE);
  String message = "Sending Vote";
  int16_t x1, y1;
  uint16_t w, h;
  tft.setTextWrap(false);
  tft.getTextBounds(message, 0, 0, &x1, &y1, &w, &h);
  int16_t x = (tft.width() - w) / 2;
  int16_t y = (tft.height() - h) / 2;

  tft.setCursor(x, y);
  tft.setTextColor(BLACK, WHITE);
  tft.print(message);

  Serial2.println(pin);

  delay(1000);

  unsigned long startTime = millis();
  while (!Serial2.available()) {
    if (millis() - startTime > 5000) {
      tft.fillScreen(WHITE);
      tft.setCursor(x, y);
      tft.setTextColor(BLACK, WHITE);
      tft.print("Vote Failed");
      delay(2000);
      displayEnterCandidateID();
      return;
    }
  }

  String response = Serial2.readStringUntil('\n');
  response.trim();

  Serial.print("Response from ESP32: ");
  Serial.println(response);

  int responseCode = response.toInt();  // Convert the response to an integer

  if (responseCode == 1) {
    tft.fillScreen(WHITE);
    tft.setCursor(x, y);
    tft.setTextColor(BLACK, WHITE);
    tft.print("Vote Accepted");
    delay(2000);
    tft.fillScreen(WHITE);
    String finalMessage = "Have a nice day";
    tft.getTextBounds(finalMessage, 0, 0, &x1, &y1, &w, &h);
    x = (tft.width() - w) / 2;
    y = (tft.height() - h) / 2;
    tft.setCursor(x, y);
    tft.print(finalMessage);
    delay(5000);
    tft.fillScreen(WHITE);
    terminateProcess();
  } else {
    tft.fillScreen(WHITE);
    tft.setCursor(x, y);
    tft.setTextColor(BLACK, WHITE);
    tft.print("Vote Rejected");
    delay(2000);
    displayEnterCandidateID();
  }
}

void terminateProcess() {
  votingStarted = false;
  pinEntered = false;
  confirmPageDisplayed = false;
  waitingForDoubleStar = false;
  pin = "";
  tft.fillScreen(WHITE);
  Serial.println("Process terminated. Ready for new input.");
}

char getKeyFromKeypad() {
  unsigned long currentTime = millis();
  
  for (byte c = 0; c < COLS; c++) {
    pcf8574.write(colPins[c], LOW); // Set the current column to LOW
    
    for (byte r = 0; r < ROWS; r++) {
      bool currentState = (pcf8574.read(rowPins[r]) == LOW);
      
      if (currentState != previousKeyState[r][c]) { // Key state has changed
        if (currentState) { // Key is pressed
          if ((currentTime - lastDebounceTime) > debounceDelay) { // Debounce
            lastDebounceTime = currentTime;
            previousKeyState[r][c] = currentState; // Update previous key state
            pcf8574.write(colPins[c], HIGH); // Reset the column to HIGH
            return keys[r][c]; // Return the pressed key
          }
        }
        previousKeyState[r][c] = currentState; // Update previous key state
      }
    }
    pcf8574.write(colPins[c], HIGH); // Reset the column to HIGH
  }
  return '\0'; // No key pressed
}

bool isDigit(char c) {
  return c >= '0' && c <= '9';
}
