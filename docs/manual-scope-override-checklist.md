# ERT Appointment — Manual Scope Override Checklist

Bu checklist, aşağıdaki konuları uçtan uca doğrulamak için hazırlanmıştır:
- Ayarların kaydedilip reload sonrası aynı kalması
- Global → Department → Provider override zinciri
- Tarih aralığı, slot üretimi, tamponlar ve bilgi metinlerinin doğru kapsamdan gelmesi

## 0) Test Ön Koşulları

- WordPress ortamında eklenti aktif.
- En az:
  - 1 department (`D1`)
  - 2 provider (`P1` = `D1` içinde, `P2` = global/no-department veya başka department)
- Booking widget sayfası mevcut (`[erta_booking]` veya blok).
- Tarayıcı cache hard refresh yapılmış (`Ctrl+F5`).

## 1) Persist (Ayar Kaydetme) Kontrolü

## 1.1 Global scope

1. Admin > Settings > Scope = Global.
2. Aşağıdakileri değiştirip kaydet:
   - Auto-confirm bookings = ON
   - Arrival Reminder = ON
   - Allow General Booking = ON
3. Sayfayı yenile.

Beklenen:
- 3 toggle da ON görünmeli (pasife dönmemeli).

## 1.2 Department scope

1. Scope = Department, `D1` seç.
2. Aynı 3 toggle için ters değer set et (örn. OFF).
3. Kaydet + yenile.

Beklenen:
- Department scope içinde seçilen değerler korunmalı.

## 1.3 Provider scope

1. Scope = Provider, `P1` seç.
2. Aynı 3 toggle için farklı kombinasyon set et.
3. Kaydet + yenile.

Beklenen:
- Provider scope değerleri korunmalı.

---

## 2) Override Zinciri Doğrulama (Global → Department → Provider)

Aşağıdaki gibi çakışan değerler ver:

- Global:
  - slot duration = 30
  - buffer before = 5
  - buffer after = 10
  - booking start/end = `2026-03-10` / `2026-03-31`
  - intro text = `GLOBAL INTRO`
  - post text = `GLOBAL POST`
- Department (`D1`):
  - slot duration = 40
  - buffer before = 3
  - booking start/end = `2026-03-12` / `2026-03-28`
  - intro text = `DEPT INTRO`
- Provider (`P1`):
  - slot duration = 50
  - buffer after = 20
  - booking start/end = `2026-03-15` / `2026-03-25`
  - post text = `PROVIDER POST`

## 2.1 P1 seçildiğinde

1. Frontend’de provider olarak `P1` seç.
2. Takvimde seçilebilir tarihleri kontrol et.
3. Slot saat aralıklarını kontrol et.
4. Form adımında intro kutu metnini kontrol et.
5. Success adımında post info kutu metnini kontrol et.

Beklenen:
- Tarih aralığı: `P1`’in provider aralığı (`03-15`..`03-25`).
- Slot duration: 50 dk (provider override).
- Buffer after: 20 dk (provider override).
- Buffer before: provider’da yoksa department/global fallback (bu örnekte department=3).
- Intro text: provider’da yoksa department (`DEPT INTRO`).
- Post text: provider varsa `PROVIDER POST`.

## 2.2 P2 seçildiğinde (provider override yok senaryosu)

1. Frontend’de provider olarak `P2` seç.
2. Takvim/slot/form/success kontrol et.

Beklenen:
- Provider’da değer yoksa department (varsa), o da yoksa global kullanılmalı.

---

## 3) Slot Üretimi Matematik Kontrolü

Test verisi:
- çalışma başlangıcı: 08:30
- slot duration: 30
- buffer after: 10
- break: 10:00–10:30

Adımlar:
1. İlgili provider’da bu değerleri ayarla.
2. Frontend slot listesine bak.

Beklenen:
- Ardışık slot başlangıçları en az `duration + buffer_after` kadar artmalı.
  - Örnek: 08:30 → 09:10 → 09:50 ...
- Break ile çakışan slotlar listelenmemeli.

---

## 4) Arrival Note Kontrolü

1. Arrival buffer > 0 ayarla (örn. 15).
2. Booking yap ve success ekranına gel.

Beklenen:
- Arrival reminder toggle ON/OFF durumundan bağımsız olarak, buffer > 0 ise arrival notu görünmeli.
- Location doluysa lokasyonlu metin, boşsa genel metin görünmeli.

---

## 5) General Booking Kontrolü

1. `Allow General Booking = ON`.
2. Frontend’i yenile.
3. Gerekirse shortcode/block ile general mode açık kullan.

Beklenen:
- Akışta department/provider adımları skip kuralına uygun davranmalı.
- Uygun provider resolve edilemezse kullanıcıya anlaşılır hata dönmeli.

---

## 6) Regresyon Kontrolü

- Save sonrası admin ekranında toggle’lar doğru state göstermeye devam etmeli.
- Aynı provider/department arasında hızlı geçişlerde eski takvim/slot/meta sızıntısı olmamalı.
- Build alınmış sürümde JS runtime hatası olmamalı.

---

## 7) Hızlı Sonuç Tablosu

- [ ] Persist: auto_confirm
- [ ] Persist: allow_general_booking
- [ ] Persist: show_arrival_reminder
- [ ] Provider override: slot/buffer/date
- [ ] Department fallback çalışıyor
- [ ] Global fallback çalışıyor
- [ ] Break + buffer slot matematiği doğru
- [ ] Arrival note görünürlüğü doğru
- [ ] General booking akışı doğru
