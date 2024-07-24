#include <Wire.h>
#include <Adafruit_Sensor.h>
#include <Adafruit_BMP280.h>
#include <Adafruit_BMP085_U.h>
#include <SPI.h>
#include <Adafruit_GFX.h>
#include <MCUFRIEND_kbv.h>
#include <Fonts/FreeSansBold24pt7b.h>
#include <Fonts/FreeSansBold18pt7b.h>
#include <PCF8574.h>
#include <RTClib.h>
#include <EEPROM.h>
#include <LiquidCrystal_I2C.h>
#include <ArduinoJson.h>
#include <Adafruit_Fingerprint.h>

// Pin definitions for TFT
#define LCD_CS A3
#define LCD_CD A2
#define LCD_WR A1
#define LCD_RD A0
#define LCD_RESET A4

MCUFRIEND_kbv tft;

#define BLACK 0x0000
#define WHITE 0xFFFF
#define RED 0xF800
#define BLUE 0x001F
#define DARK_RED 0x8000
#define GREEN 0x07E0

Adafruit_BMP280 bmp280; // BMP280 sensor
Adafruit_BMP085_Unified bmp180 = Adafruit_BMP085_Unified(10085); // BMP180 sensor

const float pressureThresholdDigitBMP280 = 60.0;
const float pressureThresholdDigitBMP180 = 100.0;
const float pressureThresholdConfirmBMP280 = 100.0;
const float pressureThresholdConfirmBMP180 = 60.0;

float lastPressureBMP280 = 0.0;
float lastPressureBMP180 = 0.0;
bool bmp280Initialized = false;
bool bmp180Initialized = false;
bool secondPageDisplayed = false;
bool fifthPageDisplayed = false;
bool confirmationPageDisplayed = false;
int digitCount = 0;
int selectedDigit = 0;
int threeDigitNumber[3] = {0, 0, 0};

bool processStarted = false;
unsigned long lastUpdateTime = 0;

// Keypad definitions
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

String candidateID = "";
bool votingStarted = false;
bool candidateIDEntered = false;
bool confirmIDPageDisplayed = false;
bool waitingForDoubleStar = false;
unsigned long lastDebounceTime = 0;
unsigned long debounceDelay = 200;
bool previousKeyState[ROWS][COLS] = {false};

// RTC and LCD Initialization
RTC_DS3231 rtc;
LiquidCrystal_I2C lcd(0x27, 16, 2);  // Assuming address 0x27

// EEPROM Addresses
#define EEPROM_MAGIC_NUMBER_ADDR 0
#define EEPROM_MAGIC_NUMBER 0xA5
#define EEPROM_TIMER_FLAG_ADDR 1
#define EEPROM_ADDRESS_YEAR 2
#define EEPROM_ADDRESS_MONTH 3
#define EEPROM_ADDRESS_DAY 4
#define EEPROM_ADDRESS_HOUR 5
#define EEPROM_ADDRESS_MINUTE 6
#define EEPROM_ADDRESS_SECOND 7

bool timerStarted = false;
unsigned long previousMillis = 0;
const long interval = 1000;

Adafruit_Fingerprint finger = Adafruit_Fingerprint(&Serial3);
String currentNIC = "";
bool cancelOperation = false;

void setup() {
    Serial.begin(115200);
    Serial2.begin(115200); // Communication with ESP32

    // Initialize TFT display
    tft.begin(tft.readID());
    tft.setRotation(1);
    tft.fillScreen(WHITE);
    tft.setTextColor(BLACK);
    tft.setFont(&FreeSansBold24pt7b);

    // Add a simple message to confirm TFT is working
    tft.setCursor(0, 0);
    tft.print("TFT Initialized");

    // Initialize BMP280
    Serial.println("Initializing BMP280 sensor...");
    if (!bmp280.begin(0x76)) {
        Serial.println("Could not find a valid BMP280 sensor, check wiring!");
    } else {
        Serial.println("BMP280 sensor initialized at address 0x76");
        bmp280Initialized = true;
        lastPressureBMP280 = bmp280.readPressure();
        Serial.print("Initial BMP280 Pressure: ");
        Serial.print(lastPressureBMP280);
        Serial.println(" Pa");
    }

    // Initialize BMP180
    Serial.println("Initializing BMP180 sensor...");
    if (!bmp180.begin()) {
        Serial.println("Could not find a valid BMP180 sensor, check wiring!");
    } else {
        Serial.println("BMP180 sensor initialized");
        bmp180Initialized = true;
        sensors_event_t event;
        bmp180.getEvent(&event);
        lastPressureBMP180 = event.pressure * 100;
        Serial.print("Initial BMP180 Pressure: ");
        Serial.print(lastPressureBMP180);
        Serial.println(" Pa");
    }

    // Initialize PCF8574 for keypad
    Wire.begin();
    pcf8574.begin();

    for (byte i = 0; i < ROWS; i++) {
        pcf8574.write(rowPins[i], HIGH);
    }
    for (byte i = 0; i < COLS; i++) {
        pcf8574.write(colPins[i], HIGH);
    }

    // Initialize LCD
    lcd.init();
    lcd.backlight();

    Serial.println("Setup complete.");

    // Initialize RTC
    if (!rtc.begin()) {
        lcd.print("RTC failed");
        Serial.println("RTC failed");
        while (1);
    }

    if (rtc.lostPower()) {
        rtc.adjust(DateTime(F(_DATE), F(TIME_)));
        Serial.println("RTC lost power, time adjusted");
    }

    byte eepromInitFlag = EEPROM.read(EEPROM_MAGIC_NUMBER_ADDR);

    if (eepromInitFlag != EEPROM_MAGIC_NUMBER) {
        EEPROM.write(EEPROM_MAGIC_NUMBER_ADDR, EEPROM_MAGIC_NUMBER);
        EEPROM.write(EEPROM_TIMER_FLAG_ADDR, 0);
        lcd.clear();
        Serial.println("EEPROM initialized");
        timerStarted = false;
    } else {
        if (EEPROM.read(EEPROM_TIMER_FLAG_ADDR) == 1) {
            timerStarted = true;
            Serial.println("Timer was running");
            checkTimerProgress();
        } else {
            lcd.clear();
            Serial.println("Waiting for PIN");
            timerStarted = false;
        }
    }

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

        if (command == "VOTING_STARTED" && !votingStarted) {
            Serial.println("Voting started signal received");
            votingStarted = true;
            displayEnterCandidateID();
        } else if (command == "DISABLE_VOTING") {
            Serial.println("Disable voting signal received");
            if (!processStarted) {
                processStarted = true;
                secondPageDisplayed = false;
                displayCountdown(5);
            }
        } else if (command == "hardware") {
            if (!timerStarted) {
                startTimer();
                timerStarted = true;
                EEPROM.write(EEPROM_TIMER_FLAG_ADDR, 1); // Set the timer started flag
                Serial.println("Hardware command received, timer started");
            }
        } else if (command == "RESET") {
            resetSystem();
        } else if (command == "GET_TIME") {
            sendRemainingTime();
        } else if (command == "System is ready") {
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
        } else {
            tft.fillScreen(WHITE);
            uint16_t textColor = BLACK;
            if (command.equals("Face ID Matched")) {
                Serial.println("Setting text color to GREEN");
                textColor = GREEN;
            } else if (command.equals("Face ID Not Matched")) {
                Serial.println("Setting text color to RED");
                textColor = RED;
            } else {
                Serial.println("Setting text color to BLACK");
            }

            int16_t x1, y1;
            uint16_t w, h;
            tft.setTextWrap(false);
            tft.getTextBounds(command, 0, 0, &x1, &y1, &w, &h);
            int16_t x = (tft.width() - w) / 2;
            int16_t y = (tft.height() - h) / 2;

            tft.setCursor(x, y);
            tft.setTextColor(textColor, WHITE);
          
        }
    }

    if (bmp280Initialized && confirmationPageDisplayed) {
        float currentPressure = bmp280.readPressure();
        float pressureChange = currentPressure - lastPressureBMP280;

        if (pressureChange >= pressureThresholdConfirmBMP280) {
            Serial.print("Puff detected by BMP280! Pressure change: ");
            Serial.print(pressureChange);
            Serial.println(" Pa");
            confirmationPageDisplayed = false;
            storeDigit(selectedDigit);
            if (digitCount == 1) {
                displayFourthPage("Next page: Second digit");
            } else if (digitCount == 2) {
                displayFourthPage("Next page: Third digit");
            } else if (digitCount == 3) {
                displayWaitPage();
            }
        }

        lastPressureBMP280 = currentPressure;
    }

    if (bmp180Initialized && confirmationPageDisplayed) {
        sensors_event_t event;
        bmp180.getEvent(&event);
        float currentPressure = event.pressure * 100;
        float pressureChange = currentPressure - lastPressureBMP180;

        if (pressureChange >= pressureThresholdConfirmBMP180) {
            Serial.print("Puff detected by BMP180! Pressure change: ");
            Serial.print(pressureChange);
            Serial.println(" Pa");
            confirmationPageDisplayed = false;
            displayInvalidIdPage();
        }

        lastPressureBMP180 = currentPressure;
    }

    if (fifthPageDisplayed && millis() - lastUpdateTime >= 1000) {
        updatePressureValues();
        lastUpdateTime = millis();
    }

    if (bmp280Initialized && fifthPageDisplayed) {
        float currentPressure = bmp280.readPressure();
        float pressureChange = currentPressure - lastPressureBMP280;

        if (pressureChange >= pressureThresholdDigitBMP280) {
            Serial.print("Puff detected by BMP280! Pressure change: ");
            Serial.print(pressureChange);
            Serial.println(" Pa");
            updateSelectedDigit();
        }

        lastPressureBMP280 = currentPressure;
    }

    if (bmp180Initialized && fifthPageDisplayed) {
        sensors_event_t event;
        bmp180.getEvent(&event);
        float currentPressure = event.pressure * 100;
        float pressureChange = currentPressure - lastPressureBMP180;

        if (pressureChange >= pressureThresholdDigitBMP180) {
            Serial.print("Puff detected by BMP180! Pressure change: ");
            Serial.print(pressureChange);
            Serial.println(" Pa");
            fifthPageDisplayed = false;
            displayConfirmationPage();
        }

        lastPressureBMP180 = currentPressure;
    }

    if (votingStarted && !candidateIDEntered) {
        char key = getKeyFromKeypad();
        if (key != '\0') {
            Serial.print("Key pressed: ");
            Serial.println(key);
            if (isDigit(key)) {
                candidateID += key;
                Serial.print("Candidate ID so far: ");
                for (int i = 0; i < candidateID.length(); i++) {
                    Serial.print("*");
                }
                Serial.println();
                updateCandidateIDDisplay();
                waitingForDoubleStar = false;

                if (candidateID.length() == 3) {
                    candidateIDEntered = true;
                    displayConfirmIDPage();
                }
            } else if (key == '*') {
                if (waitingForDoubleStar) {
                    candidateID = "";
                } else {
                    if (candidateID.length() > 0) {
                        candidateID.remove(candidateID.length() - 1);
                    }
                    waitingForDoubleStar = true;
                }
                updateCandidateIDDisplay();
            } else {
                waitingForDoubleStar = false;
            }
        }
    } else if (confirmIDPageDisplayed) {
        char key = getKeyFromKeypad();
        if (key != '\0') {
            Serial.print("Key pressed: ");
            Serial.println(key);
            if (key == '*') {
                Serial.println("Going back to enter candidate ID.");
                candidateIDEntered = false;
                confirmIDPageDisplayed = false;
                candidateID = "";
                displayEnterCandidateID();
            } else if (key == '#') {
                Serial.println("Candidate ID confirmed: " + candidateID);
                sendCandidateIDToESP32();
            }
        }
    }

    unsigned long currentMillis = millis();
    if (timerStarted && currentMillis - previousMillis >= interval) {
        previousMillis = currentMillis;
        checkTimerProgress();
    }
}

void displayMessage(const char* message, const char* countdownMessage = nullptr) {
    tft.fillScreen(WHITE);
    tft.setFont(&FreeSansBold24pt7b);
    int16_t x1, y1;
    uint16_t w, h;
    tft.getTextBounds(message, 0, 0, &x1, &y1, &w, &h);
    int16_t x = (tft.width() - w) / 2;
    int16_t y = (tft.height() - h) / 2 - 20;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message);

    if (countdownMessage != nullptr) {
        tft.setFont(&FreeSansBold24pt7b);
        tft.getTextBounds(countdownMessage, 0, 0, &x1, &y1, &w, &h);
        x = (tft.width() - w) / 2;
        y = (tft.height() / 2) + 60;
        tft.setCursor(x, y);
        tft.setTextColor(RED);
        tft.print(countdownMessage);
    }
}

void displayCountdown(int seconds) {
    char countdownMessage[3];
    for (int i = seconds; i > 0; i--) {
        snprintf(countdownMessage, 3, "%d", i);
        displayMessage("Voting Starting", countdownMessage);
        delay(1000);
    }
    displaySecondPage();
}

void displaySecondPage() {
    if (secondPageDisplayed) return;

    secondPageDisplayed = true;
    Serial.println("Displaying second page...");
    tft.fillScreen(WHITE);
    tft.setFont(&FreeSansBold24pt7b);
    const char* message1 = "Puff X to Switch";
    const char* message2 = "Puff Y to Select";

    int16_t x1, y1;
    uint16_t w, h;
    tft.getTextBounds(message1, 0, 0, &x1, &y1, &w, &h);
    int16_t x = (tft.width() - w) / 2;
    int16_t y = (tft.height() / 2) - 60;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message1);

    tft.getTextBounds(message2, 0, 0, &x1, &y1, &w, &h);
    x = (tft.width() - w) / 2;
    y = (tft.height() / 2);
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message2);

    Serial.println("Displayed second page. Starting 10-second countdown...");

    int barWidth = tft.width() - 40;
    int barHeight = 30;
    int barX = 20;
    int barY = (tft.height() / 2) + 40;
    tft.drawRect(barX, barY, barWidth, barHeight, BLACK);

    for (int i = 0; i <= barWidth; i += 2) {
        tft.fillRect(barX + 1, barY + 1, i, barHeight - 2, RED);
        delay(50);
    }

    displayFourthPage("Next page: First digit");
}

void displayFourthPage(const char* message) {
    Serial.println("Displaying fourth page...");
    tft.fillScreen(WHITE);
    tft.setFont(&FreeSansBold18pt7b);

    int16_t x1, y1;
    uint16_t w, h;
    tft.getTextBounds(message, 0, 0, &x1, &y1, &w, &h);
    int16_t x = (tft.width() - w) / 2;
    int16_t y = (tft.height() - h) / 2;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message);

    Serial.println("Displayed fourth page. Starting 5-second loading bar...");

    int barWidth = tft.width() - 40;
    int barHeight = 30;
    int barX = 20;
    int barY = (tft.height() / 2) + 40;
    tft.drawRect(barX, barY, barWidth, barHeight, BLACK);

    for (int i = 0; i <= barWidth; i += 2) {
        tft.fillRect(barX + 1, barY + 1, i, barHeight - 2, RED);
        delay(50);
    }

    displayFifthPage();
}

void displayFifthPage() {
    Serial.println("Displaying fifth page with digit cells...");
    tft.fillScreen(WHITE);
    tft.setFont(&FreeSansBold24pt7b);

    const int cellWidth = tft.width() / 5;
    const int cellHeight = tft.height() / 2;

    const char* digits[] = {"0", "1", "2", "3", "4", "5", "6", "7", "8", "9"};
    const int numDigits = 10;

    for (int i = 0; i < numDigits; i++) {
        int x = (i % 5) * cellWidth;
        int y = (i / 5) * cellHeight;

        if (i == selectedDigit) {
            tft.fillRect(x, y, cellWidth, cellHeight, BLUE);
            tft.setTextColor(WHITE);
        } else {
            tft.drawRect(x, y, cellWidth, cellHeight, BLACK);
            tft.setTextColor(BLACK);
        }

        int16_t x1, y1;
        uint16_t w, h;
        tft.getTextBounds(digits[i], 0, 0, &x1, &y1, &w, &h);
        int16_t textX = x + (cellWidth - w) / 2;
        int16_t textY = y + (cellHeight + h) / 2;

        tft.setCursor(textX, textY);
        tft.print(digits[i]);
    }

    fifthPageDisplayed = true;
    Serial.println("Fifth page displayed with digit cells.");
}

void updateSelectedDigit() {
    selectedDigit = (selectedDigit + 1) % 10;
    displayFifthPage();
}

void updatePressureValues() {
    if (bmp280Initialized) {
        float currentPressureBMP280 = bmp280.readPressure();
        float pressureChangeBMP280 = currentPressureBMP280 - lastPressureBMP280;

        Serial.print("BMP280 Pressure: ");
        Serial.print(currentPressureBMP280);
        Serial.print(" Pa (Change: ");
        Serial.print(pressureChangeBMP280);
        Serial.println(" Pa)");

        lastPressureBMP280 = currentPressureBMP280;
    }

    if (bmp180Initialized) {
        sensors_event_t event;
        bmp180.getEvent(&event);
        float currentPressureBMP180 = event.pressure * 100;
        float pressureChangeBMP180 = currentPressureBMP180 - lastPressureBMP180;

        Serial.print("BMP180 Pressure: ");
        Serial.print(currentPressureBMP180);
        Serial.print(" Pa (Change: ");
        Serial.print(pressureChangeBMP180);
        Serial.println(" Pa)");

        lastPressureBMP180 = currentPressureBMP180;
    }
}

void sendThreeDigitNumber() {
    String threeDigitString = String(threeDigitNumber[0]) + String(threeDigitNumber[1]) + String(threeDigitNumber[2]);
    Serial2.println("DISABLE:" + threeDigitString);
    Serial.print("Sent three-digit number: ");
    Serial.println(threeDigitString);
}

void displayWaitPage() {
    sendThreeDigitNumber();
    
    Serial.println("Displaying wait page...");
    tft.fillScreen(WHITE);
    tft.setFont(&FreeSansBold24pt7b);

    const char* message1 = "Please wait,";
    const char* message2 = "checking the";
    const char* message3 = "candidate ID";

    int16_t x1, y1;
    uint16_t w, h;
    tft.getTextBounds(message1, 0, 0, &x1, &y1, &w, &h);
    int x = (tft.width() - w) / 2;
    int y = (tft.height() - h) / 2 - 40;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message1);

    tft.getTextBounds(message2, 0, 0, &x1, &y1, &w, &h);
    x = (tft.width() - w) / 2;
    y += h + 20;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message2);

    tft.getTextBounds(message3, 0, 0, &x1, &y1, &w, &h);
    x = (tft.width() - w) / 2;
    y += h + 20;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message3);

    Serial.println("Wait page displayed.");
}

void handleResponse(String response) {
    Serial.print("Handling response: ");
    Serial.println(response);

    if (response == "VOTE_SUCCESS") {
        displayMessage("Vote Successful");
        delay(3000);
        displayMessage("Have a nice day");
        delay(3000);
        tft.fillScreen(WHITE);
        processStarted = false;
    } else if (response == "VOTE_INVALID") {
        displayInvalidIdPage();
    } else if (response == "VOTE_ACCEPTED") {
        displayMessage("Vote Accepted");
        delay(2000);
        displayMessage("Have a nice day");
        delay(3000);
        tft.fillScreen(WHITE);
        processStarted = false;
    } else if (response == "VOTE_REJECTED") {
        displayInvalidIdPage();
    }
}

void displayInvalidIdPage() {
    Serial.println("Displaying invalid ID page...");
    tft.fillScreen(WHITE);
    tft.setFont(&FreeSansBold24pt7b);

    const char* message1 = "Invalid ID";
    const char* message2 = "Returning to";
    const char* message3 = "digit entry";

    int16_t x1, y1;
    uint16_t w, h;
    tft.getTextBounds(message1, 0, 0, &x1, &y1, &w, &h);
    int x = (tft.width() - w) / 2;
    int y = (tft.height() - h) / 2 - 40;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message1);

    tft.getTextBounds(message2, 0, 0, &x1, &y1, &w, &h);
    x = (tft.width() - w) / 2;
    y += h + 20;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message2);

    tft.getTextBounds(message3, 0, 0, &x1, &y1, &w, &h);
    x = (tft.width() - w) / 2;
    y += h + 20;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message3);

    delay(2000);
    digitCount = 0;
    selectedDigit = 0;
    displayFifthPage();
}

void storeDigit(int digit) {
    if (digitCount < 3) {
        threeDigitNumber[digitCount] = digit;
        digitCount++;
        Serial.print("Stored digit: ");
        Serial.println(digit);
        Serial.print("Current number: ");
        for (int i = 0; i < digitCount; i++) {
            Serial.print(threeDigitNumber[i]);
        }
        Serial.println();
    }
}

void displayConfirmationPage() {
    Serial.println("Displaying confirmation page...");
    tft.fillScreen(WHITE);
    tft.setFont(&FreeSansBold24pt7b);

    const char* message1 = "Confirm your";
    const char* message2 = "selection";

    int16_t x1, y1;
    uint16_t w, h;
    tft.getTextBounds(message1, 0, 0, &x1, &y1, &w, &h);
    int x = (tft.width() - w) / 2;
    int y = 50;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message1);

    tft.getTextBounds(message2, 0, 0, &x1, &y1, &w, &h);
    x = (tft.width() - w) / 2;
    y += h + 10;
    tft.setCursor(x, y);
    tft.setTextColor(BLACK);
    tft.print(message2);

    tft.fillRect((tft.width() / 4) - 60, (tft.height() / 2) - 30, 120, 60, BLACK);
    tft.drawRect((tft.width() / 4) - 60, (tft.height() / 2) - 30, 120, 60, BLACK);
    tft.setTextColor(WHITE);
    tft.setCursor((tft.width() / 4) - 30, (tft.height() / 2) + 15);
    tft.print("Yes");

    tft.fillRect((3 * tft.width() / 4) - 60, (tft.height() / 2) - 30, 120, 60, BLACK);
    tft.drawRect((3 * tft.width() / 4) - 60, (tft.height() / 2) - 30, 120, 60, BLACK);
    tft.setTextColor(WHITE);
    tft.setCursor((3 * tft.width() / 4) - 30, (tft.height() / 2) + 15);
    tft.print("No");

    tft.setFont(&FreeSansBold18pt7b);
    tft.setTextColor(BLACK);
    tft.setCursor((tft.width() / 4) - 50, (tft.height() / 2) + 90);
    tft.print("Puff X");
    tft.setCursor((tft.width() / 4) - 50, (tft.height() / 2) + 120);
    tft.print("for Yes");
    tft.setCursor((3 * tft.width() / 4) - 50, (tft.height() / 2) + 90);
    tft.print("Puff Y");
    tft.setCursor((3 * tft.width() / 4) - 50, (tft.height() / 2) + 120);
    tft.print("for No");

    confirmationPageDisplayed = true;
    Serial.println("Confirmation page displayed.");
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

    updateCandidateIDDisplay();
    Serial.println("Enter Candidate ID page displayed");
}

void updateCandidateIDDisplay() {
    Serial.println("Updating Candidate ID display");
    int16_t x = (tft.width() - 3 * 140) / 2;
    int16_t y = (tft.height() / 2) - 60;

    for (int i = 0; i < 3; i++) {
        if (i < candidateID.length()) {
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
    Serial.println("Candidate ID display updated");
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

void displayConfirmIDPage() {
    tft.fillScreen(WHITE);
    String message1 = "Entered ID:";
    String idMessage = candidateID;
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

    tft.getTextBounds(idMessage, 0, 0, &x1, &y1, &w, &h);
    x = (tft.width() - w) / 2;
    y = (tft.height() / 2) - 60;
    tft.setCursor(x, y);
    tft.setTextColor(DARK_RED, WHITE);
    tft.print(idMessage);

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

    confirmIDPageDisplayed = true;
    Serial.println("Confirm ID page displayed");
}

void sendCandidateIDToESP32() {
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

    Serial2.println("VOTE:" + candidateID);

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

    if (response == "VOTE_ACCEPTED") {
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
    candidateIDEntered = false;
    confirmIDPageDisplayed = false;
    waitingForDoubleStar = false;
    candidateID = "";
    tft.fillScreen(WHITE);
    Serial.println("Process terminated. Ready for new input.");
}

char getKeyFromKeypad() {
    unsigned long currentTime = millis();
    
    for (byte c = 0; c < COLS; c++) {
        pcf8574.write(colPins[c], LOW);
        
        for (byte r = 0; r < ROWS; r++) {
            bool currentState = (pcf8574.read(rowPins[r]) == LOW);
            
            if (currentState != previousKeyState[r][c]) {
                if (currentState) {
                    if ((currentTime - lastDebounceTime) > debounceDelay) {
                        lastDebounceTime = currentTime;
                        previousKeyState[r][c] = currentState;
                        pcf8574.write(colPins[c], HIGH);
                        return keys[r][c];
                    }
                }
                previousKeyState[r][c] = currentState;
            }
        }
        pcf8574.write(colPins[c], HIGH);
    }
    return '\0';
}

bool isDigit(char c) {
    return c >= '0' && c <= '9';
}

void startTimer() {
    DateTime now = rtc.now();

    EEPROM.write(EEPROM_ADDRESS_YEAR, now.year() - 2000);
    EEPROM.write(EEPROM_ADDRESS_MONTH, now.month());
    EEPROM.write(EEPROM_ADDRESS_DAY, now.day());
    EEPROM.write(EEPROM_ADDRESS_HOUR, now.hour());
    EEPROM.write(EEPROM_ADDRESS_MINUTE, now.minute());
    EEPROM.write(EEPROM_ADDRESS_SECOND, now.second());

    Serial.println("Timer started");
    Serial.print("Start time: ");
    Serial.print(now.year());
    Serial.print("-");
    Serial.print(now.month());
    Serial.print("-");
    Serial.print(now.day());
    Serial.print(" ");
    Serial.print(now.hour());
    Serial.print(":");
    Serial.print(now.minute());
    Serial.print(":");
    Serial.println(now.second());
}

DateTime getTimeFromEEPROM() {
    int year = EEPROM.read(EEPROM_ADDRESS_YEAR) + 2000;
    int month = EEPROM.read(EEPROM_ADDRESS_MONTH);
    int day = EEPROM.read(EEPROM_ADDRESS_DAY);
    int hour = EEPROM.read(EEPROM_ADDRESS_HOUR);
    int minute = EEPROM.read(EEPROM_ADDRESS_MINUTE);
    int second = EEPROM.read(EEPROM_ADDRESS_SECOND);

    Serial.print("Retrieved time from EEPROM: ");
    Serial.print(year);
    Serial.print("-");
    Serial.print(month);
    Serial.print("-");
    Serial.print(day);
    Serial.print(" ");
    Serial.print(hour);
    Serial.print(":");
    Serial.print(minute);
    Serial.print(":");
    Serial.println(second);

    return DateTime(year, month, day, hour, minute, second);
}

void checkTimerProgress() {
    DateTime startTime = getTimeFromEEPROM();
    DateTime currentTime = rtc.now();

    DateTime endTime = startTime + TimeSpan(0, 12, 0, 0);

    lcd.clear();
    if (currentTime < endTime) {
        TimeSpan remaining = endTime - currentTime;
        lcd.setCursor(0, 0);
        lcd.print("Time left: ");
        lcd.setCursor(0, 1);
        lcd.print(remaining.hours(), DEC);
        lcd.print("h ");
        lcd.print(remaining.minutes() % 60, DEC);
        lcd.print("m ");
        lcd.print(remaining.seconds() % 60, DEC);
        lcd.print("s ");
    } else {
        lcd.setCursor(0, 0);
        lcd.print("Timer finished");
        EEPROM.write(EEPROM_TIMER_FLAG_ADDR, 0);
    }
}

void sendRemainingTime() {
    DateTime startTime = getTimeFromEEPROM();
    DateTime currentTime = rtc.now();
    DateTime endTime = startTime + TimeSpan(0, 12, 0, 0);
    TimeSpan remaining = endTime - currentTime;

    Serial.print("Remaining time: ");
    Serial.print(remaining.hours());
    Serial.print(" hours, ");
    Serial.print(remaining.minutes());
    Serial.print(" minutes, ");
    Serial.print(remaining.seconds());
    Serial.println(" seconds");

    DynamicJsonDocument doc(200);
    doc["hours"] = remaining.hours();
    doc["minutes"] = remaining.minutes() % 60;
    doc["seconds"] = remaining.seconds() % 60;

    String output;
    serializeJson(doc, output);

    Serial2.print(output);
    Serial2.print("\n");
}

void resetSystem() {
    EEPROM.write(EEPROM_MAGIC_NUMBER_ADDR, 0);
    EEPROM.write(EEPROM_TIMER_FLAG_ADDR, 0);
    lcd.clear();
    timerStarted = false;
    Serial.println("System reset");
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
