# ERT Appointment — Release Checklist Template

> Bu şablon her sürüm için kopyalanıp `release-checklist-<version>.md` olarak kullanılmalıdır.

## Release Meta

- Version:
- Release Date:
- Release Owner:
- QA Owner:
- Scope Summary:
- Risk Level: ☐ Low ☐ Medium ☐ High

---

## Gate A — Version & Metadata (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| `readme.txt` stable tag sürümle uyumlu |  |  | ☐ |
| Plugin ana dosya `Version` alanı güncel |  |  | ☐ |
| `Requires` alanları doğrulandı |  |  | ☐ |

## Gate B — Changelog & Docs (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| `CHANGELOG.md` sürüm girdisi tamamlandı |  |  | ☐ |
| `readme.txt` changelog sürüm notu eklendi |  |  | ☐ |
| `Upgrade Notice` güncellendi |  |  | ☐ |
| README/QA quick-start güncel |  |  | ☐ |

## Gate C — Build & Quality (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| `npm run build` başarılı |  | Komut çıktısı linki | ☐ |
| Kritik PHP dosyaları `php -l` temiz |  | Komut çıktısı linki | ☐ |
| Runtime smoke test temiz |  | Test notu | ☐ |

## Gate D — Functional Coverage (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| Ana kullanıcı akışları pass |  | QA raporu | ☐ |
| Regresyon kritik senaryolar pass |  | QA raporu | ☐ |
| Gutenberg/block davranışları pass |  | QA raporu | ☐ |

## Gate E — Packaging & WP.org Hygiene (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| Geliştirme scriptleri runtime’a bağlı değil |  | Kod inceleme notu | ☐ |
| Geçici test artefact’ları temizlendi |  | Dosya listesi | ☐ |
| Paket içeriği yayın stratejisine uygun |  | Zip içeriği | ☐ |

---

## Exceptions / Risk Acceptance

- Exception ID:
- Description:
- Approved by:
- Expiry date:

---

## Final Decision

- Blocking items tamamlandı mı? ☐ Evet ☐ Hayır
- GO / NO-GO: ☐ GO ☐ NO-GO
- Approved by:
- Approval time:
- Post-release smoke owner:
