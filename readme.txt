=== Appointment Booking by ERT ===
Contributors: ert
Tags: appointment, booking, calendar, reservation, schedule
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 1.0.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, mobile-friendly WordPress appointment booking plugin with providers, availability rules, custom forms, and notification templates.

== Description ==

**Appointment Booking by ERT** lets you add a full-featured appointment booking system to any WordPress site.

Create a smooth booking flow for your customers and manage your operations from a clean admin interface. The plugin includes flexible availability rules, a custom form builder, and notification templates out of the box.


= Core Features =

* **Provider management** — Individual providers with their own working hours and breaks
* **Smart availability** — Slot generation respects working hours, breaks, buffer times and existing bookings
* **Custom booking forms** — Drag-and-drop form builder with text, email, phone, select, checkbox and date fields
* **Email notifications** — Fully customisable templates for confirmations, cancellations, reminders and more
* **Scope-aware settings** — Override slot duration, buffers and confirmation mode at global, department or provider level
* **Special days** — Mark holidays or days with custom hours
* **Shortcodes** — Drop a booking widget anywhere with `[erta_booking]`
* **REST API** — Full REST API for headless or custom integrations
* **Multilingual** — Ships with Turkish, German, French, Spanish, Arabic and Russian translations

= Pro Version =

The free plugin is fully functional. A separate **Pro add-on** (sold on our website) adds:

* Department management
* Google Calendar sync (OAuth 2.0)
* Zoom meeting auto-creation
* Online payments (PayTR, Stripe, PayPal, İyzico)
* SMS notifications (Twilio, NetGSM)
* WhatsApp notifications (Meta Cloud API)
* Waitlist management
* Advanced reports

The Pro add-on is available at [ert.com.tr](https://ert.com.tr/).

== Installation ==

1. Upload the `ert-appointment` folder to `/wp-content/plugins/`
2. Activate the plugin from **Plugins → Installed Plugins**
3. Go to **Appointment Booking** in the WordPress admin menu
4. Create your first provider, then add `[erta_booking]` to any page

== Frequently Asked Questions ==

= Does this plugin require any paid services? =

No. The free version works completely without any external paid services. The optional Pro add-on adds payment and calendar integrations.

= Is the booking form customisable? =

Yes. You can add, remove and reorder fields using the built-in form builder under **Appointment Booking → Forms**.

= Can I set different working hours per provider? =

Yes. Working hours can be set globally, per department, or per provider. Provider-level settings take priority.

= Does it send reminder emails? =

Yes. You can configure email templates for 24-hour and 1-hour reminders under **Appointment Booking → Notifications**.

= Will it work with my theme? =

The booking widget uses its own scoped CSS and is designed to work with any theme.

= Is it translation ready? =

Yes. The plugin ships with translations for Turkish, German, French, Spanish, Arabic and Russian. Additional translations can be added via the standard WordPress `.po`/`.mo` workflow.

== Screenshots ==

1. Frontend booking form — service/provider and date-time selection
2. Frontend booking form — customer details and submit step
3. Admin dashboard — appointments overview and quick actions
4. Admin forms page — drag-and-drop form builder and booking button text customization
5. Admin notifications page — email, SMS and WhatsApp template channels
6. Admin settings page — integrations overview (Google Calendar, Zoom, SMS, WhatsApp)
7. Mobile booking view — responsive booking experience on phone screens

== Changelog ==

= 1.0.1 — 2026-03-08 =
* Added QA scripts for one-command live test + markdown report generation (`run-live-test-and-report.ps1`, `fill-live-test-report.ps1`)
* Added one-page live test report template for repeatable QA runs (`docs/live-booking-mode-test-report.md`)
* Added real Pro WhatsApp provider support (Meta Cloud API)
* Added Pro channel expansion for notifications (SMS: Twilio/NetGSM, WhatsApp: Meta Cloud API)
* Added form-level booking button text override support
* Added WordPress.org release/publish docs and asset production guides (`docs/wporg-*.md`)
* Booking flow is now mode-based: general, department without provider, department with provider, provider-only
* Frontend step orchestration updated to follow selected booking mode dynamically
* Personless modes now aggregate calendar/slot data across provider sets
* Booking submit flow now uses slot provider context in personless modes
* Gutenberg booking block inspector simplified with booking mode override and clearer edit behavior
* Fixed silent default-provider fallback in general/date-first flows
* Improved multi-host mount stability and reduced loading flicker risk
* Fixed potential key collisions in merged slot lists

= 1.0.0 — 2026-02-27 =
* Initial public release
* Multi-provider support with hierarchical settings (global → provider)
* Smart slot generation: working hours, breaks, buffer times, minimum notice, maximum advance days
* Drag-and-drop custom booking form builder (text, email, phone, select, checkbox, date fields)
* Email notifications with fully customisable templates and placeholder tokens
* Special days: mark holidays or override hours for a specific date
* Scope-aware settings: override any setting at department or provider level
* `[erta_booking]` shortcode with optional department/provider/form filters
* Full REST API (14 public + admin endpoints) for headless integrations
* WP-Cron based appointment reminders (24 h and 1 h before)
* Translations: Turkish (tr_TR), German (de_DE), French (fr_FR), Spanish (es_ES), Arabic (ar), Russian (ru_RU)
* PHP 8.1+ with strict types and PSR-4 autoloading

== Upgrade Notice ==

= 1.0.1 =
Includes mode-based booking flow improvements, notification channel upgrades (Pro SMS/WhatsApp), form-level booking button text override, Gutenberg block UX updates, and QA/WP.org tooling additions. No manual migration steps required.

= 1.0.0 =
Initial release. No upgrade steps required.
