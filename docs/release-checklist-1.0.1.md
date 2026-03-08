# ERT Appointment — Release Checklist (1.0.1)

## Release Meta

- Version: **1.0.1**
- Release Date: **2026-03-08**
- Release Owner: ____________________
- QA Owner: ____________________
- Scope Summary: Booking mode flow + Gutenberg UX + QA tooling/docs
- Risk Level: ☐ Low ☐ Medium ☐ High

---

## Gate A — Version & Metadata (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| `readme.txt` stable tag = `1.0.1` |  | [readme.txt](readme.txt) | ☐ |
| Plugin ana dosya `Version` alanı `1.0.1` |  | Dosya/link | ☐ |
| `Requires` alanları doğrulandı |  | [readme.txt](readme.txt) | ☐ |

## Gate B — Changelog & Docs (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| `CHANGELOG.md` içinde `1.0.1 — 2026-03-08` mevcut |  | [CHANGELOG.md](CHANGELOG.md) | ☐ |
| `readme.txt` içinde `= 1.0.1 — 2026-03-08 =` mevcut |  | [readme.txt](readme.txt) | ☐ |
| `Upgrade Notice` içinde `1.0.1` notu mevcut |  | [readme.txt](readme.txt) | ☐ |
| QA quick-start dokümantasyonu güncel |  | [docs/live-booking-mode-test-report.md](docs/live-booking-mode-test-report.md) | ☐ |

## Gate C — Build & Quality (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| Frontend build başarılı (`npm run build`) |  | Komut çıktısı | ☐ |
| Kritik dosyalar için `php -l` temiz |  | Komut çıktısı | ☐ |
| Wrapper/fill script parse kontrolü temiz |  | Komut çıktısı | ☐ |

## Gate D — Functional Coverage (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| `general` akışı pass |  | QA raporu | ☐ |
| `department_no_provider` akışı pass |  | QA raporu | ☐ |
| `department_with_provider` akışı pass |  | QA raporu | ☐ |
| `provider_only` akışı pass |  | QA raporu | ☐ |
| Default provider fallback yok (general/date-first) |  | QA raporu | ☐ |
| Gutenberg block edit/reselect pass |  | QA raporu | ☐ |
| Çoklu booking host mount pass |  | QA raporu | ☐ |

## Gate E — Packaging & WP.org Hygiene (Blocking)

| Item | Owner | Evidence | Status |
|---|---|---|---|
| `scripts/` ve `docs/` ekleri runtime’a bağlı değil |  | Kod inceleme notu | ☐ |
| `test-results` geçici dosyaları temiz |  | Dosya listesi | ☐ |
| Release paketi stratejiye uygun hazırlandı |  | Zip içeriği | ☐ |

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
