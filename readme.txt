=== Appointment Booking by ERT ===
Contributors: ert
Tags: appointment, booking, calendar, reservation, schedule
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful, extensible appointment booking system. Manage departments, providers, working hours, custom forms and email notifications.

== Description ==

**Appointment Booking by ERT** lets you add a full-featured appointment booking system to any WordPress site.


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

1. Booking widget — department and provider selection
2. Booking widget — date and time picker
3. Admin dashboard — appointment overview
4. Admin panel — working hours management
5. Admin panel — form builder
6. Admin panel — email notification editor

== Changelog ==

= 1.0.0 — 2025-02-27 =
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

= 1.0.0 =
Initial release. No upgrade steps required.
