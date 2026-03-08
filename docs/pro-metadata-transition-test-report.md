# ERT Appointment — Pro Metadata Transition Smoke Report

## Quick Start

Tek komutla manuel test + markdown rapor üretimi:

`powershell -ExecutionPolicy Bypass -File .\scripts\run-pro-metadata-transition-and-report.ps1 -Tester "Ad Soyad" -Environment "stage"`

Çıktılar:

- JSON: `test-results/pro-metadata-transition-YYYYMMDD-HHMMSS.json`
- MD: `test-results/pro-metadata-transition-report-YYYYMMDD-HHMMSS.md`

---

## Test Bilgileri

- **Tarih:** ____ / ____ / ______
- **Test Eden:** ____________________
- **Ortam:** ☐ Local ☐ Stage ☐ Prod
- **Lite Sürüm / Commit:** ____________________
- **Pro Sürüm / Commit:** ____________________

---

## Sonuç Özeti

- **Toplam Test:** 12
- **Pass:** ____
- **Fail:** ____
- **Skip:** ____
- **Genel Durum:** ☐ PASS ☐ FAIL

---

## 1) Scenario A — Lite Only

| ID | Senaryo | Beklenen | Sonuç (P/F/S) | Not |
|---|---|---|---|---|
| LITE-001 | Form kaydı (global) | Form kayıt/güncelleme sorunsuz | ___ | ___ |
| LITE-002 | Booking akışı | Wizard adımları ve submit sorunsuz | ___ | ___ |
| LITE-003 | Form API shape | `department_label`, `provider_label`, `ui_styles` alanları response'ta bozulmadan gelir | ___ | ___ |

---

## 2) Scenario B — Pro Active (Valid License)

| ID | Senaryo | Beklenen | Sonuç (P/F/S) | Not |
|---|---|---|---|---|
| PRO-001 | Label/style kaydet | Pro açıkken form label/style kaydı yapılır | ___ | ___ |
| PRO-002 | Frontend apply | Label override ve ui style frontendde uygulanır | ___ | ___ |
| PRO-003 | Akış sağlamlığı | Booking + notification/payment hook akışı etkilenmez | ___ | ___ |

---

## 3) Scenario C — Pro OFF → ON (Transition)

| ID | Senaryo | Beklenen | Sonuç (P/F/S) | Not |
|---|---|---|---|---|
| TRON-001 | Eski kayıtların okunması | Geçmiş metadata values Pro açıkken okunur | ___ | ___ |
| TRON-002 | Yeni kaydın devamlılığı | Pro açıkken yapılan kayıt refresh sonrası korunur | ___ | ___ |
| TRON-003 | API/shortcode uyumu | Endpoint ve shortcode output kırılmaz | ___ | ___ |

---

## 4) Scenario D — Pro ON → OFF (Fallback)

| ID | Senaryo | Beklenen | Sonuç (P/F/S) | Not |
|---|---|---|---|---|
| TROFF-001 | UI gate | Pro kapalıyken Pro alanları disabled/korumalı | ___ | ___ |
| TROFF-002 | Lite fallback read | Pro kapalıyken Lite fallback ile temel akış bozulmaz | ___ | ___ |
| TROFF-003 | Booking güvenliği | Form submit/appointment create normal çalışır | ___ | ___ |

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
