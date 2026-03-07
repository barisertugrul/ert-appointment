# ERT Appointment — Lite

WordPress için randevu rezervasyon eklentisi. Bölümler (departmanlar), sağlayıcılar (doktor, uzman, birim vb.) ve özelleştirilebilir formlarla tam özellikli rezervasyon sistemi.

---

## Gereksinimler

| Gereksinim | Minimum |
|---|---|
| WordPress | 6.0+ |
| PHP | 8.1+ |
| MySQL | 5.7+ / MariaDB 10.3+ |

---

## Kurulum

### 1. Dosyaları yükle

```bash
# ert-appointment/ klasörünü şuraya koy:
wp-content/plugins/ert-appointment/
```

### 2. Bağımlılıkları yükle

```bash
cd wp-content/plugins/ert-appointment
composer install --no-dev
npm install && npm run build
```

### 3. WordPress'ten aktive et

**Eklentiler → ERT Appointment → Aktive Et**

Aktifleştirme sırasında veritabanı tabloları otomatik oluşturulur.

---

## Yapılandırma

### Genel Ayarlar

**ERT Appointment → Ayarlar → Genel**

| Ayar | Açıklama | Varsayılan |
|---|---|---|
| Slot süresi | Rezervasyon dilimi dakikası | 30 dk |
| Buffer (önce) | Randevular arası boşluk | 0 dk |
| Buffer (sonra) | Randevular arası boşluk | 0 dk |
| Min. ihbar süresi | Kaç saat öncesine kadar rezervasyon | 1 saat |
| Max. ileri tarih | Kaç güne kadar rezervasyon alınır | 60 gün |
| Otomatik onay | Onay gerektirmeden kabul et | Kapalı |

### Çalışma Saatleri

**ERT Appointment → Çalışma Saatleri**

- **Global**: Tüm sağlayıcılara uygulanan varsayılan saatler
- **Bölüm bazlı**: Belirli bir departmana özel saatler
- **Sağlayıcı bazlı**: Bireysel sağlayıcı saatleri

Öncelik sırası: Sağlayıcı > Bölüm > Global

---

## Kısa Kodlar (Shortcodes)

### Rezervasyon Formu

```
[erta_booking]
[erta_booking department="klinik" provider="5"]
[erta_booking form="3"]
```

### Bölüm Listesi

```
[erta_departments]
[erta_departments columns="3"]
```

### Sağlayıcı Listesi

```
[erta_providers]
[erta_providers department="5" show_avatar="true"]
```

### Müşteri Randevularım

```
[erta_my_appointments]
```

---

## REST API

Tüm endpointler `https://siteniz.com/wp-json/erta/v1/` prefix'ini kullanır.

### Public Endpointler

| Method | Endpoint | Açıklama |
|---|---|---|
| GET | `/departments` | Bölüm listesi |
| GET | `/providers?department_id=` | Sağlayıcı listesi |
| GET | `/providers/{id}/slots?date=` | Müsait slotlar |
| GET | `/providers/{id}/calendar?from=&to=` | Takvim görünümü |
| POST | `/appointments` | Yeni rezervasyon oluştur |
| GET | `/appointments/{id}` | Rezervasyon detayı |
| PUT | `/appointments/{id}/cancel` | İptal et |

### Admin Endpointler (`erta_manage_all` yetkisi gerekli)

| Method | Endpoint | Açıklama |
|---|---|---|
| GET/POST | `/admin/settings` | Ayarları oku/yaz |
| GET/POST/PUT/DELETE | `/admin/departments` | Bölüm yönetimi |
| GET/POST/PUT/DELETE | `/admin/providers` | Sağlayıcı yönetimi |
| GET/POST/PUT/DELETE | `/admin/forms` | Form yönetimi |
| GET/POST/PUT | `/admin/notification-templates` | Bildirim şablonları |
| GET/POST | `/admin/working-hours` | Çalışma saatleri |
| GET/POST | `/admin/breaks` | Molalar |
| GET/POST | `/admin/special-days` | Özel günler |

---

## Hooks (Filtreler ve Aksiyonlar)

### Aksiyonlar

```php
// Rezervasyon oluşturulduğunda
do_action('erta_appointment_created', Appointment $appointment);

// Onaylandığında
do_action('erta_appointment_confirmed', Appointment $appointment);

// İptal edildiğinde
do_action('erta_appointment_cancelled', Appointment $appointment);

// Yeniden planlandığında
do_action('erta_appointment_rescheduled', Appointment $new, Appointment $old);

// Ödemeyle onaylandığında (Pro)
do_action('erta_appointment_confirmed_by_payment', int $appointmentId);
```

### Filtreler

```php
// Bildirim şablonu placeholder değerleri
add_filter('erta_template_placeholders', function(array $context, Appointment $appt): array {
    $context['my_key'] = 'değer';
    return $context;
}, 10, 2);

// Bildirim editörü placeholder ipuçları
add_filter('erta_available_placeholder_hints', function(array $hints): array {
    $hints[] = ['token' => '{{my_key}}', 'description' => 'Açıklama'];
    return $hints;
});

// Pro aktif mi?
$isPro = apply_filters('erta_is_pro_active', false);

// REST API init (Pro eklentisi buraya hook atar)
do_action('erta_rest_api_init', Container $container);
```

---

## Geliştirici Notları

### Servis Katmanı

```
src/
├── Core/           — Plugin bootstrap, DI container, installer
├── Domain/         — İş mantığı (Appointment, Provider, Department...)
│   ├── Appointment/
│   ├── Department/
│   ├── Form/
│   ├── Notification/
│   ├── Provider/
│   └── Schedule/
├── Api/Controllers/ — REST endpoint controller'ları
├── Settings/        — SettingsManager (scope-aware)
└── Infrastructure/  — WP implementasyonları (Repository'ler)
```

### Yeni Gateway Eklemek (Pro)

```php
// PaymentGatewayInterface'i implemente et
class MyGateway implements PaymentGatewayInterface {
    public function getName(): string { return 'my_gateway'; }
    public function createPaymentUrl(Appointment $a, float $amount, string $currency): string { ... }
    public function handleWebhook(WP_REST_Request $req): WP_REST_Response { ... }
}

// ProPlugin'de kayıt et
add_filter('erta_payment_gateways', function(array $gateways): array {
    $gateways['my_gateway'] = MyGateway::class;
    return $gateways;
});
```

---

## Lisans

GPLv2 or later
