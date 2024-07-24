#include <Wire.h>
#include <RTClib.h>
#include <EEPROM.h>
#include <LiquidCrystal_I2C.h>
#include <ArduinoJson.h>  // Include the ArduinoJson library

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

void setup() {
  Serial.begin(115200);
  Serial2.begin(115200); // Serial2 for communication with ESP32
  lcd.init();
  lcd.backlight();

  Serial.println("Setup started");

  if (!rtc.begin()) {
    lcd.print("RTC failed");
    Serial.println("RTC failed");
    while (1);  // Halt if RTC not working
  }

  // Initialize RTC if it lost power
  if (rtc.lostPower()) {
    rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
    Serial.println("RTC lost power, time adjusted");
  }

  byte eepromInitFlag = EEPROM.read(EEPROM_MAGIC_NUMBER_ADDR);

  // Check EEPROM initialized flag
  if (eepromInitFlag != EEPROM_MAGIC_NUMBER) {
    // Fresh firmware upload or first run
    EEPROM.write(EEPROM_MAGIC_NUMBER_ADDR, EEPROM_MAGIC_NUMBER);
    EEPROM.write(EEPROM_TIMER_FLAG_ADDR, 0);  // Reset the flag
    lcd.clear();
    Serial.println("EEPROM initialized");
    timerStarted = false;
  } else {
    // Check if timer was already running
    if (EEPROM.read(EEPROM_TIMER_FLAG_ADDR) == 1) {
      timerStarted = true;
      Serial.println("Timer was running");
      checkTimerProgress(); // Check and display remaining time immediately
    } else {
      lcd.clear();
      Serial.println("Waiting for PIN");
      timerStarted = false;
    }
  }

  Serial.println("Setup completed");
}

void loop() {
  if (Serial2.available() > 0) {
    String command = Serial2.readStringUntil('\n');
    command.trim();

    Serial.print("Received from ESP32: ");
    Serial.println(command);

    if (command == "RESET") {
      resetSystem();
    } else if (command == "GET_TIME") {
      sendRemainingTime();
    } else if (!timerStarted) {
      String receivedPin = command;
      lcd.clear();
      lcd.print("PIN: ");
      lcd.print(receivedPin);

      // Start the 12-hour timer
      startTimer();
      timerStarted = true;
      EEPROM.write(EEPROM_TIMER_FLAG_ADDR, 1); // Set the flag to indicate timer is started
    }
  }

  if (timerStarted) {
    // Continuously check and display remaining time
    checkTimerProgress();
    delay(1000); // Update every second
  }
}

void resetSystem() {
  EEPROM.write(EEPROM_MAGIC_NUMBER_ADDR, 0);  // Reset the EEPROM
  EEPROM.write(EEPROM_TIMER_FLAG_ADDR, 0);
  lcd.clear();
  timerStarted = false;
  Serial.println("System reset");
}

void startTimer() {
  DateTime now = rtc.now();

  EEPROM.write(EEPROM_ADDRESS_YEAR, now.year() - 2000); // Store only the last two digits of the year
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

  // Calculate the end time by adding 12 hours to the start time
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
    EEPROM.write(EEPROM_TIMER_FLAG_ADDR, 0); // Reset the flag when timer finishes
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
