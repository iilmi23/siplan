# SIPLAN

SIPLAN adalah aplikasi web untuk membantu proses Production Planning and Control, terutama pengelolaan Shipping Release, master data produksi, Summary, Six-Month Production Plan, variance, dan export Excel.

Project ini menggunakan Laravel 12 untuk backend, React + Inertia untuk frontend, Vite untuk bundling, dan TypeScript untuk halaman frontend baru.

## Mulai Cepat

1. Salin konfigurasi environment.

   ```bash
   cp .env.example .env
   ```

2. Install dependency backend dan frontend.

   ```bash
   composer install
   npm install
   ```

3. Siapkan aplikasi dan database.

   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```

4. Jalankan mode pengembangan.

   ```bash
   composer dev
   ```

   Alternatifnya, jalankan backend dan frontend terpisah:

   ```bash
   php artisan serve
   npm run dev
   ```

## Struktur Folder

| Lokasi | Isi |
| --- | --- |
| `app/Http/Controllers` | Controller Laravel untuk halaman dan API web. |
| `app/Http/Requests` | Validasi request per fitur. |
| `app/Models` | Model Eloquent dan relasi data. |
| `app/Services` | Logika bisnis utama seperti upload SR, Summary, SPP, Week Resolver, dan variance. |
| `app/Exports` | Export Excel. |
| `database/migrations` | Definisi struktur tabel. |
| `database/seeders` | Data awal aplikasi. |
| `resources/js/Pages` | Halaman Inertia React. |
| `resources/js/Components` | Komponen UI yang dipakai ulang. |
| `resources/js/Layouts` | Layout utama aplikasi. |
| `resources/css` | Styling global. |
| `routes` | Definisi route web, auth, console, dan API. |
| `storage/templates` | Template file Excel yang dipakai aplikasi. |
| `tests` | Test feature dan unit. |
| `docs` | Dokumentasi teknis dan catatan pengembangan. |

## Peta Fitur

| Fitur | Backend | Frontend |
| --- | --- | --- |
| Dashboard | `DashboardController` | `resources/js/Pages/Admin/Dashboard.tsx` |
| Upload Shipping Release | `SRController`, `SRUploadService`, `app/Services/SR` | `resources/js/Pages/UploadSR/Index.tsx` |
| Summary | `SummaryController`, `SummaryService`, `SummaryGeneratorService` | `resources/js/Pages/Summary` |
| SPP | `SPPController`, `SPPService` | `resources/js/Pages/SPP` |
| Variance | `VarianceController`, `app/Services/Variance` | `resources/js/Pages/Variance` |
| Master Customer | `CustomerController` | `resources/js/Pages/Master/Customer` |
| Master Port | `PortController` | `resources/js/Pages/Master/Ports` |
| Master Carline | `CarlineController`, `CarlineService` | `resources/js/Pages/Master/Carline` |
| Master Assy | `AssyController`, `AssyService` | `resources/js/Pages/Master/Assy` |
| Production Week | `ProductionWeekController`, `ProductionWeekService` | `resources/js/Pages/Master/ProductionWeek` |
| Template Mapping SR | `SRMappingTemplateController`, `SRMappingTemplateService` | `resources/js/Pages/Master/SRMappingTemplate` |
| User Management | `UserController` | `resources/js/Pages/Admin/Users` |

## Perintah Penting

```bash
npm run dev
npm run build
npm run typecheck
composer test
vendor/bin/pint
```

## Dokumentasi

Lihat [docs/README.md](docs/README.md) untuk indeks dokumentasi teknis, panduan pengguna, dan laporan.

## Catatan Kebersihan Workspace

File `.log`, profil Chrome QA, `node_modules`, `vendor`, build frontend, dan cache lokal tidak perlu dicari lewat Git. Aturan ignore ada di `.gitignore` supaya hasil `git status` dan pencarian project tetap lebih rapi.
