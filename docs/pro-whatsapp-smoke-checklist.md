# Pro WhatsApp/SMS Smoke Checklist

Bu kontrol listesi, **ERT Appointment Lite + Pro** kurulumunda WhatsApp/SMS entegrasyonunun görünürlük ve temel çalışma doğrulamasını hızlıca yapmak içindir.

## Önkoşullar

- Lite eklentisi aktif.
- Pro eklentisi aktif.
- Pro lisansı **valid**.
- Admin kullanıcı ile giriş yapılmış.

## 1) Entegrasyon kartlarının görünürlüğü

1. `Ayarlar → Integrations` sekmesini aç.
2. Aşağıdaki kartların göründüğünü doğrula:
   - `SMS (Twilio / NetGSM)`
   - `WhatsApp (Meta Cloud API)`
3. Pro pasif/invalid lisans senaryosunda kartların **görünüp**, alanların **kilitli (disabled)** olduğunu doğrula.

Beklenen:
- Kartlar her durumda görünür.
- Pro aktif değilse düzenleme kapalıdır.

## 2) SMS ayarları (Pro aktif)

1. `SMS Provider` alanında `Twilio` ve `NetGSM` seçeneklerini gör.
2. `Twilio` seçildiğinde alanlar:
   - `Twilio Account SID`
   - `Twilio Auth Token`
   - `Twilio From Number`
3. `NetGSM` seçildiğinde alanlar:
   - `NetGSM Usercode`
   - `NetGSM Password`
   - `NetGSM Header`

Beklenen:
- Sağlayıcı değişimine göre doğru alanlar görünür.
- `Save` sonrası değerler korunur.

## 3) WhatsApp ayarları (Pro aktif)

1. `WhatsApp Provider` alanında `Meta Cloud API` görünür.
2. Aşağıdaki alanları doldurup kaydet:
   - `Phone Number ID`
   - `Access Token`
   - `Graph API Version` (örn: `v21.0`)

Beklenen:
- Kaydetme başarılıdır.
- Sayfa yenilenince değerler geri yüklenir.

## 4) Notification kanal seçenekleri

1. `Notifications` sekmesine git.
2. Yeni template oluşturma ekranında `Template Channel` listesini aç.

Beklenen:
- Pro aktifken: `Email`, `SMS`, `WhatsApp` görünür.
- Pro pasifken: `WhatsApp` görünmez.

## 5) Quick Template Combos davranışı

1. `Quick Template Combos` ile bir event için şablon üret.
2. İlgili event altında oluşan kayıtları kontrol et.

Beklenen (Pro aktif):
- `customer/email` oluşturulur.
- `admin/email` oluşturulur.
- `customer/whatsapp` oluşturulur.
- `customer/whatsapp` şablonu başlangıçta `Inactive` (`is_active = 0`) gelir.

## 6) Varsayılan WhatsApp seed doğrulaması

> Bu adım yeni kurulum veya Repair sonrası doğrulama içindir.

1. `Settings` sayfasında `Repair Now` çalıştır.
2. `Notifications` sayfasını yenile.

Beklenen:
- Aşağıdaki event’ler için `customer/whatsapp` şablonları mevcuttur:
  - `appointment_pending`
  - `appointment_confirmed`
  - `appointment_cancelled`
  - `appointment_rescheduled`
  - `appointment_reminder_24h`

## 7) Basit gönderim testi (opsiyonel)

1. Test amaçlı bir müşteri kaydında telefon numarası olduğundan emin ol.
2. `customer/whatsapp` template’i `Active` yap.
3. Tetikleyici event’i oluştur (örn. `appointment_confirmed`).

Beklenen:
- Hata durumunda notification log’da başarısız kayıt görülür.
- Doğru credential/numara ile başarılı gönderim log’u oluşur.

## Notlar

- WhatsApp provider: **Meta Cloud API**
- SMS provider: **Twilio** / **NetGSM**
- Bu doküman smoke-level doğrulama içindir; kapsamlı E2E ve rate-limit/timeout testleri ayrı planlanmalıdır.
