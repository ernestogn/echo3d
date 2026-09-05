#include <WiFi.h>
#include <WebServer.h>

const char* ssid     = "Servidor2 ";
const char* password = "diskpart";

#define LED_PIN 8  // LED integrado del ESP32-C3 Super Mini

WebServer server(80);

bool ledEstado = false;
unsigned long ultimoBlink = 0;
const int intervaloBlink = 1000;  // 1 segundo

void handleRoot() {
  String html = "<html><body>";
  html += "<h1>ESP32-C3 Super Mini</h1>";
  html += "<p>Estado del LED: <b>" + String(ledEstado ? "ENCENDIDO" : "APAGADO") + "</b></p>";
  html += "<p>Uptime: " + String(millis() / 1000) + " segundos</p>";
  html += "<p>Señal Wi-Fi: " + String(WiFi.RSSI()) + " dBm</p>";
  html += "<meta http-equiv='refresh' content='1'>";  // auto-refresca cada 1 seg
  html += "</body></html>";
  server.send(200, "text/html", html);
}

void setup() {
  Serial.begin(115200);
  pinMode(LED_PIN, OUTPUT);
  delay(2000);

  WiFi.persistent(false);
  WiFi.disconnect(true, true);
  delay(1000);
  WiFi.mode(WIFI_STA);
  WiFi.setTxPower(WIFI_POWER_8_5dBm);
  WiFi.begin(ssid, password);

  Serial.print("Conectando");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.printf("\n✅ Conectado. IP: %s\n", WiFi.localIP().toString().c_str());
  Serial.printf("Abrí http://%s en tu navegador\n", WiFi.localIP().toString().c_str());

  server.on("/", handleRoot);
  server.begin();
}

void loop() {
  // Blink
  if (millis() - ultimoBlink >= intervaloBlink) {
    ultimoBlink = millis();
    ledEstado = !ledEstado;
    digitalWrite(LED_PIN, ledEstado);
  }

  // Atender requests web
  server.handleClient();
} 