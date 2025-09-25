# Ringkasan Proyek: Kalender Digital (Laravel 12 + Inertia + Vue 3)

## Gambaran Umum
- Kalender Digital adalah aplikasi kalender tim berbasis Laravel 12 yang menyediakan tampilan bulan, hari, dan timeline untuk mengelola kegiatan kantor secara kolaboratif.
- Aplikasi ini memadukan Jetstream (Fortify + Sanctum) untuk otentikasi, Inertia.js + Vue 3 untuk SPA, serta TailwindCSS untuk antarmuka responsif.
- Peran pengguna (admin, editor, viewer) mengatur hak akses. Admin mengelola pengguna, editor mengelola kegiatan yang dibuatnya, dan viewer hanya melihat.
- Pencatatan perubahan (user dan event) serta integrasi Stripe, Scribe, dan Filament menjadikan aplikasi siap produksi dengan audit trail dan admin panel opsional.

## Stack & Integrasi Utama
- Backend: Laravel 12, PHP 8.3 (`composer.json`, `artisan`).
- Frontend: Inertia.js, Vue 3, Vite, Ziggy (`resources/js/app.js`, `vite.config.ts`).
- UI: Tailwind CSS + komponen kustom (`resources/css/app.css`, `resources/js/Components`).
- Auth & Teams: Jetstream, Fortify, Sanctum, Socialite, magic link (`app/Actions`, `app/Http/Controllers/User`).
- Kalender: API JSON berbasis Sanctum + komponen Vue khusus kalender (`routes/api_calendar.php`, `resources/js/Components/Calendar`).
- Billing opsional: Laravel Cashier + Stripe (`config/cashier.php`, `app/Http/Controllers/SubscriptionController.php`).
- Observability & tooling: Sentry, Telescope, Octane, Pail, Rector, Pint, PHPStan, Scribe.

## Struktur Direktori & Fungsi Berkas
### Routing
- `routes/web.php`: Route web terproteksi Sanctum/Jetstream (dashboard, chat, subscriptions, admin) + bootstrap auth (OAuth, magic link).
- `routes/calendar.php`: Halaman kalender utama via Inertia (`/calendar`).
- `routes/api.php`: API `apiResource` user (Sanctum) dan import kalender.
- `routes/api_calendar.php`: Endpoint CRUD event (`GET/POST/PUT/DELETE /api/events`).
- `routes/console.php`: Definisi artisan command kustom (Sitemap).

### Controller HTTP
- `app/Http/Controllers/CalendarController.php`: Render halaman kalender, seed divisi default, kirim tanggal & opsi divisi.
- `app/Http/Controllers/Api/EventController.php`: CRUD event, ekspansi event rekuren (weekly/monthly), filter teks & divisi, logging perubahan.
- `app/Http/Controllers/ApiUserController.php`: API user (list, buat, update, hapus) memanfaatkan Fortify actions & policy.
- `app/Http/Controllers/Admin/{UserManagement,UserLog,EventLog}Controller.php`: Halaman admin Inertia + endpoint JSON log audit user/event.
- `app/Http/Controllers/User/{OauthController,LoginLinkController}.php`: Login sosial dan tautan sekali pakai.
- `app/Http/Controllers/{Dashboard,Chat,Subscription,Welcome}Controller.php`: Dashboard redirect, chat AI, manajemen langganan, halaman sambutan.

### Request Validation & Middleware
- `app/Http/Requests/StoreEventRequest.php` & `UpdateEventRequest.php`: Validasi kegiatan termasuk aturan rekuren dan hubungan divisi/peserta.
- `app/Http/Requests/Admin/UpdateManagedUserRequest.php`: Validasi update user oleh admin (nama, email, NIP, telepon, role, password opsional).
- `app/Http/Middleware/EnsureUserIsAdmin.php`: Middleware menjaga route admin.
- `app/Http/Middleware/HandleInertiaRequests.php`: Menyuntikkan data global Inertia (auth, flash message, Ziggy).

### Model & Relasi
- `app/Models/Event.php`: Model kegiatan, relasi ke `User` (creator), `Division`, `participantUsers`, scope `overlap` untuk rentang tanggal.
- `app/Models/Division.php`: Divisi organisasi, relasi ke pengguna & event.
- `app/Models/{EventParticipant,EventDivision}`: Pivot untuk partisipan & relasi divisi pada event.
- `app/Models/{User,LoginLink,OauthConnection,Membership,Team,TeamInvitation}`: Model Jetstream/Cashier dengan tambahan kolom `nip` & `phone`, role-based helper.
- `app/Models/{UserChangeLog,EventChangeLog}`: Audit trail yang menyimpan perubahan field dalam format array.

### Resources, Notifications, Jobs, Actions
- `app/Http/Resources/EventResource.php`: Serialisasi event ke JSON (divisi & peserta ter-embed).
- `app/Notifications/LoginLinkMail.php`: Email untuk magic login link.
- `app/Jobs/User/UpdateUserProfileInformationJob.php`: Menyinkronkan profil ke Stripe (dipicu oleh update profile action).
- `app/Actions/Fortify/*` & `app/Actions/Jetstream/*`: Implementasi login/register/teams default Jetstream.
- `app/Actions/User/*`: Aktivasi provider OAuth dan callback handler.
- `app/Console/Commands/GenerateSitemap.php`: Command membuat sitemap.

### Providers & Filament
- `app/Providers/{App,Fortify,Jetstream,Telescope}ServiceProvider.php`: Registrasi service, policy, Fortify view, fitur Jetstream, Telescope.
- `app/Providers/Filament/AdminPanelProvider.php` & `app/Filament/Resources/UserResource.php`: Panel admin Filament untuk CRUD user jika diaktifkan.

### Frontend (Inertia + Vue)
- `resources/js/app.js`: Bootstraps Inertia app, Ziggy, Unhead, register komponen Vue.
- `resources/js/bootstrap.js`: Setup axios, CSRF header, Echo placeholder.
- `resources/js/Pages/Calendar/Index.vue`: Halaman kalender utama; mengelola state view/day/week/month, filter divisi, pencarian, sinkronisasi data API, izin berdasarkan role.
- `resources/js/Components/Calendar/*`: Grid bulan (`MonthView`), timeline harian (`DayView`), daftar harian (`SidebarDayList`), form modal (`EventFormModal`), chips/blok event.
- `resources/js/Pages/Admin/Users/Index.vue`: Panel admin untuk edit pengguna + viewer log perubahan user/event.
- `resources/js/Pages/Profile/*`, `Teams/*`, `Subscriptions/*`, `Chat/*`: Halaman Jetstream standar yang sudah di-styling ulang.
- `resources/js/Components/UI/*`: Koleksi komponen UI generik (modal, tombol, input, tab) berbasis Tailwind.
- `resources/js/Components/Profile/*`: Modal profil cepat & pengaturan profil.

### Views & Assets
- `resources/views/app.blade.php`: Shell Inertia dengan meta tags, Ziggy, dan Vite bundle.
- `resources/views/emails/team-invitation.blade.php`: Template undangan tim.
- `resources/views/scribe/index.blade.php`: Halaman dokumentasi API Scribe.
- `resources/css/app.css`: Import Tailwind + gaya tambahan.
- `public/images/*`: Screenshot/grafik untuk README.

### Database Migrations & Seeder
- Migrations Jetstream/Cashier dasar (`0001_*`).
- `2025_01_01_000000_create_divisions_table.php`: Tabel divisi.
- `2025_01_01_000100_add_division_id_to_users_table.php`: Relasi user -> division.
- `2025_01_01_000200_create_events_table.php`: Tabel event dengan waktu mulai/akhir dan metadata.
- `2025_01_01_000300_create_event_participants_table.php` & `000400_create_event_divisions_table.php`: Pivot partisipan & divisi.
- `2025_01_01_000450_add_recurrence_to_events_table.php`: Kolom rekuren (`recurrence_type`, `recurrence_rule`, `recurrence_until`).
- `2025_09_20_000001_add_nip_and_phone_to_users_table.php`: Kolom NIP & telepon.
- `2025_09_20_010000_add_role_to_users_table.php`: Kolom role + default.
- `2025_09_20_020000_create_user_change_logs_table.php` & `020100_create_event_change_logs_table.php`: Audit trail.
- Seeder: `DivisionSeeder` (divisi default), `EventSeeder` (contoh kegiatan), `DatabaseSeeder` (user contoh + memanggil seeder lain).

### Tests & QA
- `tests/Feature/*`: Suite Pest untuk auth, team management, API user, OAuth, profil, dsb.
- `tests/Feature/Controllers/ApiUserControllerTest.php`: Uji hak akses & operasi API user.
- `tests/Feature/Policies/UserPolicyTest.php`: Memastikan aturan peran.
- `tests/Feature/Controllers/DashboardControllerTest.php`: Redirect ke kalender.
- `tests/Unit/*`: Contoh uji unit dasar.

### Dokumentasi & Referensi
- `docs/calendar_setup.md`: Panduan setup kalender.
- `README.md`: Petunjuk instalasi, fitur, dan dokumentasi pengguna.
- `app_summary.md`: Ringkasan arsitektur (berkas ini).

## Alur Data & Database Flow
1. Pengguna login melalui Jetstream (email/password, magic link, atau OAuth). Sanctum menyiapkan cookie sesi.
2. Akses `/calendar` memicu `CalendarController` yang memastikan divisi default tersedia dan memberikan `today`, `view`, `date`, dan `divisionOptions` ke Inertia.
3. Frontend `Calendar/Index.vue` menghitung rentang tanggal (hari/minggu/bulan) dan memanggil `GET /api/events` dengan parameter `start`, `end`, `q`, dan `division[]`.
4. `Api\EventController@index` memuat event non-rekuren yang overlap rentang, memfilter berdasarkan teks & divisi, lalu memuat relasi divisi & peserta.
5. Event rekuren diekspansi dalam rentang diminta (weekly dengan daftar hari + interval, monthly dengan tanggal bulan + interval). Setiap instansi diberi `occurrence_key` untuk identifikasi di UI.
6. `EventResource` mengembalikan JSON siap render. Vue memperbarui grid bulan, daftar hari, timeline, dan metrik mingguan/bulanan.
7. Saat membuat/memperbarui event, frontend mengirim payload ke `/api/events` atau `/api/events/{id}` (dengan XSRF token). Request divalidasi oleh `StoreEventRequest`/`UpdateEventRequest`.
8. Controller menyimpan event, menyinkronkan relasi pivot `divisions` dan `participantUsers`, serta mencatat perubahan di `event_change_logs` (jika tabel tersedia).
9. Penghapusan event membuat snapshot nilai lama, menyimpan log, lalu menghapus record (FK `event_id` di-log akan di-null).
10. Panel admin memanggil `/admin/users/logs` dan `/admin/events/logs` (JSON). Controller log memfilter berdasarkan tanggal dan mengembalikan daftar perubahan dengan actor & target.
11. Update pengguna oleh admin diverifikasi `UpdateManagedUserRequest`, perubahan disimpan, role terproteksi, dan log ditulis ke `user_change_logs`.

## Workflow Pengguna
1. **Masuk**: Pengguna membuka `/`, diarahkan ke login Jetstream. Dapat menggunakan OAuth (GitHub/GitLab) atau magic link.
2. **Beranda**: Setelah login, `/dashboard` mengarahkan ke `/calendar`.
3. **Navigasi Kalender**: Pilih tampilan bulan/hari, gunakan filter divisi & pencarian, lihat metrik mingguan/bulanan, dan pilih hari untuk melihat daftar serta timeline.
4. **Kelola Kegiatan**:
   - Admin/Editor membuka modal form untuk membuat kegiatan (jam kerja default 07:30-16:00 untuk `all_day`).
   - Atur rekuren weekly (hari + interval) atau monthly (tanggal + interval + until).
   - Tambah peserta (daftar ID user) dan hubungkan ke divisi.
   - Editor hanya dapat mengubah/hapus event yang dibuatnya; Admin bebas.
5. **Audit & Admin**: Admin membuka `/admin/users` untuk memperbarui nama/email/NIP/telepon/role, melihat log perubahan pengguna & kalender berdasarkan tanggal, serta kembali ke kalender.
6. **Profil & Teams**: Pengguna memperbarui profil melalui modal profil cepat atau halaman `/profile`, mengelola tim di `/teams`, dan mengakses langganan di `/subscriptions` bila fitur diaktifkan.
7. **Chat & Fitur Tambahan**: Halaman `/chat` menyediakan antarmuka AI (menggunakan Prism) jika langganan aktif; akses dilakukan setelah autentikasi.

## Automasi & Tooling
- `composer dev`: Menjalankan server Laravel, queue listener, pail log viewer, dan Vite secara paralel.
- `composer setup`: Regenerasi key, migrate fresh + seed, build aset dengan Bun, dan generate dokumentasi Scribe.
- `composer analyse|format|test`: Menjalankan PHPStan, Pint+Rector, dan suite Pest.
- Vite scripts (`npm run dev|build`, `bun run dev|build`) mengelola aset frontend.

## Catatan Keamanan & Operasional
- Semua endpoint kalender dan admin dibungkus `auth:sanctum` + middleware role; pastikan konfigurasi sanctum cookie sesuai domain produksi.
- Ketergantungan timezone: server menggunakan timezone default Laravel; pastikan `APP_TIMEZONE` konsisten agar ekspansi rekuren cocok dengan zona pengguna.
- Audit log hanya berfungsi jika migrasi log dijalankan; tanpa tabel tersebut aplikasi tetap berjalan namun tanpa histori perubahan.
- Pertimbangkan integrasi fitur lanjutan (autocomplete peserta, ekspor iCal, drag & drop event) sesuai kebutuhan.

## Dokumentasi Tambahan
- Dokumentasi API dapat digenerasi via `php artisan scribe:generate` (output HTML di `public/docs`).
- Gunakan `docs/calendar_setup.md` untuk panduan internal tambahan.
