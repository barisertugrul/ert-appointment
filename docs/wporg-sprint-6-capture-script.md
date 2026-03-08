# Sprint 6 Çekim Scripti (30 dk)

Amaç:
- WP.org yükleme öncesi final kalite kontrol, dosyalama ve teslim paketini tamamlamak.

Süre:
- Toplam 30 dakika

---

## A) Dosya ve Adlandırma Kontrolü (8 dk)

Beklenen dosyalar:
- `banner-1544x500.png`
- `banner-772x250.png`
- `icon-256x256.png`
- `icon-128x128.png`
- `screenshot-1.png` ... `screenshot-7.png`

Adımlar:
1. Tüm dosyaları `assets/wporg/` altında topla.
2. İsimleri birebir doğrula (boşluk, büyük-küçük harf, uzantı).
3. Yanlış adları düzelt.

---

## B) Görsel Kalite Kontrolü (10 dk)

1. Tüm görselleri hızlı önizlemede tek tek aç.
2. Kontrol et:
   - Okunurluk
   - Kontrast
   - Kişisel veri sızıntısı
   - Kırık kadraj/kesik alan
3. Sorunlu görselleri not al ve hızlı revize et.

---

## C) Readme Eşleşme Kontrolü (6 dk)

1. `readme.txt` içindeki `== Screenshots ==` listesini aç.
2. `screenshot-1..7` sırasının metinle uyumunu doğrula.
3. Gerekirse sadece metni veya screenshot sırasını güncelle.

---

## D) SVN Öncesi Paketleme (6 dk)

1. Final dosyaların tek klasörde olduğundan emin ol:
   - `assets/wporg/*`
2. Yükleme notu hazırla:
   - Banner + icon + screenshot seti tamamlandı.
3. `docs/wporg-svn-publish.md` adımlarını takip etmeye hazır hale gel.

---

## Hızlı Kontrol Komutları (Opsiyonel)

PowerShell:
`Get-ChildItem assets/wporg | Select-Object Name, Length`

---

## Sprint Sonu Çıkış Kriteri

- [ ] WP.org `assets/` yüklemeye hazır final görsel paketi tamam.
- [ ] Screenshot sırası `readme.txt` ile uyumlu.
- [ ] SVN publish aşamasına geçilebilir.
