# ERT Appointment — Live Booking Mode Test Report (One Page)

## QA Quick Start

Tek komutla manuel test + otomatik markdown rapor üretimi:

`powershell -ExecutionPolicy Bypass -File .\scripts\run-live-test-and-report.ps1 -Tester "Ad Soyad" -Environment "stage"`

Opsiyonel parametreler:

- `-OutputDir ".\test-results"`
- `-TemplateFile ".\docs\live-booking-mode-test-report.md"`

Çıktılar:

- JSON: `test-results/scope-override-YYYYMMDD-HHMMSS.json`
- MD: `test-results/live-report-YYYYMMDD-HHMMSS.md`

## Test Bilgileri

- **Tarih:** ____ / ____ / ______
- **Test Eden:** ____________________
- **Ortam:** ☐ Local ☐ Stage ☐ Prod
- **Plugin Sürümü / Commit:** ____________________
- **URL (Booking Sayfası):** ____________________

---

## Sonuç Özeti

- **Toplam Test:** 12
- **Pass:** ____
- **Fail:** ____
- **Skip:** ____
- **Genel Durum:** ☐ PASS ☐ FAIL

---

## 1) Persist Kontrolleri

| ID | Senaryo | Beklenen | Sonuç (P/F/S) | Not |
|---|---|---|---|---|
| PERSIST-001 | Global auto_confirm persist | Kaydet/yenile sonrası toggle ON kalır | ___ | ___ |
| PERSIST-002 | Global allow_general_booking persist | Kaydet/yenile sonrası toggle ON kalır | ___ | ___ |
| PERSIST-003 | Global arrival_reminder persist | Kaydet/yenile sonrası toggle ON kalır | ___ | ___ |

---

## 2) Override Kontrolleri

| ID | Senaryo | Beklenen | Sonuç (P/F/S) | Not |
|---|---|---|---|---|
| OVR-001 | Provider date range override | Takvim provider tarih aralığıyla sınırlanır | ___ | ___ |
| OVR-002 | Provider slot duration override | Slot üretimi provider duration değerini kullanır | ___ | ___ |
| OVR-003 | Provider buffer_after override | Slot başlangıçları duration+buffer_after kuralına uyar | ___ | ___ |
| OVR-004 | Department fallback | Provider’da yoksa department değeri uygulanır | ___ | ___ |
| OVR-005 | Global fallback | Provider/department yoksa global değer uygulanır | ___ | ___ |

---

## 3) Arrival + Slot Matematiği

| ID | Senaryo | Beklenen | Sonuç (P/F/S) | Not |
|---|---|---|---|---|
| ARR-001 | Arrival note visibility | Arrival notu senaryoya uygun görünür | ___ | ___ |
| SLOT-001 | Break overlap suppression | Break ile çakışan slotlar listelenmez | ___ | ___ |

---

## 4) Gutenberg Regresyon

| ID | Senaryo | Beklenen | Sonuç (P/F/S) | Not |
|---|---|---|---|---|
| GBLK-001 | Gutenberg block mount | "Rezervasyon formu yükleniyor..." sonrası widget açılır | ___ | ___ |
| GBLK-002 | Multiple booking hosts | Birden fazla host bağımsız mount olur, sonsuz loading olmaz | ___ | ___ |

---

## Fail Detayı (Zorunlu)

- **ID:** __________
- **Repro Steps:**
  1. ____________________
  2. ____________________
  3. ____________________
- **Beklenen:** ____________________
- **Gerçekleşen:** ____________________
- **Ekran Görüntüsü / Video:** ____________________

---

## Onay

- **QA Onayı:** ☐ Verildi ☐ Verilmedi
- **Karar:** ☐ Yayına Uygun ☐ Düzeltme Gerekli
- **Ek Not:** ____________________________________________
