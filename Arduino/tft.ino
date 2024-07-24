    #include <Adafruit_GFX.h>
    #include <MCUFRIEND_kbv.h>
    #include <Fonts/FreeSansBold24pt7b.h>


 //keypad
    
    #define LCD_CS A3
    #define LCD_CD A2
    #define LCD_WR A1
    #define LCD_RD A0
    #define LCD_RESET A4
    
    MCUFRIEND_kbv tft;
    
    #define BLACK 0x0000
    #define WHITE 0xFFFF
    #define DARK_RED 0x8000
    
    String pin = "";
    bool pinEntered = false;
    bool confirmPageDisplayed = false;
    
    void setup() {
      Serial.begin(115200);
      uint16_t ID = tft.readID();
      tft.begin(ID);
      tft.setRotation(1);
      tft.fillScreen(WHITE);
      tft.setTextColor(BLACK);
      tft.setFont(&FreeSansBold24pt7b);
      Serial.println("Setup complete.");
    }
    
    void loop() {
      // Code to handle other functionalities (e.g., keypad input) goes here
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
      pinEntered = false;
      confirmPageDisplayed = false;
      pin = "";
      tft.fillScreen(WHITE);
      Serial.println("Process terminated. Ready for new input.");
    }
    

//fingerprint

    #include <Adafruit_GFX.h>
        #include <MCUFRIEND_kbv.h>
        #include <Fonts/FreeSansBold24pt7b.h>
        
        #define LCD_CS A3
        #define LCD_CD A2
        #define LCD_WR A1
        #define LCD_RD A0
        #define LCD_RESET A4
        
        MCUFRIEND_kbv tft;
        
        // Color definitions
        #define BLACK 0x0000
        #define WHITE 0xFFFF
        #define GREEN 0x07E0
        #define RED 0xF800
        
        void setup() {
          Serial.begin(115200);
          uint16_t ID = tft.readID();
          Serial.print("TFT ID: 0x");
          Serial.println(ID, HEX);
          tft.begin(ID);
          tft.setRotation(1); // Adjust rotation if needed
          tft.fillScreen(WHITE); // Set background to white
          tft.setFont(&FreeSansBold24pt7b); // Set the font to FreeSansBold24pt7b
        }
        
        void loop() {
          // This loop is empty as TFT related functions are not dependent on loop.
        }
        
        // Function to display multiline text on the TFT screen
        void displayMultilineText(String text, uint16_t color) {
          tft.fillScreen(WHITE); // Clear the screen with white background
          tft.setTextColor(color, WHITE); // Set text color
        
          // Split the text into lines
          int16_t x1, y1;
          uint16_t w, h;
          tft.setTextWrap(false); // Disable text wrap
          int lineHeight = 40; // Approximate height of each line, adjust as needed
        
          // Calculate number of lines in the text
          int numLines = 1;
          for (char c : text) {
            if (c == '\n') {
              numLines++;
            }
          }
        
          int startY = (tft.height() - lineHeight * numLines) / 2; // Calculate start Y position
        
          String line = "";
          int lineIndex = 0;
        
          for (char c : text) {
            if (c == '\n') {
              tft.getTextBounds(line.c_str(), 0, 0, &x1, &y1, &w, &h); // Get text bounds
              tft.setCursor((tft.width() - w) / 2, startY + lineIndex * lineHeight); // Set cursor position
              tft.print(line); // Print line
              line = "";
              lineIndex++;
            } else {
              line += c; // Append character to line
            }
          }
        
          // Print the last line
          tft.getTextBounds(line.c_str(), 0, 0, &x1, &y1, &w, &h);
          tft.setCursor((tft.width() - w) / 2, startY + lineIndex * lineHeight);
          tft.print(line);
        }
        
        // Example functions that use the displayMultilineText function
        void showReadyMessage() {
          displayMultilineText("Ready\nfor\nfingerprint scan", BLACK);
        }
        
        void showRegisterMessage() {
          displayMultilineText("Preparing to register fingerprint.\nPlease wait...", BLACK);
        }
        
        void showRegistrationCancelled() {
          displayMultilineText("Registration\nCancelled", RED);
        }
        
        void showFingerprintRegistered() {
          displayMultilineText("Fingerprint\nstored!", GREEN);
        }
        
        void showFingerprintFailed() {
          displayMultilineText("Failed to capture\nfingerprint", RED);
        }
        
        void showFingerprintMatched() {
          displayMultilineText("Fingerprint\nmatched!", GREEN);
        }
        
        void showNoMatchFound() {
          displayMultilineText("No match\nfound", RED);
        }
        
        void showAllFingerprintsDeleted() {
          displayMultilineText("All fingerprints\ndeleted!", GREEN);
        }
        
        void showDeleteAllFailed() {
          displayMultilineText("Failed to delete\nall fingerprints", RED);
        }
        

//bmp
    #include <Wire.h>
    #include <Adafruit_Sensor.h>
    #include <Adafruit_BMP280.h>
    #include <Adafruit_BMP085_U.h>
    #include <SPI.h>
    #include <Adafruit_GFX.h>
    #include <MCUFRIEND_kbv.h>
    #include <Fonts/FreeSansBold24pt7b.h>
    #include <Fonts/FreeSansBold18pt7b.h>
    
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
    
    Adafruit_BMP280 bmp280;
    Adafruit_BMP085_Unified bmp180 = Adafruit_BMP085_Unified(10085);
    
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
    bool thirdPageDisplayed = false;
    int digitCount = 0;
    int selectedDigit = 0;
    int threeDigitNumber[3] = {0, 0, 0};
    
    bool processStarted = false;
    unsigned long lastUpdateTime = 0;
    
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
            y = (tft.height() - h) / 2 + 60;
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
    
        int barWidth = tft.width() - 40;
        int barHeight = 30;
        int barX = 20;
        int barY = (tft.height() / 2) + 40;
        tft.drawRect(barX, barY, barWidth, barHeight, BLACK);
    
        for (int i = 0; i <= barWidth; i += 2) {
            tft.fillRect(barX + 1, barY + 1, i, barHeight - 2, RED);
            delay(50);
        }
    
        displayThirdPage();
    }
    
    void displayThirdPage() {
        if (thirdPageDisplayed) return;
    
        thirdPageDisplayed = true;
        tft.fillScreen(WHITE);
        tft.setFont(&FreeSansBold18pt7b);
        const char* message1 = "Go to previous page: Puff X";
        const char* message2 = "Proceed to next page";
        const char* message3 = "in 15 seconds";
    
        int16_t x1, y1;
        uint16_t w, h;
        tft.getTextBounds(message1, 0, 0, &x1, &y1, &w, &h);
        int16_t x = (tft.width() - w) / 2;
        int16_t y = (tft.height() / 2) - 80;
        tft.setCursor(x, y);
        tft.setTextColor(BLACK);
        tft.print(message1);
    
        tft.getTextBounds(message2, 0, 0, &x1, &y1, &w, &h);
        x = (tft.width() - w) / 2;
        y = (tft.height() / 2) - 40;
        tft.setCursor(x, y);
        tft.setTextColor(BLACK);
        tft.print(message2);
    
        tft.getTextBounds(message3, 0, 0, &x1, &y1, &w, &h);
        x = (tft.width() - w) / 2;
        y = (tft.height() / 2);
        tft.setCursor(x, y);
        tft.setTextColor(BLACK);
        tft.print(message3);
    
        int barWidth = tft.width() - 40;
        int barHeight = 30;
        int barX = 20;
        int barY = (tft.height() / 2) + 40;
        tft.drawRect(barX, barY, barWidth, barHeight, BLACK);
    
        unsigned long startTime = millis();
        while (millis() - startTime < 15000) {
            int elapsed = millis() - startTime;
            int progress = map(elapsed, 0, 15000, 0, barWidth);
            tft.fillRect(barX + 1, barY + 1, progress, barHeight - 2, RED);
    
            if (bmp280Initialized && (elapsed % 1000 == 0)) {
                float currentPressure = bmp280.readPressure();
                float pressureChange = currentPressure - lastPressureBMP280;
    
                if (pressureChange >= pressureThresholdDigitBMP280) {
                    displaySecondPage();
                    return;
                }
    
                lastPressureBMP280 = currentPressure;
            }
    
            delay(50);
        }
    
        displayFourthPage("Next page: First digit");
    }
    
    void displayFourthPage(const char* message) {
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
        tft.fillScreen(WHITE);
        tft.setFont(&FreeSansBold24pt7b);
    
        const int cellWidth = tft.width() / 5;
        const int cellHeight = tft.height() / 2;
    
        const char* digits[] = {"0", "1", "2", "3", "4", "5", "6", "7", "8", "9"};
        const int numDigits =

//sketch2.ino 

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
    
    // Color definitions
    #define BLACK 0x0000
    #define WHITE 0xFFFF
    #define GREEN 0x07E0 // The green value that worked in your test code
    #define RED 0xF800  // Using a standard red value
    
    void setup() {
      Serial.begin(115200); // For serial monitor
      Serial2.begin(115200); // For communication with ESP32 (RX3, TX3)
    
      // Initialize the display
      uint16_t ID = tft.readID();
      tft.begin(ID);
      tft.setRotation(1); // Adjust rotation if needed
      tft.fillScreen(WHITE); // Set background to white
      tft.setFont(&FreeSansBold24pt7b); // Set the font to FreeSansBold24pt7b
    }
    
    void loop() {
      if (Serial2.available()) {
        String command = Serial2.readStringUntil('\n');
        command.trim(); // Remove any leading or trailing whitespace
        Serial.print("Received command: '");
        Serial.print(command);
        Serial.println("'");
    
        if (command == "Clear display" || command == "Clear Face ID") {
          tft.fillScreen(WHITE);
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
          tft.print(command);
        }
      }
    }
    
    