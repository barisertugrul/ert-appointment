# WP.org Assets Master Checklist

Bu dosya, tüm üretim sürecini tek ekrandan takip etmek için hazırlanmıştır.

## Genel İlerleme

- Durum: `%0 tamamlandı`
- Son güncelleme: `2026-03-08`

Güncelleme kuralı (manuel):
- Sprint 1 tamam: `%16`
- Sprint 2 tamam: `%33`
- Sprint 3 tamam: `%50`
- Sprint 4 tamam: `%66`
- Sprint 5 tamam: `%83`
- Sprint 6 tamam: `%100`

Otomatik güncelleme (PowerShell):
- `./scripts/update-wporg-assets-progress.ps1`
- (Opsiyonel özel yol) `./scripts/update-wporg-assets-progress.ps1 -ChecklistPath "docs/wporg-assets-master-checklist.md"`
- (Güncelle + dosyayı aç) `./scripts/update-wporg-assets-progress.ps1 -OpenFile`
- (Güncelle + VS Code aktif sekmede aç) `./scripts/update-wporg-assets-progress.ps1 -OpenInCode`
- (Güncelle + VS Code + başlığa git) `./scripts/update-wporg-assets-progress.ps1 -OpenInCode -OpenHeading "## Durum Özeti"`
- (Kodlama güvenli kısa kullanım) `./scripts/update-wporg-assets-progress.ps1 -OpenInCode -OpenHeading "## Durum"`
- (Alias ile sprint/section aç) `./scripts/update-wporg-assets-progress.ps1 -OpenInCode -OpenSection s4`
- (Alias örnekleri) `progress`, `status`, `today`, `tomorrow`, `s1`..`s6`, `final`
- (Alias listesini yazdır) `./scripts/update-wporg-assets-progress.ps1 -ListSections`

DOCX tutarlılık temizleme (internal):
- [docs/internal-docx-maintenance.md](docs/internal-docx-maintenance.md)

## Bugün Hedefi (Hızlı Odak)

Bugün için önerilen minimum hedef (90-120 dk):

- [ ] Sprint 1 tamamla (`screenshot-1`, `screenshot-2`)
- [ ] Sprint 2 tamamla (`screenshot-3`, `screenshot-4`)
- [ ] Sprint 3'ten en az `screenshot-5` ve `screenshot-6` üret

Bugün için önerilen ideal hedef (2.5-3 saat):

- [ ] Sprint 1 tamamlandı
- [ ] Sprint 2 tamamlandı
- [ ] Sprint 3 tamamlandı (tüm `screenshot-1..7` bitti)

Bugün sonunda kontrol:

- [ ] `assets/wporg/` altında en az `screenshot-1..6` hazır
- [ ] Mümkünse `screenshot-7` ile birlikte set tamamlandı
- [ ] Yarın için Sprint 4 (ana banner) hazır notu bırakıldı

## Yarın Hedefi (Sprint 4-6 Odak)

Yarın için önerilen minimum hedef (90-120 dk):

- [ ] Sprint 4 tamamla (`banner-1544x500`)
- [ ] Sprint 5'ten `banner-772x250` ve `icon-256x256` üret
- [ ] Sprint 6'dan dosya adı/klasör kontrolünü yap

Yarın için önerilen ideal hedef (2-2.5 saat):

- [ ] Sprint 4 tamamlandı
- [ ] Sprint 5 tamamlandı (`banner-772x250`, `icon-256x256`, `icon-128x128`)
- [ ] Sprint 6 tamamlandı (final QA + paketleme)

Yarın sonunda kontrol:

- [ ] `assets/wporg/` altındaki tüm dosyalar tamam
- [ ] `readme.txt` screenshot sırası ile görseller uyumlu
- [ ] SVN yükleme için yalnızca publish adımı kaldı

## Durum Özeti

- [ ] Sprint 1 tamamlandı
- [ ] Sprint 2 tamamlandı
- [ ] Sprint 3 tamamlandı
- [ ] Sprint 4 tamamlandı
- [ ] Sprint 5 tamamlandı
- [ ] Sprint 6 tamamlandı
- [ ] WP.org assets paketi tamamlandı
- [ ] SVN publish’e hazır

---

## Sprint 1 — Frontend Screenshot Başlangıcı

Referans: [docs/wporg-sprint-1-capture-script.md](docs/wporg-sprint-1-capture-script.md)

- [ ] screenshot-1 üretildi
- [ ] screenshot-2 üretildi
- [ ] Dosyalar assets/wporg altına taşındı
- [ ] Görsel kalite kontrolü yapıldı

---

## Sprint 2 — Admin Overview + Forms

Referans: [docs/wporg-sprint-2-capture-script.md](docs/wporg-sprint-2-capture-script.md)

- [ ] screenshot-3 üretildi
- [ ] screenshot-4 üretildi
- [ ] Dosyalar assets/wporg altına taşındı
- [ ] Görsel kalite kontrolü yapıldı

---

## Sprint 3 — Notifications + Integrations + Mobile

Referans: [docs/wporg-sprint-3-capture-script.md](docs/wporg-sprint-3-capture-script.md)

- [ ] screenshot-5 üretildi
- [ ] screenshot-6 üretildi
- [ ] screenshot-7 üretildi
- [ ] Screenshot seti (1..7) tamamlandı

---

## Sprint 4 — Ana Banner

Referans: [docs/wporg-sprint-4-capture-script.md](docs/wporg-sprint-4-capture-script.md)

- [ ] banner-1544x500 üretildi
- [ ] Metin okunurluğu kontrol edildi
- [ ] Dosya assets/wporg altına taşındı

---

## Sprint 5 — Küçük Banner + Icon Seti

Referans: [docs/wporg-sprint-5-capture-script.md](docs/wporg-sprint-5-capture-script.md)

- [ ] banner-772x250 üretildi
- [ ] icon-256x256 üretildi
- [ ] icon-128x128 üretildi
- [ ] Tüm dosyalar assets/wporg altına taşındı

---

## Sprint 6 — Final QA + Paketleme

Referans: [docs/wporg-sprint-6-capture-script.md](docs/wporg-sprint-6-capture-script.md)

- [ ] Dosya adlandırmaları doğrulandı
- [ ] Görsellerin okunurluk/kontrast kontrolü yapıldı
- [ ] Kişisel veri sızıntısı kontrol edildi
- [ ] readme screenshot sırası ile eşleşme doğrulandı
- [ ] Final paket hazırlandı

---

## Final Teslim Listesi (WP.org Assets)

- [ ] assets/wporg/banner-1544x500.png
- [ ] assets/wporg/banner-772x250.png
- [ ] assets/wporg/icon-256x256.png
- [ ] assets/wporg/icon-128x128.png
- [ ] assets/wporg/screenshot-1.png
- [ ] assets/wporg/screenshot-2.png
- [ ] assets/wporg/screenshot-3.png
- [ ] assets/wporg/screenshot-4.png
- [ ] assets/wporg/screenshot-5.png
- [ ] assets/wporg/screenshot-6.png
- [ ] assets/wporg/screenshot-7.png

---

## Yayın Öncesi Son Adım

SVN yükleme adımları için: [docs/wporg-svn-publish.md](docs/wporg-svn-publish.md)
