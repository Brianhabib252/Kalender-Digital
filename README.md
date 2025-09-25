# Kalender Digital

Kalender Digital adalah aplikasi kalender kolaboratif untuk instansi/organisasi yang dibangun di atas Laravel 12, Inertia.js, dan Vue 3. Proyek ini menghadirkan tampilan bulan, hari, dan timeline, lengkap dengan peran pengguna, audit log, dan integrasi Stripe, sehingga siap dipakai di lingkungan produksi.

## Overview
- Role-based access (admin/editor/viewer) dengan Jetstream, Fortify, dan Sanctum.
- Tampilan kalender rich UI menggunakan Vue 3 + TailwindCSS, filter divisi, dan pencarian.
- API JSON untuk manajemen event, lengkap dengan dukungan rekuren mingguan/bulanan.
- Admin panel Inertia untuk pengelolaan user, audit log user/event, dan integrasi Filament opsional.
- Magic link login, OAuth (GitHub/GitLab), dan langganan Stripe (Cashier) sebagai fitur tambahan.

## Stack
- **Backend**: Laravel 12 (PHP 8.3+), Sanctum, Jetstream Teams, Fortify, Cashier, Socialite, Scribe.
- **Frontend**: Inertia.js + Vue 3 + Vite, Ziggy, Tailwind CSS.
- **Tooling**: PHPUnit/Pest, Pint, Rector, PHPStan, Telescope, Octane, Pail, Sentry.

## Application Structure
- `routes/web.php`, `routes/calendar.php`, `routes/api.php`, `routes/api_calendar.php`: HTTP endpoint untuk UI, kalender, API user, dan API event.
- `app/Http/Controllers`: Calendar CRUD, admin panel, subscription, chat, login sosial, magic link.
- `app/Http/Requests`, `app/Policies`, `app/Http/Middleware`: Validasi, kebijakan role, dan proteksi admin.
- `app/Models`: Event, Division, User, pivot relasi, audit log.
- `resources/js`: Inertia SPA, halaman Calendar/Admin/Profile, komponen UI/Calendar, bootstrap Ziggy.
- `database/migrations`, `database/seeders`: Skema dan seeder divisi/event/log.
- `docs/`, `tests/`: Dokumentasi konfigurasi dan suite Pest untuk auth, policy, controller.

## Backend Flow
- `CalendarController` memastikan divisi dasar tersedia dan mengirim data awal kalender.
- `Api\EventController` menangani pencarian event, ekspansi rekuren, dan pencatatan log perubahan.
- `ApiUserController` menggunakan Fortify actions untuk CRUD user via Sanctum.
- `Admin\{UserManagement,UserLog,EventLog}Controller` melayani halaman admin serta endpoint JSON audit trail.
- Middleware `EnsureUserIsAdmin` dan policy `EventPolicy`/`UserPolicy` mengatur hak akses admin/editor/viewer.

## Frontend Flow
- `resources/js/app.js` mem-bootstrap Inertia, Ziggy, dan komponen global.
- Halaman `Calendar/Index.vue` mengelola state (view/day/week), memanggil `/api/events`, dan merender grid + timeline.
- Komponen kalender (`MonthView`, `DayView`, `SidebarDayList`, `EventFormModal`) menangani interaksi drag-free, filter divisi, serta form rekuren.
- `Admin/Users/Index.vue` menyediakan tabel edit user, pemanggilan log harian, dan tombol kembali ke kalender.
- Halaman Jetstream lainnya (Profile, Teams, Subscriptions, Chat) mempertahankan gaya dan integrasi aplikasi.

## Database Schema & Seeds
- `events`, `event_participants`, `event_divisions` menyimpan kegiatan, peserta, dan divisi terhubung.
- Kolom rekuren (`recurrence_type`, `recurrence_rule`, `recurrence_until`) menambah dukungan weekly/monthly.
- `user_change_logs` dan `event_change_logs` merekam perubahan (field lama -> baru) beserta aktor.
- `DivisionSeeder`, `EventSeeder` mengisi divisi default dan contoh jadwal; `DatabaseSeeder` membuat pengguna awal.

## User Workflow
1. Pengguna login (password, magic link, atau OAuth). Sanctum menyetup sesi.
2. `/dashboard` mengarahkan ke `/calendar` yang memuat data awal via Inertia.
3. Vue memanggil `/api/events` sesuai rentang tampilan, menerapkan filter divisi & pencarian.
4. Admin/editor membuat, mengubah, atau menghapus event melalui modal; perubahan dicatat di log.
5. Admin membuka `/admin/users` untuk memperbarui data user, melihat audit log user & kalender, lalu kembali ke kalender.
6. Pengguna dapat memperbarui profil, mengelola tim, mengakses langganan Stripe, dan memakai halaman chat AI.

## API Endpoints
- `GET /api/events?start&end&q&division[]`: Event dalam rentang dengan filter teks/divisi.
- `POST /api/events`: Buat event baru (divisi + peserta + rekuren).
- `PUT /api/events/{id}`: Perbarui event.
- `DELETE /api/events/{id}`: Hapus event + catat log.
- `apiResource('user', ApiUserController)`: CRUD user berdasarkan policy dan token Sanctum.

## Quick Start (Local)

1) Clone dan install

```bash
git clone https://github.com/Brianhabib252/Kalender-Digital.git
cd Kalender-Digital
composer install
npm install # atau: bun install
```

2) Konfigurasi environment

```bash
cp .env.example .env
```

Atur `.env` minimal:

```
APP_URL=http://127.0.0.1:8000
SANCTUM_STATEFUL_DOMAINS=127.0.0.1,localhost
SESSION_DOMAIN=localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kalender_digital
DB_USERNAME=your_user
DB_PASSWORD=your_pass
```

3) Generate key, migrate, dan seed

```bash
php artisan key:generate
php artisan migrate --force
# Opsional: isi divisi & contoh event
php artisan db:seed --class=Database\Seeders\DivisionSeeder
php artisan db:seed --class=Database\Seeders\EventSeeder
```

4) Jalankan aplikasi (dua terminal)

```bash
php artisan serve
npm run dev # atau: bun run dev
```

Buka `http://127.0.0.1:8000/calendar`.

## Development & Tooling
- `composer dev`: Server Laravel, queue listener, Pail log, dan Vite berjalan paralel.
- `composer setup`: Reset database (migrate:fresh --seed), build aset Bun, generate Scribe docs.
- `composer analyse|format|test`: PHPStan, Pint+Rector, dan suite Pest.
- `npm run build` / `bun run build`: Build aset produksi.

## Testing

```bash
composer test
```

Saat ini: 110 lewat, 3 dilewati (286 assertions).

## Production Notes
- Pastikan `APP_URL`, cookie Sanctum, dan `SESSION_DOMAIN` disetel sesuai domain produksi.
- Jalankan migrasi untuk `user_change_logs` dan `event_change_logs` agar audit trail berfungsi.
- Aktifkan Stripe, Sentry, Octane, dan Telescope sesuai kebutuhan.

## Troubleshooting
- Filter divisi tidak jalan: pastikan berada di branch terbaru; UI akan refetch saat chip berubah.
- Jam `Sepanjang hari` mengikuti jam kerja (07:30-16:00); ubah di UI lalu simpan ulang untuk event lama.
- Log kosong: cek bahwa migrasi log sudah dijalankan dan user bernilai admin.

## Documentation
- Dokumentasi API: `php artisan scribe:generate` -> `public/docs/index.html`.
- Panduan setup internal: `docs/calendar_setup.md`.

## License

MIT
