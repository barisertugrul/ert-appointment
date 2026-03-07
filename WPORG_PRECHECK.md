# ERT Appointment — WordPress.org Precheck

Tarih: 2026-03-07

## Genel Sonuç

Durum: **Şartlı olumlu (gönderime hazır)**

Kritik red sebebi oluşturabilecek açık bir bulgu tespit edilmedi. Aşağıdaki maddeler gönderim öncesi kontrol edilirse kabul şansı yükselir.

## Risk Seviyesi Analizi

### Kritik (P0)

- **Bulunmadı**
- PHP syntax taraması temiz (`php_lint_ok`)
- Dağıtım ZIP içinde geliştirme artıkları yok (`node_modules`, `tests`, `.git`, `package.json`, `vite.config.js` bulunmadı)

### Yüksek (P1)

- **Bulunmadı**
- Confirm/unconfirm named-parameter çakışma hatası düzeltildi ve akış doğrulandı.

### Orta (P2)

- `phpcs.xml.dist` halen bazı kuralları gevşetiyor (örn. `DirectDatabaseQuery`, `Capabilities.Unknown`, yorum kuralları). Bu doğrudan red sebebi değildir ancak inceleme kalitesini etkileyebilir.

### Düşük (P3)

- `README.md` ve `readme.txt` içerikleri kısmen farklı amaç taşıyor; şu an kritik tutarsızlıklar giderildi.

## Bu Turda Düzeltilenler

- README hook adı düzeltildi: `wpa_available_placeholder_hints` → `erta_available_placeholder_hints`
- README WordPress gereksinimi ana plugin header ile hizalandı (`6.0+`)
- Lite readme metni, departman yönetiminin Pro’da olduğu güncel davranışla hizalandı
- PHPCS profilinde geniş iki istisna kaldırıldı:
	- `WordPress.DB.PreparedSQL`
	- `WordPress.Security.EscapeOutput.OutputNotEscaped`

## WordPress.org Gönderim Checklist

### Zorunlu Teknik Kontroller

- [x] Ana plugin header mevcut ve tutarlı (`Version`, `Requires at least`, `Requires PHP`, `Text Domain`)
- [x] `readme.txt` mevcut ve parse edilebilir formatta
- [x] Lisans bilgisi mevcut (GPL uyumlu)
- [x] `uninstall.php` güvenlik guard ile başlıyor (`WP_UNINSTALL_PLUGIN`)
- [x] REST endpointlerde permission callback tanımlı
- [x] Kullanıcı girdilerinde sanitize/escape kullanımı mevcut

### Paketleme Kontrolleri

- [x] Dağıtım ZIP mevcut: `dist/ert-appointment-v1.0.0.zip`
- [x] ZIP içinde `node_modules` yok
- [x] ZIP içinde `tests` yok
- [x] ZIP içinde `.git` yok
- [x] ZIP içinde build config/dev dosyaları yok (`package.json`, `vite.config.js`)

### Gönderim Öncesi Önerilen Son Adımlar

- [x] Temiz bir WordPress kurulumunda eklentiyi ZIP’ten kurup aktivasyon testi yap
- [x] Public booking + admin confirm/unconfirm + cancel akışlarını manuel smoke test et
- [ ] `Plugin Check` eklentisi ile son tarama alıp uyarıları sınıflandır
- [x] `readme.txt` “Tested up to” değerini kullandığın WP sürümüyle güncel tut

### Composer Timeout/Offline Notu

- Ağ yavaş veya kesintiliyse Composer timeout uyarısı normaldir; tekrar denemeden önce şu değer önerilir:
	- `COMPOSER_PROCESS_TIMEOUT=2000 composer install --prefer-dist --no-interaction --no-dev`
- Tam offline kurulum yalnızca cache yeterliyse çalışır:
	- `COMPOSER_DISABLE_NETWORK=1 composer install --prefer-dist --no-interaction --no-dev`
- CI/CD tarafında mümkünse lock dosyasıyla (`composer.lock`) deterministik kurulum kullanılmalıdır.

### Bu Turdaki Canlı Ortam Sonuçları

- WordPress sürümü: `6.9.1`
- Aktivasyon testi: `ok`
- Public departments endpoint smoke: `200`
- Lifecycle smoke (confirm → unconfirm → cancel): `ok`
- Plugin Check eklentisi kurulu durumu: `yok` (önce kurulum gerekli)
