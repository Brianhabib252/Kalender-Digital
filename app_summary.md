# Ringkasan Proyek: Kalender Digital (Laravel 12 + Inertia + Vue 3)

Dokumen ini merangkum framework, peran berkas penting, alur database, endpoint, dan workflow aplikasi Kalender Digital dalam repositori ini.

—

## Stack & Framework

- Backend: Laravel 12 (`artisan`, `composer.json`)
- SPA: Inertia.js + Vue 3 + Vite (`resources/js/app.js`, `vite.config.ts`, `package.json`)
- UI: Tailwind CSS (`postcss.config.js`, `resources/css/app.css`)
- Auth: Jetstream + Fortify + Sanctum; Social login via Socialite
- Teams: Jetstream Teams
- Billing: Stripe via Laravel Cashier (`config/cashier.php`, `config/subscriptions.php`)
- Admin: Filament 3 (panel tersedia)
- API Docs: Scribe (`config/scribe.php`, `resources/views/scribe/index.blade.php`)
- Observability/Perf: Sentry, Telescope, Octane
- FE routing helper: Ziggy

—

## Struktur Proyek (Tingkat Tinggi)

- `routes/web.php`: Route web (auth, dashboard, chat, subscriptions) + include `calendar.php`.
- `routes/calendar.php`: Halaman kalender (Inertia) di `/calendar`.
- `routes/api.php`: API dasar + include `api_calendar.php`.
- `routes/api_calendar.php`: Endpoint CRUD event (`/api/events`).
- `app/Http/Controllers/*`: Controller web & API (lihat ringkas di bawah).
- `app/Http/Requests/*`: Validasi request Event (store/update) dan admin user.
- `app/Models/*`: Model Eloquent (Event, Division, User, dst.).
- `app/Policies/*`: Kebijakan akses (EventPolicy, UserPolicy, dst.).
- `database/migrations/*`: Skema database (users, divisions, events, pivot, recurrence, dst.).
- `database/seeders/*`: Seeder Divisi dan contoh Event.
- `resources/js/Pages/*`: Halaman Inertia (Calendar, Auth, Profile, Teams, Subscriptions, Chat, Welcome).
- `resources/js/Components/*`: Komponen Vue (kalender dan UI primitives).
- `resources/views/*`: Blade dasar (`app.blade.php`) dan view email/prompt/docs.

—

## Routing & Endpoint Utama

Web routes (`routes/web.php`):

- `/`: Redirect ke `login`.
- `/dashboard`: Redirect ke kalender (`DashboardController`).
- `/calendar`: Halaman kalender (middleware `auth:sanctum`, Jetstream auth session; lihat `routes/calendar.php`).
- `auth/*`: OAuth redirect/callback dan magic link (rate-limited + signed URL).
- `/chat`: Chat (opsional; integrasi Prism, akses setelah login).
- `/subscriptions`: Manajemen langganan (Cashier) — `index/create/show`.

API routes (`routes/api.php`, `routes/api_calendar.php`):

- `GET /api/events`: Ambil event berdasarkan rentang `start/end`, pencarian `q`, dan filter `division[]`.
- `POST /api/events`: Buat event (beserta peserta dan divisi; dukung recurrence).
- `PUT /api/events/{event}`: Perbarui event (field, peserta, divisi, recurrence).
- `DELETE /api/events/{event}`: Hapus event.
- `apiResource('user', ...)`: API manajemen user (Sanctum) — index, store, show, update, destroy.

—

## Controllers (Ringkas Per Berkas)

- `app/Http/Controllers/CalendarController.php`: Render halaman Inertia `Calendar/Index`; memastikan divisi default tersedia; mengirim `today`, `view`, `date`, `divisionOptions`.
- `app/Http/Controllers/DashboardController.php`: Satu aksi `__invoke` untuk redirect ke `calendar.index`.
- `app/Http/Controllers/Api/EventController.php`: CRUD event + ekspansi recurrence mingguan/bulanan dalam rentang diminta; filter teks dan divisi; respon JSON via `EventResource`.
- `app/Http/Controllers/ApiUserController.php`: API user berbasis Sanctum dan Jetstream (list user tim, buat user via `CreateNewUser`, update profil via `UpdateUserProfileInformation`, hapus via `DeleteUser`).
- `app/Http/Controllers/User/OauthController.php`: Alur OAuth (redirect/callback/link/unlink) sesuai `config/oauth.php`.
- `app/Http/Controllers/User/LoginLinkController.php`: Magic link (buat token, kirim email, login via URL bertanda tangan).
- `app/Http/Controllers/SubscriptionController.php`: Halaman/aksi langganan Stripe (Cashier) termasuk portal dan checkout.
- `app/Http/Controllers/ChatController.php`: Halaman chat; memeriksa status subscription dan menyiapkan model Prism.

—

## Requests (Validasi Input)

- `app/Http/Requests/StoreEventRequest.php`: Validasi pembuatan event (judul, waktu, lokasi, `all_day`, `participant_user_ids.*`, `division_ids.*`, serta `recurrence_*`).
- `app/Http/Requests/UpdateEventRequest.php`: Validasi pembaruan event (aturan mirip, sifatnya `sometimes`).
- `app/Http/Requests/Admin/UpdateManagedUserRequest.php`: Validasi update user dari panel admin (nama, email, nip/phone, role, password opsional).

—

## Models & Relasi (inti kalender)

- `app/Models/Event.php`
  - Fillable: `title, description, location, start_at, end_at, all_day, created_by, recurrence_type, recurrence_rule, recurrence_until`.
  - Casts: `start_at`/`end_at` datetime, `all_day` boolean, `recurrence_rule` array, `recurrence_until` date.
  - Relasi: `creator` (User), `participantUsers` (pivot `event_participants` + `participant_role`), `divisions` (pivot `event_divisions`).
  - Scope: `overlap(start,end)` untuk ambil event yang beririsan rentang.
- `app/Models/Division.php`
  - Fillable: `name`; Relasi: `users` (hasMany), `events` (belongsToMany via `event_divisions`).
- `app/Models/User.php`
  - Peran: `admin`, `editor`, `viewer`; konstanta `ADMIN_EMAIL` untuk admin default.
  - Trait: Jetstream Teams, Sanctum, Cashier, Profile Photo, 2FA.
  - Guarded default; casts termasuk `password` hashed, `role` string.
  - Method util: `isAdmin()/isEditor()/isViewer()`; `canAccessPanel()` Filament true.

—

## Policies & Peran

- `app/Policies/EventPolicy.php`
  - `before`: admin selalu diizinkan.
  - `create`: admin/editor.
  - `update/delete`: editor hanya untuk event yang ia buat; admin diizinkan lewat `before`.
- `app/Policies/UserPolicy.php`
  - Berbasis Jetstream Teams + Sanctum token ability (`read/create/update/delete`).
  - Pembatasan penghapusan diri sendiri; hanya admin yang boleh hapus user lain.

—

## Resource Serializer

- `app/Http/Resources/EventResource.php`: Serialisasi event ke JSON (termasuk divisions, participants dengan division mereka, dan `occurrence_key` untuk instance recurrence).

—

## Skema Database & Alur Data

Migrations utama:

- `0001_01_01_000000_create_users_table.php`: Tabel user dasar (Jetstream/Cashier field umum).
- `2025_09_20_000001_add_nip_and_phone_to_users_table.php`: Tambah kolom `nip` dan `phone` pada users.
- `2025_09_20_010000_add_role_to_users_table.php`: Tambah `role` (default `viewer`) dan set admin untuk email `brianhabib252@gmail.com`.
- `2025_01_01_000000_create_divisions_table.php`: Tabel `divisions` (`name` unik).
- `2025_01_01_000100_add_division_id_to_users_table.php`: Foreign key opsional `users.division_id -> divisions.id`.
- `2025_01_01_000200_create_events_table.php`: Tabel `events` (judul, waktu, lokasi, `all_day`, `created_by`, + kolom recurrence).
- `2025_01_01_000300_create_event_participants_table.php`: Pivot `event_participants` (`event_id`, `user_id`, `participant_role`, unik gabungan).
- `2025_01_01_000400_create_event_divisions_table.php`: Pivot `event_divisions` (`event_id`, `division_id`, unik gabungan).
- `2025_01_01_000450_add_recurrence_to_events_table.php`: Guarded add kolom recurrence bila belum ada.

Seeder:

- `DatabaseSeeder.php`: Membuat user contoh + memanggil `DivisionSeeder` dan `EventSeeder`.
- `DivisionSeeder.php`: Membuat divisi default: Seluruh Pegawai, Hakim, Kesekretariatan, Kepaniteraan, Dinas Luar.
- `EventSeeder.php`: Generate ~20 event acak dalam bulan berjalan dan mengaitkan 1–2 divisi per event.

Alur data inti:

- FE menentukan rentang berdasarkan view (day/week/month + buffer) dan memanggil `GET /api/events`.
- BE mengambil event non-recurring yang overlap + mengekspansi recurring (weekly by `days` atau monthly by `month_days`, menghormati `interval` dan `recurrence_until`).
- Hasil dikembalikan dalam format `EventResource` untuk dirender di grid bulan/timeline hari.
- Pembuatan/pembaruan event menyinkronkan relasi `participantUsers` dan `divisions` via pivot.

—

## Frontend (Inertia + Vue)

- Layout: `resources/views/app.blade.php` menyertakan CSRF token, Ziggy routes, dan Vite bundle.
- Boot: `resources/js/app.js` membuat Inertia app, memuat halaman `resources/js/Pages/*`, Ziggy, dan Unhead.
- Halaman Kalender: `resources/js/Pages/Calendar/Index.vue`
  - State: `view`, `date`, `divisionOptions` dari controller; `events` dari `/api/events`.
  - Fitur: tombol navigasi tanggal, filter divisi (checkbox), pencarian `q`, counter harian/mingguan/bulanan.
  - Izin FE: `viewer` (lihat saja), `editor`/`admin` (buat/edit; delete untuk admin dan editor terhadap miliknya sesuai policy).
  - API: memakai cookie Sanctum (`/sanctum/csrf-cookie`) + header `X-XSRF-TOKEN` saat request.
- Komponen Kalender:
  - `Components/Calendar/MonthView.vue`: grid bulan, event chips per hari, trigger buka form.
  - `Components/Calendar/DayView.vue`: timeline 06:00–18:00 dengan penataan overlap.
  - `Components/Calendar/SidebarDayList.vue`: daftar event per hari dengan aksi edit/hapus.
  - `Components/Calendar/EventFormModal.vue`: form buat/ubah event, termasuk recurrence (weekly/monthly + interval + until), pilihan divisi, peserta (CSV ID saat ini).
  - `Components/Calendar/{EventBlock,EventChip,DayCell}.vue`: elemen tampilan event.
- Komponen UI umum: `resources/js/Components/ui/*` (button, input, select, modal, tabs, dsb.).

—

## Workflow Pengguna (end-to-end)

1) Otentikasi

- Kunjungi `/` -> redirect ke halaman login. Login via email/password, magic link, atau OAuth (GitHub/GitLab jika aktif).
- Setelah login, `/dashboard` -> redirect ke `/calendar`.

2) Mengelola Jadwal

- Lihat kalender bulan; pilih tanggal untuk melihat daftar & timeline.
- Filter berdasarkan Divisi dan/atau gunakan pencarian teks.
- Tambah event (admin/editor): buka modal, isi detail, pilih divisi/peserta, opsional atur pengulangan (mingguan/bulanan), simpan -> data tersimpan beserta relasi.
- Edit/Hapus event: admin bebas; editor terbatas pada event yang ia buat; viewer hanya melihat.
- Event berulang diekspansi otomatis dalam rentang tampil, tampil seperti event biasa.

3) Manajemen Pengguna & Profil

- Admin dapat mengelola user melalui API/halaman admin (role: viewer/editor/admin). Email `ADMIN_EMAIL` selalu admin.
- Profil user dapat diperbarui (nama, email, nip, phone, foto) via Fortify action.

4) Langganan (opsional)

- `/subscriptions` menampilkan/mengelola status langganan Stripe bila `cashier.billing_enabled=true`.

—

## Endpoint API (Ringkas)

- `GET /api/events?start&end&q&division[]`: daftar event dalam rentang.
- `POST /api/events`: buat event.
- `PUT /api/events/{id}`: perbarui event.
- `DELETE /api/events/{id}`: hapus event.
- `apiResource('user', ...)`: index/store/show/update/destroy (butuh token Sanctum + izin policy).

—

## Build & Pengembangan

- `composer dev`: jalankan php server, queue listener, pail log, dan Vite secara paralel.
- `composer setup`: key:generate -> migrate:fresh --seed -> bun install/build -> scribe:generate.
- Manual: `php artisan serve` + `npm run dev` (atau `bun run dev`).

—

## Keamanan & Catatan

- Pastikan semua route API kalender berada di balik `auth:sanctum` (saat ini sudah dibungkus group di `routes/api_calendar.php`).
- FE menggunakan cookie Sanctum + header `X-XSRF-TOKEN` untuk request aman.
- Perhatikan konsistensi timezone (server/DB vs browser) pada perhitungan rentang.
- Peningkatan yang disarankan: autocomplete peserta, iCal export, drag-resize di UI, dan dukungan kalender mingguan penuh.

—

## Referensi Berkas Penting (pilihan)

- Konfigurasi inti: `composer.json`, `config/app.php`, `config/auth.php`, `config/sanctum.php`, `config/jetstream.php`.
- Kalender: `routes/calendar.php`, `routes/api_calendar.php`, `app/Http/Controllers/{CalendarController,Api/EventController}.php`, `app/Http/Resources/EventResource.php`, `resources/js/Pages/Calendar/Index.vue`, `resources/js/Components/Calendar/*`.
- Database: `database/migrations/2025_*`, `database/seeders/{DivisionSeeder,EventSeeder}.php`.
- Auth/OAuth: `routes/web.php` (group auth), `app/Http/Controllers/User/*`, `config/oauth.php`.
- Billing: `app/Http/Controllers/SubscriptionController.php`, `config/subscriptions.php`, `config/cashier.php`.
- Dokumen bantuan: `docs/calendar_setup.md`.

