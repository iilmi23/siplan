# Dokumentasi SIPLAN

Indeks ini membantu mencari dokumen project tanpa harus membuka file satu per satu.

## Panduan Aplikasi

| Dokumen | Isi |
| --- | --- |
| [`panduan-alur-pengguna-id.md`](panduan-alur-pengguna-id.md) | Alur penggunaan aplikasi dalam Bahasa Indonesia. |
| [`laporan-magang-revisi.md`](laporan-magang-revisi.md) | Laporan magang dan latar belakang pengembangan SIPLAN. |

## Catatan Teknis

| Dokumen | Isi |
| --- | --- |
| [`architecture-hardening.md`](architecture-hardening.md) | Catatan penguatan arsitektur. |
| [`database-redesign.md`](database-redesign.md) | Catatan desain ulang database. |
| [`variance-analytics.md`](variance-analytics.md) | Catatan analytics variance. |
| [`variance-operational-refactor.md`](variance-operational-refactor.md) | Catatan refactor operasional variance. |

## Lokasi Kode Utama

| Area | Lokasi |
| --- | --- |
| Route aplikasi | `routes/web.php` |
| Controller | `app/Http/Controllers` |
| Service bisnis | `app/Services` |
| Model database | `app/Models` |
| Halaman frontend | `resources/js/Pages` |
| Komponen frontend | `resources/js/Components` |
| Migration | `database/migrations` |
| Test | `tests` |

## Catatan Perapihan

Dokumen besar sudah dipindahkan ke `docs/` dengan nama file kecil dan konsisten. File log QA dipindahkan ke `storage/logs/qa/` karena hanya artefak runtime.
