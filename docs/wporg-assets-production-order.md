# WP.org Assets Üretim Sırası

Bu plan, WordPress.org görsellerini en hızlı ve en az revizyonla üretmek için önerilen sıradır.

## 0) Ön Hazırlık (10-15 dk)

- [ ] `readme.txt` içindeki Screenshots listesini referans al.
- [ ] Kullanılacak UI durumlarını belirle (demo veri, anonim isim/telefon/e-posta).
- [ ] Marka renkleri, font ve logo varyantını sabitle.
- [ ] Çıktı klasörü oluştur: `/assets/wporg/`.

Hedef çıktı isimleri:
- `banner-1544x500.png`
- `banner-772x250.png`
- `icon-256x256.png`
- `icon-128x128.png`
- `screenshot-1.png` ... `screenshot-7.png`

---

## 1) İlk Önce Screenshot’ları Üret (en kritik adım)

Neden önce screenshot?
- Banner içinde gerçek UI kullanacağın için önce ekran görüntülerinin hazır olması revizyonu azaltır.

Sıra:
1. `screenshot-1.png` — Frontend: service/provider + date/time
2. `screenshot-2.png` — Frontend: customer details + submit
3. `screenshot-3.png` — Admin dashboard/appointments
4. `screenshot-4.png` — Forms page + booking button text özelleştirme
5. `screenshot-5.png` — Notifications: email/sms/whatsapp channels
6. `screenshot-6.png` — Integrations page
7. `screenshot-7.png` — Mobil booking görünümü

Her screenshot için mini kontrol:
- [ ] Kişisel veri anonim mi?
- [ ] Metinler okunur mu?
- [ ] Aynı zoom/ölçek korunuyor mu?

---

## 2) Sonra Ana Banner (1544x500)

Girdi:
- Adım 1’de üretilen gerçek screenshot’lar (özellikle `screenshot-1.png` veya `screenshot-2.png`).

Uygulama:
- [ ] Sol tarafa gerçek booking UI yerleştir.
- [ ] Sağa başlık + alt metin + rozetleri ekle.
- [ ] Metin güvenli alanını koru (kenarlardan nefes payı bırak).

Önerilen metin:
- Başlık: `Appointment Booking for WordPress`
- Alt metin: `Fast, mobile-friendly and flexible booking workflows`

Rozetler:
- `Email Notifications`
- `SMS & WhatsApp (Pro)`
- `Google Calendar & Zoom (Pro)`
- `Custom Forms`
- `REST API`

Kontrol:
- [ ] 100% zoom’da net mi?
- [ ] Küçük ekranda metinler hâlâ okunuyor mu?

---

## 3) Banner’dan Küçük Versiyon Türet (772x250)

- [ ] `banner-1544x500` tasarımını kopyala.
- [ ] 772x250’a yeniden kadrajla.
- [ ] Gerekirse rozetleri 3 adede düşür.
- [ ] Başlık/alt metin okunaklılığını tekrar test et.

---

## 4) Icon Üretimi (256 -> 128)

- [ ] Önce `icon-256x256.png` tasarla (takvim + saat, metinsiz, yüksek kontrast).
- [ ] Sonra `icon-128x128.png` türet.
- [ ] 128 boyutta çizgiler kayboluyorsa sadeleştir/kalınlaştır.

Kontrol:
- [ ] 128 boyutta tek bakışta “appointment” çağrışımı var mı?
- [ ] Arka planla kontrast yeterli mi?

---

## 5) Paketleme ve Adlandırma Kontrolü (10 dk)

- [ ] Dosya isimleri birebir doğru mu?
- [ ] Tüm dosyalar PNG mi?
- [ ] `readme.txt` Screenshots sırası ile dosyalar aynı mı?

Kontrol komutu (isteğe bağlı):
- PowerShell: `Get-ChildItem assets/wporg | Select-Object Name, Length`

---

## 6) SVN Yükleme Öncesi Son Kontrol

- [ ] Banner ve icon dosyaları WP.org `assets/` için hazır.
- [ ] Screenshot’lar net ve sıralı.
- [ ] `readme.txt` açıklamalarla görseller tutarlı.

Sonraki adım:
- `docs/wporg-svn-publish.md` dosyasındaki adımlarla `assets/` içine yükle.

---

## Önerilen Zaman Planı (Toplam ~2-3 saat)

- Hazırlık: 15 dk
- 7 screenshot: 60-90 dk
- Ana banner: 30-40 dk
- Küçük banner: 15-20 dk
- Icon seti: 20-30 dk
- Son kontrol/paketleme: 10-15 dk

---

## 30 Dakikalık Sprint Planı (Operasyonel)

Bu planı tek oturumda uygularsan toplam 6 sprintte tamamlayabilirsin.

### Sprint 1 (00:00-00:30) — Hazırlık + İlk 2 Screenshot
- [ ] Demo veri ve anonim içerikleri hazırla.
- [ ] `screenshot-1.png` üret (frontend service/provider + date/time).
- [ ] `screenshot-2.png` üret (frontend customer info + submit).

Çıkış kriteri:
- [ ] İlk iki frontend screenshot net ve kullanılabilir.

### Sprint 2 (00:30-01:00) — Admin Akışı Screenshot’ları
- [ ] `screenshot-3.png` üret (dashboard/appointments).
- [ ] `screenshot-4.png` üret (forms + booking button text).

Çıkış kriteri:
- [ ] Admin tarafı temel operasyonu gösteren iki ekran hazır.

### Sprint 3 (01:00-01:30) — Entegrasyon + Mobil Screenshot
- [ ] `screenshot-5.png` üret (notifications channels).
- [ ] `screenshot-6.png` üret (integrations).
- [ ] `screenshot-7.png` üret (mobil görünüm).

Çıkış kriteri:
- [ ] Tüm 7 screenshot tamamlandı.

### Sprint 4 (01:30-02:00) — Ana Banner
- [ ] `banner-1544x500.png` tasarla.
- [ ] Gerçek UI screenshot’ı banner içine yerleştir.
- [ ] Başlık/alt metin/rozetleri sonlandır.

Çıkış kriteri:
- [ ] Ana banner üretime hazır.

### Sprint 5 (02:00-02:30) — Küçük Banner + Icon Seti
- [ ] `banner-772x250.png` türet ve kadrajla.
- [ ] `icon-256x256.png` üret.
- [ ] `icon-128x128.png` türet ve okunurluk kontrolü yap.

Çıkış kriteri:
- [ ] Banner seti ve icon seti tamamlandı.

### Sprint 6 (02:30-03:00) — QA + Paketleme
- [ ] Dosya adlarını doğrula.
- [ ] `readme.txt` screenshot sırası ile eşleştir.
- [ ] `/assets/wporg/` içine final dosyaları topla.

Çıkış kriteri:
- [ ] WP.org `assets/` yüklemeye hazır final paket tamam.
