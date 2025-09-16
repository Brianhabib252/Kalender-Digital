# Alur Fitur Utama – Aplikasi Kalender Digital (Laravel + Vue.js + TailwindCSS + Inertia)

Dokumen ini menjelaskan *alur kerja* (flow) fitur-fitur utama aplikasi kalender internal untuk mengelola kegiatan berdasarkan tanggal & jam, menampilkan siapa/ divisi yang terlibat, timeline harian, rekap mingguan/bulanan, serta filter berdasarkan divisi.

---

## 1) Ringkasan Tujuan

* **Input kegiatan** pada tanggal & jam tertentu.
* **Kalender multi‑view**: Bulan, Minggu, Hari (dengan timeline jam).
* **Detail per tanggal**: daftar kegiatan, peserta (nama & divisi), lokasi/notes.
* **Rekap**: jumlah kegiatan per minggu & per bulan.
* **Filter**: divisi (Seluruh Pegawai, Hakim, Kesekretariatan, Kepaniteraan).
* **Teknologi**: Backend Laravel (API + Eloquent), Frontend Vue 3 + Inertia, styling Tailwind.

---

## 2) Peran Pengguna (opsional, jika dibutuhkan)

* **Admin**: CRUD kegiatan, kelola peserta & divisi, set akses.
* **User**: Lihat kalender, filter kegiatan, buat/ubah kegiatan yang ia kelola.
* (Opsional) **Viewer**: hanya membaca jadwal.

---

## 3) Struktur Data Inti

Minimal skema tabel (bisa disesuaikan):

**users**

* id, name, email, division\_id (nullable jika pakai pivot multi‑divisi), role

**divisions**

* id, name // enum: Seluruh Pegawai, Hakim, Kesekretariatan, Kepaniteraan

**events**

* id, title, description, location
* start\_at (datetime), end\_at (datetime)
* all\_day (boolean, default false)
* created\_by (user\_id)

**event\_participants** (pivot)

* id, event\_id, user\_id, participant\_role (opsional: organizer/member)

**event\_divisions** (opsional bila kegiatan bersifat lintas divisi tanpa menentukan orang satu per satu)

* id, event\_id, division\_id

> Catatan: Jika setiap user pasti punya satu divisi, cukup join melalui **users.division\_id** untuk menurunkan informasi divisi. Jika kegiatan melibatkan divisi secara umum (tanpa daftar nama), gunakan tabel **event\_divisions**.

---

## 4) Alur Fitur Utama

### 4.1 Membuat/ Mengubah Kegiatan (Create/Update Flow)

1. **Akses Form**: tombol "Buat Kegiatan" dari tampilan Kalender atau dari tombol global (+).
2. **Isi Form**: title, tanggal & jam mulai/selesai, all‑day (opsional), lokasi, deskripsi.
3. **Pilih Keterlibatan**:

   * Tambah peserta (autocomplete user), **atau**
   * Tambah divisi (checkbox: Hakim, Kesekretariatan, Kepaniteraan, Seluruh Pegawai).
4. **Validasi**: pastikan end\_at > start\_at; kolom wajib terisi.
5. **Simpan**: kirim via Inertia POST ke route Laravel; backend membuat **event** lalu menyimpan **participants** dan/atau **event\_divisions**.
6. **Feedback**: notifikasi sukses + refresh kalender (Inertia partial reload) ke range yang relevan.

### 4.2 Menampilkan Kalender Bulan (Month View)

* **Tujuan**: melihat semua kegiatan per tanggal.
* **Data**: query range (awal‑akhir bulan yang sedang dilihat + buffer minggu sebelumnya/berikutnya).
* **UI**: grid 7×(4‑6) minggu; setiap sel tanggal menampilkan chip kegiatan (maks 2‑3 + "+n" jika lebih).
* **Interaksi**:

  * Klik tanggal ⇒ **Panel Samping/Modal** menampilkan "Daftar Kegiatan Hari Ini" (lihat 4.3).
  * Hover/klik chip kegiatan ⇒ *tooltip* ringkas (judul, jam, divisi).

### 4.3 Detail per Tanggal (Daftar Kegiatan)

* **Tujuan**: melihat kegiatan pada tanggal tertentu, siapa & divisinya.
* **Data**: filter event yang *overlap* dengan tanggal tsb (00:00‑23:59 atau all\_day=true).
* **UI**: daftar kartu: judul, rentang jam, lokasi, badge divisi, avatar peserta.
* **Aksi**: buka detail kegiatan (modal/route detail), edit, hapus (berdasarkan izin).

### 4.4 Timeline Jam (Day View)

* **Tujuan**: melihat plot kegiatan per jam.
* **Data**: event pada tanggal terpilih; hitung posisi relatif terhadap jam (mis. 08:00‑17:00) + dukung all\_day di header.
* **UI**: kolom jam di kiri; event digambar sebagai blok sepanjang durasi; overlapping ditata kolom fleksibel.
* **Interaksi**: drag‑to‑create (opsional), drag‑to‑resize (opsional), klik blok untuk detail/edit.

### 4.5 Rekap Jumlah Kegiatan per Minggu & Bulan

* **Mingguan**: hitung jumlah event yang memiliki *overlap* dengan rentang Senin‑Minggu (atau sesuai locale).
* **Bulanan**: hitung jumlah event yang *overlap* bulan berjalan.
* **UI**: badge ringkas di header view; atau widget ringkasan ("Minggu ini: 12 kegiatan", "Bulan ini: 46").

### 4.6 Filter Berdasarkan Divisi

* **Filter Panel**: radio/checkbox untuk memilih: Seluruh Pegawai, Hakim, Kesekretariatan, Kepaniteraan (bisa multi‑select jika diinginkan).
* **Logika**:

  * Jika kegiatan berisi **participants**: tampilkan jika **ada** peserta dari divisi terpilih.
  * Jika kegiatan berisi **event\_divisions**: tampilkan jika ada **division\_id** yang terpilih.
* **Interaksi**: perubahan filter memicu fetch ulang data kalender via Inertia (query param ?division=hakim, dsb).

### 4.7 Pencarian (opsional)

* Kotak pencarian judul/ lokasi/ deskripsi.
* Dikombinasikan dengan filter divisi & rentang tanggal yang sedang aktif.

---

## 5) Arsitektur Frontend (Vue + Inertia)

**Komponen Utama**

* `CalendarLayout` (shell + filter + header view switch)
* `MonthView`, `WeekView`, `DayView`
* `DayCell` (untuk MonthView), `EventChip`, `EventBlock`
* `SidebarDayList` (daftar kegiatan tanggal terpilih)
* `EventFormModal` (create/update)

**Navigasi & State**

* State ringan via props/emit atau Pinia (untuk filter & range tanggal aktif).
* Perpindahan view (Bulan/Minggu/Hari) menjaga `currentDate` & `filters` di URL (Inertia remember state).

**Styling (Tailwind)**

* Grid kalender, utilities untuk background/status, badge divisi (warna netral konsisten).

---

## 6) Routing & Endpoint (Laravel)

**Web routes (Inertia pages)**

* `GET /calendar` → halaman utama (default MonthView)
* Query params: `view=month|week|day`, `date=YYYY-MM-DD`, `division=hakim|…`

**API (controller) contoh**

* `GET /events` – daftar event untuk rentang tanggal & filter divisi

  * Params: `start`, `end`, `division[]`, `q`
* `POST /events` – buat event
* `PUT /events/{id}` – update event
* `DELETE /events/{id}` – hapus event

**Contoh Payload `POST /events`**

```json
{
  "title": "Rapat Koordinasi",
  "description": "Pembahasan perkara minggu berjalan",
  "location": "Ruang Sidang 1",
  "start_at": "2025-09-18T09:00:00",
  "end_at": "2025-09-18T10:30:00",
  "all_day": false,
  "participant_user_ids": [12, 45, 71],
  "division_ids": [2] // mis. 2=Hakim
}
```

---

## 7) Logika Query (Eloquent) – Inti

**Ambil event yang overlap rentang**

```php
Event::query()
  ->when($start && $end, fn($q) => $q->where(function($w) use ($start, $end) {
      $w->whereBetween('start_at', [$start, $end])
        ->orWhereBetween('end_at', [$start, $end])
        ->orWhere(function($ov) use ($start, $end) {
          $ov->where('start_at', '<=', $start)
             ->where('end_at', '>=', $end);
        });
  }))
  ->with(['participants.user.division', 'divisions'])
  ->get();
```

**Filter berdasarkan divisi**

```php
->when($divisionIds, function($q) use ($divisionIds) {
  $q->where(function($w) use ($divisionIds) {
    // event via participants
    $w->whereHas('participants.user', fn($p) => $p->whereIn('division_id', $divisionIds))
      // atau event ditandai langsung ke divisi
      ->orWhereHas('divisions', fn($d) => $d->whereIn('division_id', $divisionIds));
  });
})
```

**Hitung jumlah kegiatan mingguan**

```php
$weeklyCount = Event::overlap($weekStart, $weekEnd)->count();
```

**Hitung jumlah kegiatan bulanan**

```php
$monthlyCount = Event::overlap($monthStart, $monthEnd)->count();
```

> *Tips:* Buat **scope** `overlap($start,$end)` di model Event untuk DRY.

---

## 8) Flow UI (Skenario Interaksi)

**A. Melihat beban jadwal satu bulan**

1. User buka `/calendar?view=month&date=2025-09-01`.
2. Aplikasi fetch `/events?start=2025-08-25&end=2025-10-05&division[]=all`.
3. Grid bulan tampil; setiap tanggal menampilkan ringkas kegiatan.
4. User set filter `division=Hakim` ⇒ reload data hanya kegiatan Hakim.

**B. Fokus ke hari tertentu & timeline**

1. User klik 2025‑09‑18.
2. Panel kanan memuat daftar kegiatan hari itu + tombol "Lihat Timeline".
3. Klik ⇒ ganti ke `view=day` dengan timeline jam; blok‑blok event tergambar proporsional.

**C. Membuat kegiatan baru**

1. User klik `+` di 2025‑09‑18 pukul 09:00 (drag‑to‑create).
2. Modal form muncul; user melengkapi data & peserta/divisi.
3. Submit ⇒ notifikasi sukses; kalender di-refresh.

---

## 9) Pertimbangan UX & Kinerja

* **Virtualization** untuk banyak event (month view) agar tetap responsif.
* **Partial reload Inertia**: hanya refetch `/events` saat filter/ tanggal berubah.
* **Debounce** pencarian.
* **Aksesibilitas**: navigasi keyboard, kontrast warna Tailwind, ARIA pada modal.
* **Timezone**: simpan UTC di DB, tampilkan sesuai locale.

---

## 10) Validasi & Edge Cases

* Event all‑day yang menyeberang beberapa hari tetap muncul di setiap hari terkait.
* Event lintas hari (mulai malam, selesai pagi) harus tampil di dua tanggal (month) dan dua segmen (day view).
* Bentrok jadwal: tandai overlap (warna/border khusus) atau per kolom.
* Hak akses: hanya pembuat/ admin yang dapat edit/hapus.

---

## 11) Roadmap (Opsional)

* Repeating events (RRULE sederhana: harian/mingguan/bulanan).
* Notifikasi (email/telegram) minus‑1 jam/ hari.
* Impor/ekspor iCal.
* Integrasi SSO & audit log perubahan event.

---

## 12) Ringkasan Manfaat

* Satu sumber kebenaran jadwal organisasi.
* Transparansi keterlibatan orang & divisi.
* Pengambilan keputusan lebih cepat lewat rekap mingguan/bulanan & filter divisi.
