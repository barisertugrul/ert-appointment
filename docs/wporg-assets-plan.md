# WP.org Assets Plan (ERT Appointment)

Bu doküman, WordPress.org plugin sayfası için gerekli görsel varlıkların (banner, icon, screenshot) üretim planını ve önerilen içerik metinlerini içerir.

## 1) Gerekli Dosyalar ve Boyutlar

### Banner
- `banner-1544x500.png` (retina)
- `banner-772x250.png` (standard)

### Icon
- `icon-256x256.png`
- `icon-128x128.png`

### Screenshots
- `screenshot-1.png`
- `screenshot-2.png`
- `screenshot-3.png`
- `screenshot-4.png`
- `screenshot-5.png`
- `screenshot-6.png`
- `screenshot-7.png`

> Not: `readme.txt` içindeki Screenshots listesi bu sıra ile eşleşmelidir.

---

## 2) Banner Kompozisyon Brief’i

## Amaç
İlk bakışta “Bu eklenti ne yapar?” sorusuna cevap vermek ve güven vermek.

## Yerleşim (1544x500)
- Sol %55: Frontend booking form ekranı (service/provider + date/time adımı)
- Sağ %45: Başlık + kısa alt metin + 3–5 özellik rozeti

## Önerilen Başlık ve Metin
- Başlık: `Appointment Booking for WordPress`
- Alt metin: `Fast, mobile-friendly and flexible booking workflows`

## Önerilen Rozetler
- `Email Notifications`
- `SMS & WhatsApp (Pro)`
- `Google Calendar & Zoom (Pro)`
- `Custom Forms`
- `REST API`

## Görsel Stil
- Aşırı detaydan kaçın; sade, modern, okunaklı.
- Kontrast yüksek olsun; metin küçük ekranlarda da okunabilsin.
- Marka renkleri mevcut plugin/admin görünümü ile uyumlu olsun.

---

## 3) Icon Kompozisyon Brief’i

## Amaç
Küçük boyutta bile “randevu/takvim” çağrışımını net vermek.

## Öneri
- Sembol: takvim + saat birleşimi
- Stil: düz, kalın hatlı, minimal
- Renk: tek ana renk + beyaz ikon/simge
- Metin: icon içine yazı koyma (128px’te okunmaz)

## Kontrol Listesi
- 128px’te net okunuyor mu?
- Koyu/açık arka planda kontrast yeterli mi?
- Fazla ince çizgi var mı? (varsa kalınlaştır)

---

## 4) Screenshot Planı (WP.org sıralı)

## screenshot-1.png
- Konu: Frontend booking form (service/provider + date/time)
- Amaç: Kullanıcı akışını ilk bakışta göstermek

## screenshot-2.png
- Konu: Frontend form customer info + submit adımı
- Amaç: Tamamlanabilir akışın devamını göstermek

## screenshot-3.png
- Konu: Admin dashboard / appointments overview
- Amaç: Yönetim panelinin operasyon görünümü

## screenshot-4.png
- Konu: Forms page (drag-drop) + booking button text özelleştirme
- Amaç: Özelleştirilebilir form kabiliyetini göstermek

## screenshot-5.png
- Konu: Notifications page (email/sms/whatsapp channels)
- Amaç: Bildirim kabiliyetini ve kanal yapısını göstermek

## screenshot-6.png
- Konu: Settings > Integrations (Google Calendar, Zoom, SMS, WhatsApp)
- Amaç: Entegrasyon genişliğini göstermek

## screenshot-7.png
- Konu: Mobil ekran görüntüsü (responsive booking)
- Amaç: Mobil uyumluluk vurgusu

---

## 5) Çekim ve Düzenleme Kuralları

- Kişisel veri göstermeyin (ad, telefon, e-posta anonim olsun).
- Aynı dil ve terminoloji kullanın (UI metinleri tutarlı olsun).
- Tüm screenshot’larda benzer zoom/ölçek kullanın.
- Önerilen çıktı formatı: PNG.
- Önerilen çalışma çözünürlüğü: 1280x960 veya 1365x768; ardından gerekirse optimize edin.

---

## 6) Teslim Paket Önerisi

Tasarımcıdan/ekipten şu yapıda teslim alın:

- `/assets/wporg/banner-1544x500.png`
- `/assets/wporg/banner-772x250.png`
- `/assets/wporg/icon-256x256.png`
- `/assets/wporg/icon-128x128.png`
- `/assets/wporg/screenshot-1.png` ... `/assets/wporg/screenshot-7.png`

Bu dosyalar daha sonra WP.org SVN `assets/` dizinine aynı isimlerle yüklenir.
