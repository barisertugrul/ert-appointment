# Changelog — Appointment Booking by ERT (Lite)

All notable changes to this plugin are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.0.0] — 2025-02-27

### Added

**Core booking engine**
- Multi-department support — organise providers into departments (clinics, teams, services, etc.)
- Multi-provider support with individual working hours, breaks and buffer times
- Smart slot generator: respects working hours, break windows, pre/post appointment buffers, minimum booking notice and maximum advance booking days
- Scope-aware settings system — global → department → provider inheritance with per-level overrides
- Special days support — mark dates as closed or give them custom hours

**Custom forms**
- Drag-and-drop form builder with six field types: text, email, phone, select, checkbox, date
- Forms are assignable per department or per provider
- Form responses stored with each appointment record

**Email notifications**
- Fully customisable email templates for: booking confirmation, cancellation, rescheduling, 24-hour reminder, 1-hour reminder, admin new-booking alert
- `{{placeholder}}` token system with 15+ built-in tokens (customer name, date, time, provider, department, etc.)
- Template preview in the admin editor
- Extension point via `wpa_template_placeholders` filter for custom tokens

**Appointment lifecycle**
- Statuses: `pending` → `confirmed` → `completed` / `cancelled` / `no_show` / `rescheduled`
- Optional auto-confirm mode (skip manual approval)
- Customer and provider cancellation with reason field
- Rescheduling creates a linked new appointment; original is marked `rescheduled`
- No-show and completed marking from the admin dashboard

**Admin dashboard**
- Appointment list with status badges, date/provider/department filters and quick-action buttons
- Department and provider management panels
- Working hours grid (per day, open/close times, multiple breaks)
- Form builder UI
- Notification template editor with live placeholder hints

**Shortcodes**
- `[erta_booking]` — full booking widget with optional `department`, `provider` and `form` attributes
- `[erta_departments]` — department listing
- `[erta_providers]` — provider listing with optional `department` filter
- `[erta_my_appointments]` — customer's appointment history

**REST API**
- 14 endpoints across public and admin namespaces (`/wp-json/wpa/v1/`)
- Public: department list, provider list, available slots, calendar view, create/view/cancel booking
- Admin (requires `wpa_manage_all` capability): settings, departments, providers, forms, notification templates, working hours, breaks, special days
- Full WP REST API nonce and permission callback security

**Developer extension points**
- `wpa_appointment_created`, `wpa_appointment_confirmed`, `wpa_appointment_cancelled`, `wpa_appointment_rescheduled` action hooks
- `wpa_after_booking_saved` filter — Pro add-on injects payment logic here
- `wpa_before_cancel_notifications` filter — Pro add-on injects refund logic here
- `wpa_is_pro_active` filter — feature-gate check
- `wpa_rest_api_init` action — Pro add-on registers its own endpoints here

**Internationalisation**
- Full i18n with `load_plugin_textdomain()`
- Ships with translations for: Turkish (`tr_TR`), German (`de_DE`), French (`fr_FR`), Spanish (`es_ES`), Arabic (`ar`), Russian (`ru_RU`)
- `.pot` template included for community translations

**Technical**
- PHP 8.1+ with `declare(strict_types=1)` throughout
- PSR-4 autoloading via Composer
- Domain-driven structure: `Domain/`, `Infrastructure/`, `Api/`, `Core/`, `Settings/`
- `TransientCache` wrapper for slot caching (bust on booking/cancel/reschedule)
- Immutable `Appointment` value object with fluent status-transition methods
- `ResolvedConfig` value object — domain code reads settings without touching WP options directly
- WP-Cron scheduled events: `wpa_send_reminders` (hourly)
- PHPUnit test suite: 7 test classes, ~85 assertions, no WP install required
- Vite + Vue 3 frontend build pipeline (`npm run build`)

---

*Older versions: N/A — this is the initial release.*
