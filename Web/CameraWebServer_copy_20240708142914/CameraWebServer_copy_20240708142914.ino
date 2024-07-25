#include "esp_camera.h"
#include <WiFi.h>

// Define your camera model
#define CAMERA_MODEL_AI_THINKER // Has PSRAM
#include "camera_pins.h"

// WiFi credentials
const char *ssid = "Toji";
const char *password = "toji2992";

void startCameraServer();
void setupLedFlash(int pin);

void setup() {
  Serial.begin(115200);
  Serial.setDebugOutput(true);
  Serial.println();

  camera_config_t config;
  config.ledc_channel = LEDC_CHANNEL_0;
  config.ledc_timer = LEDC_TIMER_0;
  config.pin_d0 = Y2_GPIO_NUM;
  config.pin_d1 = Y3_GPIO_NUM;
  config.pin_d2 = Y4_GPIO_NUM;
  config.pin_d3 = Y5_GPIO_NUM;
  config.pin_d4 = Y6_GPIO_NUM;
  config.pin_d5 = Y7_GPIO_NUM;
  config.pin_d6 = Y8_GPIO_NUM;
  config.pin_d7 = Y9_GPIO_NUM;
  config.pin_xclk = XCLK_GPIO_NUM;
  config.pin_pclk = PCLK_GPIO_NUM;
  config.pin_vsync = VSYNC_GPIO_NUM;
  config.pin_href = HREF_GPIO_NUM;
  config.pin_sccb_sda = SIOD_GPIO_NUM;
  config.pin_sccb_scl = SIOC_GPIO_NUM;
  config.pin_pwdn = PWDN_GPIO_NUM;
  config.pin_reset = RESET_GPIO_NUM;
  config.xclk_freq_hz = 20000000;
  config.frame_size = FRAMESIZE_SVGA;  // Use SVGA for higher resolution
  config.pixel_format = PIXFORMAT_JPEG;  // for streaming
  config.grab_mode = CAMERA_GRAB_WHEN_EMPTY;
  config.fb_location = CAMERA_FB_IN_PSRAM;
  config.jpeg_quality = 10;  // Adjust for higher quality
  config.fb_count = 2;  // Use two frame buffers for better performance

  esp_err_t err = esp_camera_init(&config);
  if (err != ESP_OK) {
    Serial.printf("Camera init failed with error 0x%x", err);
    return;
  }

  sensor_t *s = esp_camera_sensor_get();

  // Common sensor settings for OV2640
  s->set_brightness(s, 0);    // default brightness
  s->set_contrast(s, 0);      // default contrast
  s->set_saturation(s, 0);    // default saturation
  s->set_special_effect(s, 0);  // no effect
  s->set_whitebal(s, 1);      // enable auto white balance
  s->set_awb_gain(s, 1);      // enable AWB gain
  s->set_wb_mode(s, 0);       // auto white balance mode
  s->set_exposure_ctrl(s, 1); // enable exposure control
  s->set_aec2(s, 0);          // disable AEC2
  s->set_ae_level(s, 0);      // default AE level
  s->set_aec_value(s, 300);   // default AEC value
  s->set_agc_gain(s, 0);      // default AGC gain
  s->set_gainceiling(s, (gainceiling_t)2); // default gain ceiling
  s->set_bpc(s, 1);           // enable bad pixel correction
  s->set_wpc(s, 1);           // enable white pixel correction
  s->set_raw_gma(s, 1);       // enable raw gamma
  s->set_lenc(s, 1);          // enable lens correction
  s->set_hmirror(s, 0);       // disable horizontal mirror
  s->set_vflip(s, 1);         // enable vertical flip
  s->set_dcw(s, 1);           // enable DCW
  s->set_colorbar(s, 0);      // disable color bar

  WiFi.begin(ssid, password);
  WiFi.setSleep(false);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("");
  Serial.println("WiFi connected");

  startCameraServer();

  Serial.print("Camera Ready! Use 'http://");
  Serial.print(WiFi.localIP());
  Serial.println("' to connect");
}

void loop() {
  // Do nothing. Everything is done in another task by the web server
  delay(10000);
}
