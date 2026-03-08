# Internal DOCX Maintenance Guide

Bu doküman, DOCX dosyalarında formatı bozmadan metin/tutarlılık temizliği için iç kullanım rehberidir.

## Amaç

- Lite ve Pro DOCX dosyalarında tekrar eden paragraf ve bilinen metin tutarsızlıklarını temizlemek
- Tema/şablon yapısını koruyarak yalnızca hedef metinlere müdahale etmek

## Script

- `scripts/fix-pro-docx-consistency.py`

## Hedef Dosya Grupları

- Lite TR: `ERT-Randevu-Dokumantasyon-TR.docx`
- Lite EN: `ERT-Appointment-Documentation-EN.docx`
- Pro TR: `ert-appointment-pro/ERT-Randevu-Pro-Dokumantasyon-TR.docx`
- Pro EN: `ert-appointment-pro/ERT-Appointment-Pro-Documentation-EN.docx`

## Kullanım

Önizleme (yazmadan):

- `python scripts/fix-pro-docx-consistency.py --edition all --lang all --dry-run`

Sadece Pro dokümanları güncelle:

- `python scripts/fix-pro-docx-consistency.py --edition pro --lang all`

Tüm dokümanları güncelle:

- `python scripts/fix-pro-docx-consistency.py --edition all --lang all`

## Parametreler

- `--edition lite|pro|all`
- `--lang tr|en|all`
- `--dry-run` (değişiklikleri kaydetmez, sadece raporlar)

## Önerilen Akış

1. Önce `--dry-run` ile kontrol et.
2. Hedef kombinasyonda yazma modunda çalıştır.
3. Tekrar `--dry-run` ile kalan temizlenecek satır olmadığını doğrula.

## Mini Policy

### Kim Çalıştırır?

- Release sorumlusu veya dokümantasyon bakımından sorumlu geliştirici.

### Ne Zaman Çalıştırılır?

- DOCX dosyaları güncellendikten sonra release öncesi son doğrulamada.
- Farklı kaynaklardan birleştirme (merge/cherry-pick) sonrası format bozulması şüphesinde.

### Guardrails

- Önce mutlaka `--dry-run` çalıştır.
- Beklenmeyen yüksek değişiklik sayısı varsa yazma modunda devam etme; önce diff ve içerik kontrolü yap.
- Script yalnızca hedeflenen tutarlılık/tekrar temizliği içindir; yeni içerik üretimi için kullanılmaz.
