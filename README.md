# Invoice Tracker — Laravel Blade + MySQL (Standard MVC)

Konversi dari Google Apps Script `Invoice_Tracker_v35_11_handover_search` ke struktur Laravel yang konvensional: route web biasa, controller per fitur, Blade layout, view per role, Eloquent model, middleware, migration, dan MySQL.

## Struktur utama

- `routes/web.php` — route aplikasi, tanpa pola RPC tunggal.
- `app/Http/Controllers/Auth/LoginController.php` — login/logout session Laravel.
- `app/Http/Controllers/DashboardController.php` — memilih dashboard sesuai role.
- `app/Http/Controllers/InvoiceController.php` — create, edit, work update, history, delete.
- `app/Http/Controllers/InvoiceTransitionController.php` — perpindahan workflow invoice.
- `app/Http/Controllers/AdminUserController.php` — kelola user.
- `app/Http/Controllers/AdminActivityController.php` — aktivitas dan audit pembatalan.
- `app/Services/InvoiceTrackerService.php` — aturan bisnis/workflow agar controller tetap tipis.
- `resources/views/layouts/app.blade.php` — layout dashboard.
- `resources/views/layouts/guest.blade.php` — layout halaman publik/login.
- `resources/views/dashboard/*.blade.php` — Blade terpisah Admin, Resepsionis, User/PIC, Accounting.
- `resources/views/partials/modals.blade.php` — modal bersama.
- `public/js/dashboard.js` — interaksi AJAX ke route Laravel normal; bukan `google.script.run` dan bukan `/rpc`.

## Route utama

```text
GET    /                         cek posisi invoice publik
GET    /login                    form login
POST   /login                    proses login
POST   /logout                   logout
GET    /dashboard                dashboard sesuai role
POST   /invoices                 buat invoice
PUT    /invoices/{invoice}       edit invoice
PATCH  /invoices/{invoice}/work  update pekerjaan/checklist
PATCH  /invoices/{invoice}/transition perpindahan status
GET    /invoices/{invoice}/history riwayat invoice
DELETE /invoices/{invoice}       hapus invoice (Admin)
GET    /admin/users              daftar user
POST   /admin/users              simpan user
GET    /admin/activity           aktivitas terbaru
GET    /admin/cancelled-invoices audit pembatalan
```

## Instalasi di Laragon

Project membutuhkan PHP 8.3+ dan MySQL.

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Buat database MySQL, contoh `invoice_tracker`, lalu sesuaikan `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invoice_tracker
DB_USERNAME=root
DB_PASSWORD=
```

Jalankan:

```bash
php artisan migrate --seed
php artisan optimize:clear
php artisan serve
```

Akun seed awal:

```text
admin        / 1234
resepsionis  / 1234
pic01        / 1234
acc01        / 1234
acc02        / 1234
```

> Ganti password akun seed sebelum dipakai untuk produksi.

## Catatan data lama

ZIP GAS yang menjadi sumber hanya berisi source code, bukan isi Spreadsheet. Panduan pemetaan data lama tersedia di `docs/MIGRASI-DATA-GAS.md`.
