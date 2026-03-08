# WP.org SVN Publish Guide (ERT Appointment Lite)

Bu doküman, `ert-appointment` Lite sürümünü WordPress.org SVN deposuna yayınlamak için kısa ve güvenli bir akış sunar.

## Önkoşullar

- WordPress.org plugin sahibi hesabın aktif.
- SVN istemcisi kurulu (`svn --version` çalışıyor).
- Lokal release doğrulaması tamamlandı:
  - `ert-appointment.php` içindeki `Version` doğru
  - `readme.txt` içindeki `Stable tag` aynı sürüm
  - Build ve Plugin Check temiz
  - `docs/wporg-release-checklist.md` tamamlandı
  - `docs/wporg-assets-master-checklist.md` tamamlandı

## Assets hazırlığı (önerilen)

SVN commit öncesi görsel dosyaları şu akışla hazırlayın:

1. `docs/wporg-assets-plan.md` ile kapsamı netleştirin.
2. `docs/wporg-assets-production-order.md` ile sprint sırasını takip edin.
3. `docs/wporg-assets-master-checklist.md` üzerinden ilerlemeyi işaretleyin.
4. İlerleme satırını güncelleyin:

```powershell
./scripts/update-wporg-assets-progress.ps1 -OpenInCode -OpenSection final
```

## 1) SVN deposunu checkout et

```bash
svn checkout https://plugins.svn.wordpress.org/ert-appointment wporg-svn
```

Klasör yapısı tipik olarak:

- `wporg-svn/trunk`
- `wporg-svn/tags`
- `wporg-svn/assets`

## 2) Trunk dosyalarını güncelle

- Lokal plugin release içeriğini `wporg-svn/trunk` içine kopyala.
- ZIP dosyasını değil, açılmış plugin dosyalarını kullan.
- Dev/artık dosyaları trunk’a koyma (`node_modules`, `tests`, `.git`, vb.).

## 3) Yeni tag oluştur

Sürüm örneği: `1.0.1`

- `wporg-svn/tags/1.0.1` oluştur.
- `trunk` içeriğinin aynı sürüm snapshot’unu `tags/1.0.1` altına kopyala.

## 4) SVN durumunu kontrol et

```bash
cd wporg-svn
svn status
```

- `?` görünen yeni dosyaları ekle
- Silinenleri SVN’den de sil

```bash
svn add <path>
svn delete <path>
```

## 5) Commit at

```bash
svn commit -m "Release 1.0.1"
```

## 6) Yayın sonrası kontrol

- WP.org plugin sayfasını birkaç dakika sonra kontrol et.
- Yeni sürümün listelendiğini doğrula.
- WordPress panelinde update kontrolünde yeni sürüm göründüğünü test et.
- WordPress.org assets (banner/icon/screenshots) doğru görünüyor mu kontrol et.

---

## Sık yapılan hatalar

- `Version` ile `Stable tag` eşleşmemesi
- `tags/<version>` ile `trunk` içeriğinin farklı olması
- ZIP yüklemeye çalışmak (SVN’e açılmış dosyalar gitmeli)
- Dev dosyalarını trunk/tag içine dahil etmek

## Not

WordPress.org dağıtımı için ekstra özel update-checker kodu gerekmez. Çekirdek update mekanizması, slug + stable tag + sürüm metadata üzerinden otomatik çalışır.
