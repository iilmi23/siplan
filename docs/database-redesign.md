# SIPLAN Database Redesign

Dokumen ini adalah blueprint sebelum fresh migrate. Tujuannya bukan menghapus semua kolom lama sekaligus, tetapi membuat sumber data utama lebih jelas supaya aplikasi bisa dirapikan bertahap.

## Prinsip

- Master data tetap di tabel master: `customers`, `ports`, `carline`, `assy`, `production_weeks`.
- File upload dicatat sebagai header di `upload_batches`.
- Baris hasil upload SR tetap di `srs` sebagai detail operasional.
- `summaries`, `spp`, dan `sr_variance_*` diperlakukan sebagai hasil proses/materialized data.
- Kolom snapshot lama seperti `customer`, `port`, `source_file`, `upload_batch`, dan `sheet_name` masih dipertahankan sementara untuk kompatibilitas UI dan query lama.
- Foreign key baru dipakai sebagai arah refactor: kode baru sebaiknya membaca dari relasi, bukan dari snapshot string.

## Struktur Target Bertahap

### Master

- `customers`: master customer.
- `ports`: master port per customer.
- `carline`: master carline.
- `assy`: master assy, relasi ke `carline`.
- `production_weeks`: kalender production week global atau per customer.
- `etd_mappings`: mapping ETD ke production week.

### Upload SR

- `upload_batches`: header upload, sekarang punya `batch_type`, `processed_at`, `failed_at`, dan `metadata`.
- `srs`: detail baris SR upload.
- `srs.production_week_id`: relasi ke `production_weeks`, ditambahkan lewat migration terpisah karena urutan migration.

### Materialized Output

- `summaries`: ringkasan per batch/assy/order/period. Ini boleh menyimpan snapshot karena hasil proses.
- `spp_batches`: header untuk hasil generate SPP fixed.
- `spp_batch_sources`: pivot sumber SR upload untuk satu SPP batch, terutama untuk combined SPP.
- `spp`: detail hasil SPP, sekarang bisa menunjuk ke `spp_batch_id`.
- `sr_variance_*`: hasil analisa variance, tetap materialized.

## Arah Refactor Berikutnya

1. Query filter customer sebaiknya pindah dari string `customer` ke `customer_id`/relasi `uploadBatch.customer`.
2. Query filter port sebaiknya pindah dari string `port` ke `port_id`/relasi `uploadBatch.port`.
3. Setelah UI dan service aman, kolom snapshot di `srs` bisa dihapus atau dipindah ke `extra`.
4. Untuk `summaries`, pertahankan snapshot secukupnya karena tabel ini materialized. Sumber kebenaran tetap `srs` + master.
5. Untuk `spp`, gunakan `spp_batches` sebagai header utama dan `spp_batch_sources` untuk melacak SR upload sumbernya.

## Migration Order Note

`srs` dibuat sebelum `production_weeks`, jadi kolom `production_week_id` dibuat dulu sebagai nullable integer, lalu foreign key dibuat di:

`2026_04_23_080000_add_production_week_foreign_to_srs_table.php`

Ini menjaga fresh migrate tetap berjalan sesuai urutan timestamp.
