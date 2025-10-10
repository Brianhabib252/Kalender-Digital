# Kalender Digital – Setup & Wiring

Langkah untuk mengaktifkan fitur Kalender (Laravel + Vue 3 + Inertia + Tailwind) yang sudah ditambahkan ke repo ini.

## 1) Migrasi Database (MySQL)

Pastikan `.env` sudah diarahkan ke MySQL yang benar:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kalender_digital
DB_USERNAME=your_user
DB_PASSWORD=your_pass
```

Lalu jalankan migrasi & seeder divisi:

```
php artisan migrate
php artisan db:seed --class=\Database\Seeders\DivisionSeeder
```

Tabel yang dibuat:

- `divisions`, `events`, `event_participants`, `event_divisions`
- `users` akan ditambahkan kolom `division_id` (nullable)

## 2) Routing

Tambahkan baris berikut di akhir `routes/web.php` untuk halaman kalender (Inertia):

```php
require __DIR__.'/calendar.php';
```

Tambahkan baris berikut di akhir `routes/api.php` untuk API event:

```php
require __DIR__.'/api_calendar.php';
```

Endpoint API yang tersedia:

- `GET /api/events?start=ISO&end=ISO&division[]=ID&q=...`
- `POST /api/events`
- `PUT /api/events/{id}`
- `DELETE /api/events/{id}`

## 3) Frontend

Halaman utama kalender: buka `GET /calendar`.

Komponen inti berada di:

- `resources/js/Pages/Calendar/Index.vue`
- `resources/js/Components/Calendar/*`

Pastikan bundler (Vite) sudah terpasang sesuai template Inertia/Vue yang digunakan. Jalankan dev server:

```
php artisan serve
npm run dev
```

## 4) Hak Akses (opsional)

Saat ini controller API memperbolehkan akses tanpa policy khusus. Sesuaikan `authorize()` atau gunakan Policy/Gate sesuai kebutuhan.

## 5) Catatan

- Pencarian (param `q`) dan filter divisi sudah didukung di endpoint `GET /api/events`.
- Form peserta masih berupa input ID (CSV). Anda bisa menghubungkan autocomplete user sesuai library yang tersedia di proyek.
- Untuk perulangan jadwal (RRULE) belum dibuat—masuk ke roadmap.
