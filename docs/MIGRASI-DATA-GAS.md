# Mapping migrasi data GAS → MySQL

## Invoices
- ID → `invoices.id`
- No Invoice → `invoice_no`
- No PO → `po_no`
- Supplier → `supplier`
- Tanggal Masuk → `received_date`
- Amount → `amount`
- No Tanda Terima → `receipt_no`
- Jatuh Tempo → `due_date`
- Status → `status`
- Kekurangan Dokumen → `missing_documents`
- Keterangan → `notes`
- PIC / User → cari `users.id`, simpan ke `pic_user_id`
- Posisi Saat Ini → `position`
- Status Updated At → `status_updated_at`
- Tanggal Selesai → `completed_at`
- Accounting PIC → cari `users.id`, simpan ke `accounting_pic_id`

`Status Jatuh Tempo` dan `Sisa Hari Jatuh Tempo` tidak perlu disimpan lagi karena dihitung real-time dari `due_date` + `status`.

## Users
Password lama dari Spreadsheet sebaiknya tidak diimpor langsung sebagai hash. Buat/reset password dari dashboard Admin setelah user dimigrasikan.

## ActivityLog
- History ID → `activity_logs.id`
- Invoice ID → `invoice_id`
- No Invoice → `invoice_no`
- Aktor Username/Nama/Role → snapshot actor fields
- Status Sebelum/Sesudah → `from_status` / `to_status`
- Durasi → `duration_hours`
- Catatan → `note`
