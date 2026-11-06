# Spec: Procurement Job Sub-Fields Management & Vendor Eligibility Filter

## Problem Statement

Saat ini, setiap paket pengadaan (`Procurement`) hanya menyimpan satu jenis pekerjaan (`JobType`) tanpa memiliki daftar sub-bidang pekerjaan (`JobSubField`) yang lebih spesifik di bawah jenis pekerjaan tersebut. Akibatnya:
1. Panitia pengadaan belum dapat menentukan sub-bidang pekerjaan apa saja yang menjadi syarat kualifikasi teknis/bidang usaha pada suatu paket pengadaan.
2. Sistem belum memiliki dasar data sub-bidang pada paket pengadaan untuk memvalidasi dan memfilter apakah seorang penyedia (`Vendor`) memenuhi syarat bidang/sub-bidang ketika hendak mendaftar (*apply*) pada paket pengadaan tersebut.

## Solution

Menambahkan relasi *many-to-many* antara `Procurement` dan `JobSubField` melalui tabel pivot khusus yang dikelola lewat endpoint sub-resource terpisah (`/procurement/{id}/job-sub-field`), sejalan dengan pola pengelolaan jadwal pengadaan (`schedule`).

Setiap `Procurement` dapat memiliki banyak `JobSubField` selama seluruh `JobSubField` tersebut berada di bawah `JobType` yang sama dengan `job_type_id` milik `Procurement`. Apabila `job_type_id` pada `Procurement` diubah, daftar `JobSubField` yang sudah tersimpan pada paket tersebut akan otomatis di-reset agar konsistensi data terjaga. Selain itu, model `Procurement` menyediakan mekanisme pengecekan kecocokan (*eligibility matching*) berbasis aturan irisan minimal satu (**OR** / minimal 1 `JobSubField` cocok) yang siap digunakan untuk memvalidasi pendaftaran vendor (*vendor apply*) dan menolak vendor yang tidak memiliki sub-bidang yang sesuai.

## User Stories

1. As a procurement officer, I want to view the list of job sub-fields assigned to a specific procurement package via a dedicated endpoint, so that I can see which sub-fields are currently required for that procurement.
2. As a procurement officer, I want each assigned job sub-field in the list response to include its parent job field and job type metadata, so that the UI can display complete hierarchical context without extra API calls.
3. As a procurement officer, I want to assign multiple job sub-fields to a procurement package in a single bulk/sync request, so that I can configure all required sub-fields efficiently.
4. As a procurement officer, I want to replace or update the entire list of job sub-fields on a procurement package via the bulk sync endpoint, so that any additions or removals made in the form are persisted atomically.
5. As a procurement officer, I want to clear all job sub-fields from a procurement package by sending an empty array to the sync endpoint (or deleting individual items), so that I can reset the sub-field requirements while the procurement is still being prepared.
6. As a procurement officer, I want to remove a single specific job sub-field from a procurement package via a delete endpoint, so that I can quickly drop an individual sub-field requirement without re-submitting the full list.
7. As a procurement officer, I want the system to validate that every job sub-field assigned to a procurement belongs to the same job type (`job_type_id`) as the procurement itself, so that invalid cross-type sub-fields cannot be attached to a procurement package.
8. As a procurement officer, I want the system to reject non-existent, soft-deleted, or inactive job sub-field IDs when assigning sub-fields to a procurement, so that only valid master data can be used.
9. As a procurement officer, I want duplicate job sub-field IDs in the request payload to be deduplicated automatically without causing database unique constraint errors, so that the API behaves idempotently.
10. As a procurement officer, I want the system to prevent modifying (adding, syncing, or deleting) job sub-fields on a cancelled procurement, so that historical data of cancelled procurements remains immutable.
11. As a procurement officer, I want the assigned job sub-fields of a procurement to be automatically cleared when I change the procurement's `job_type_id`, so that stale job sub-fields from the previous job type do not remain attached.
12. As a procurement officer, I want the assigned job sub-fields of a procurement to remain intact when I update other procurement attributes without changing `job_type_id`, so that I do not lose configured sub-fields during normal edits.
13. As a procurement officer, I want the pivot records of assigned job sub-fields to be cleaned up when a procurement is deleted, so that no orphaned associations remain.
14. As a system administrator, I want the read endpoint for procurement job sub-fields to require the `procurement_read` permission, so that only authorized users can view internal procurement configuration.
15. As a system administrator, I want the sync and delete endpoints for procurement job sub-fields to require the `procurement_update` permission, so that unauthorized users cannot alter procurement requirements.
16. As a procurement detail consumer, I want the main procurement detail (`GET /procurement/{id}`) and list (`GET /procurement`) responses to remain lightweight and unchanged (without embedding `job_sub_fields`), so that existing API contracts remain stable and sub-fields are fetched only when needed via `/procurement/{id}/job-sub-field`.
17. As the procurement application service, I want to check whether a vendor's list of owned `job_sub_field_id` values matches at least one (`OR` condition) of the procurement's required `job_sub_field_id` values, so that eligible vendors can proceed to apply.
18. As the procurement application service, I want a vendor application attempt to fail/be rejected when none of the vendor's `job_sub_field_id` values intersect with the procurement's required `job_sub_field_id` values, so that unqualified vendors cannot apply for the procurement.
19. As the procurement application service, I want a vendor application attempt to fail/be rejected when a procurement has no job sub-fields configured yet or when the vendor has no job sub-fields at all, so that applications only occur against properly matched sub-field qualifications.

## Implementation Decisions

- **Pivot Schema (`procurement_job_sub_fields`)**:
  - Menyimpan relasi *many-to-many* antara `procurements` dan `job_sub_fields`.
  - Kolom yang dibutuhkan: `procurement_id` (foreign key ke `procurements` dengan `cascadeOnDelete`), `job_sub_field_id` (foreign key ke `job_sub_fields` dengan `cascadeOnDelete`), serta `created_at` dan `updated_at`.
  - Menggunakan composite primary key pada `(procurement_id, job_sub_field_id)` (selaras dengan tabel pivot `role_user` dan `role_permission` pada Oracle) untuk mencegah duplikasi baris di level database.

- **Domain & Model Relationship**:
  - Model `Procurement` memiliki relasi `belongsToMany` ke `JobSubField` melalui tabel `procurement_job_sub_fields` dengan timestamps.
  - Model `JobSubField` memiliki relasi kebalikannya (`belongsToMany` ke `Procurement`) melalui tabel pivot yang sama.

- **Konsistensi terhadap `job_type_id` Procurement**:
  - Kolom tunggal `job_type_id` pada tabel `procurements` tetap dipertahankan sebagai Jenis Pekerjaan utama paket pengadaan.
  - Setiap `JobSubField` yang dipasangkan ke suatu `Procurement` wajib memiliki `job_type_id` yang sama persis dengan `procurement.job_type_id`, berstatus aktif (`is_active = true`), dan belum dihapus (`deleted_at IS NULL`).
  - Apabila `job_type_id` pada `Procurement` diubah melalui endpoint update procurement (`POST /procurement/{id}`), seluruh relasi `job_sub_fields` pada procurement tersebut otomatis di-reset (`detach()`) di dalam transaksi database yang sama.
  - Apabila `Procurement` dihapus (`DELETE /procurement/{id}`), relasi `job_sub_fields` ikut di-detach di dalam transaksi penghapusan.

- **Dedicated Sub-Resource API Contract (`/procurement/{id}/job-sub-field`)**:
  - Data `job_sub_fields` tidak disisipkan ke dalam response `GET /procurement` maupun `GET /procurement/{id}`, melainkan diakses dan dikelola melalui endpoint khusus (mengikuti pola `/procurement/{id}/schedule`):
    - `GET /api/procurement/{id}/job-sub-field` (permission: `procurement_read`): Mengembalikan daftar `JobSubField` yang terhubung ke procurement tersebut, lengkap dengan informasi relasi `job_type` (`id`, `name`) dan `job_field` (`id`, `name`).
    - `POST /api/procurement/{id}/job-sub-field` (permission: `procurement_update`): Menerima payload `{ "job_sub_field_ids": [1, 2, ...] }` (array of integers, boleh kosong `[]` untuk mengosongkan) dan melakukan operasi *sync/replace* terhadap daftar sub-bidang procurement.
    - `DELETE /api/procurement/{id}/job-sub-field/{jobSubFieldId}` (permission: `procurement_update`): Menghapus satu `JobSubField` tertentu dari procurement. Jika `jobSubFieldId` tersebut tidak terhubung pada procurement terkait, kembalikan respons error/tidak ditemukan yang sesuai.
  - Semua operasi mutasi (`POST` dan `DELETE`) wajib menolak perubahan apabila `procurement.status === 'cancelled'`.

- **Aturan Filter / Validasi Kecocokan Vendor Apply**:
  - Menggunakan logika **OR (irisan minimal 1)**: Vendor dinyatakan memenuhi syarat sub-bidang pada suatu `Procurement` apabila terdapat minimal 1 `job_sub_field_id` milik vendor yang ada di dalam daftar `job_sub_field_id` milik `Procurement`.
  - Apabila `Procurement` belum memiliki `JobSubField` sama sekali, atau vendor tidak memiliki `JobSubField` sama sekali, atau irisan keduanya kosong, maka hasil kecocokan adalah `false` (tidak bisa apply / gagal).
  - Menyediakan method domain pada model `Procurement` (misal `matchesVendorJobSubFields(array $vendorJobSubFieldIds): bool`) agar modul pendaftaran vendor (*Vendor Apply*) nantinya cukup memanggil satu kontrak ini.

- **Dokumentasi OpenAPI**:
  - Menambahkan spesifikasi endpoint `GET /procurement/{id}/job-sub-field`, `POST /procurement/{id}/job-sub-field`, dan `DELETE /procurement/{id}/job-sub-field/{jobSubFieldId}` ke dalam spesifikasi OpenAPI proyek.

## Testing Decisions

- **Prinsip Pengujian**:
  - Menguji perilaku eksternal (*external behavior*) melalui kontrak HTTP JSON dan state hasil akhir, bukan menguji detail implementasi internal.
- **Seam 1 — HTTP API Feature Seam (`ApiTestCase`)**:
  - Mengikuti pola prior art pada `ProcurementScheduleTest` dan `ProcurementTest`.
  - Menguji skenario end-to-end:
    - `GET /api/procurement/{id}/job-sub-field` mengembalikan daftar sub-bidang beserta `job_field` dan `job_type`.
    - `POST /api/procurement/{id}/job-sub-field` berhasil melakukan bulk sync dan menggantikan daftar sebelumnya.
    - `POST /api/procurement/{id}/job-sub-field` menolak `job_sub_field_id` yang berasal dari `job_type_id` berbeda dengan `job_type_id` milik procurement.
    - `POST /api/procurement/{id}/job-sub-field` menolak `job_sub_field_id` yang tidak valid / sudah di-soft-delete / tidak aktif.
    - `DELETE /api/procurement/{id}/job-sub-field/{jobSubFieldId}` berhasil menghapus 1 item sub-bidang dari procurement.
    - Mutasi (`POST` & `DELETE`) ditolak ketika status procurement adalah `cancelled`.
    - Mengubah `job_type_id` pada `POST /api/procurement/{id}` otomatis mengosongkan daftar `job_sub_fields` procurement tersebut, sedangkan update field lain (tanpa mengubah `job_type_id`) tidak menghapus `job_sub_fields`.
    - Pengecekan otorisasi permission (`procurement_read` dan `procurement_update`).
- **Seam 2 — Domain Eligibility Seam (`Procurement::matchesVendorJobSubFields`)**:
  - Menguji aturan kecocokan sub-bidang vendor terhadap procurement:
    - Mengembalikan `true` jika vendor memiliki minimal 1 `job_sub_field_id` yang beririsan dengan sub-bidang procurement.
    - Mengembalikan `false` jika seluruh `job_sub_field_id` milik vendor berbeda dari yang disyaratkan procurement.
    - Mengembalikan `false` jika daftar sub-bidang vendor kosong atau jika procurement belum memiliki sub-bidang.

## Out of Scope

- Pembuatan tabel/model `Vendor` dan pengelolaan profil sub-bidang milik vendor.
- Pembuatan endpoint pendaftaran/lamaran vendor ke paket pengadaan (*Vendor Apply Procurement* endpoint), yang nantinya akan mengonsumsi helper `matchesVendorJobSubFields`.
- Perubahan pada struktur response `GET /api/procurement` dan `GET /api/procurement/{id}`.

## Further Notes

- Pola arsitektur sub-resource `/procurement/{id}/job-sub-field` dibuat konsisten dengan `/procurement/{id}/schedule` yang sudah ada di codebase agar konvensi routing, validasi (`RuleTrait`), dan format respons (`baseResponse`, `invalidResponse`, `errorValidateResponse`) tetap seragam.
