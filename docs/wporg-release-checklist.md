# WordPress.org Release Checklist (Lite)

Bu checklist, `ert-appointment` Lite sürümünü WordPress.org’a gönderirken son kontrol için hazırlanmıştır.

## A) Metadata ve sürüm uyumu

- [ ] `ert-appointment.php` içindeki `Version` güncel.
- [ ] `readme.txt` içindeki `Stable tag` aynı sürüm.
- [ ] `Requires at least`, `Requires PHP`, `Tested up to` değerleri güncel.
- [ ] `Text Domain` = `ert-appointment` ve `Domain Path` doğru.
- [ ] `readme.txt` içerik/özellik listesi Lite davranışıyla uyumlu.

## B) WP.org update uyumluluğu

- [ ] Eklenti klasör slug’ı sabit: `ert-appointment`.
- [ ] Özel update-checker kodu yok (`pre_set_site_transient_update_plugins`, harici update checker vb.).
- [ ] WordPress.org için ek `Update URI` header kullanılmıyor.

## C) Kod kalite ve güvenlik

- [ ] REST endpointlerde `permission_callback` mevcut.
- [ ] SQL çağrılarında güvenli yaklaşım korunuyor (`prepare`/`%i` ve gerekli PHPCS gerekçeleri).
- [ ] `uninstall.php` güvenlik guard içeriyor (`WP_UNINSTALL_PLUGIN`).
- [ ] PHP lint temiz (`php -l`).
- [ ] Frontend build temiz (`npm run build`).

## D) Dağıtım paketi (ZIP)

- [ ] Paket içinde dev dosyaları yok: `node_modules`, `tests`, `.git`, `package.json`, `vite.config.js` vb.
- [ ] `assets/dist` üretimi güncel.
- [ ] `languages` içindeki `.po/.mo` dosyaları güncel.
- [ ] ZIP temiz kurulumda aktivasyon testi geçti.

## E) Fonksiyonel smoke test

- [ ] Booking akışı (mode bazlı) manuel test edildi.
- [ ] Admin ayarları kayıt/yükleme test edildi.
- [ ] Notification şablonları kayıt/güncelleme test edildi.
- [ ] Pro pasifken Lite kısıtları doğru (ör. SMS/WhatsApp kanal seçimi disabled).

## E.1) WP.org assets hazırlığı

- [ ] `docs/wporg-assets-master-checklist.md` güncel.
- [ ] Progress script çalıştırıldı: `./scripts/update-wporg-assets-progress.ps1`
- [ ] Gerekirse alias kontrolü yapıldı: `./scripts/update-wporg-assets-progress.ps1 -ListSections`
- [ ] Banner/icon/screenshot seti `assets/wporg/` altında tamam.
- [ ] Assets isimleri WP.org formatına uygun (`banner-1544x500`, `banner-772x250`, `icon-256x256`, `icon-128x128`, `screenshot-1..7`).

## F) Gönderim öncesi son adımlar

- [ ] `Plugin Check` son tarama alındı.
- [ ] Kritik/hata seviyesinde açık bulgu yok.
- [ ] `CHANGELOG.md` ve `readme.txt` son değişiklikleri içeriyor.
- [ ] WP.org SVN release adımı için sürüm etiketi hazır.

---

## Hızlı karar kriteri

**Ready** demek için minimum:
1. Metadata sürüm eşleşmesi,
2. Temiz build + temiz ZIP,
3. Plugin Check kritik/hata yok,
4. Temel smoke test geçmiş olmalı,
5. WP.org assets checklist tamamlanmış olmalı.
