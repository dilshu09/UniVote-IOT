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
