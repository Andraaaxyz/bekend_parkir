# Sistem Manajemen Parkir API

Proyek ini adalah backend RESTful API berbasis **Laravel** untuk mengelola aktivitas operasional parkir secara sistematis dan otomatis. API ini menangani alur dari saat kendaraan masuk (check-in), mencatat waktu parkir, hingga kendaraan keluar (check-out) dengan kalkulasi tarif otomatis berdasarkan durasi parkir dan jenis kendaraan. Sistem juga mencakup pemantauan kapasitas area parkir secara real-time.

---

## 🚀 Fitur Utama

1. **Autentikasi & Otorisasi**: Mengamankan endpoint API dengan otentikasi berbasis token (Laravel Sanctum), memungkinkan role akses dasar (seperti Admin dan Operator).
2. **Manajemen Area Parkir**: Melacak area parkir yang tersedia, batas kapasitas maksimal (`kapasitas`), dan jumlah kendaraan yang sedang parkir (`terisi`).
3. **Manajemen Kendaraan & Tarif**: Pendaftaran jenis-jenis kendaraan (Motor, Mobil, dsb.) beserta tarif parkirnya secara dinamis.
4. **Transaksi Parkir Otomatis**: 
   - Transaksi Check-in (Mencatat Waktu Masuk & Validasi Kapasitas).
   - Transaksi Check-out (Mencatat Waktu Keluar, Menghitung Durasi, dan Total Biaya Parkir secara otomatis).
5. **Log Aktivitas**: Merekam semua log tindakan penting di dalam sistem untuk kebutuhan audit dan *monitoring*.

---

## 🗄️ Struktur Database (Relasi Model)

Backend ini menggunakan Eloquent ORM dengan beberapa tabel utama:
- `tb_user` / `users`: Berisi data admin dan operator parkir yang memiliki akses ke dalam sistem.
- `tb_area_parkir`: Menyimpan nama/lokasi parkir, total kapasitas maksimal, dan menghitung otomatis slot kendaraan (`terisi` / tersisa).
- `tb_kendaraan`: Master data jenis kendaraan dan plat nomor (nopol) yang terdaftar/masuk.
- `tb_tarif`: Master harga parkir. Tarif bisa dibedakan berdasarkan jenis kendaraan atau parameter lainnya.
- `tb_transaksi`: Tabel utama operasional parkir. Menyimpan `id_kendaraan`, `waktu_masuk`, `waktu_keluar`, `durasi`, `biaya_total`, dan `status` kendaraan (apakah masih "masuk" atau sudah "keluar"). Terhubung dengan area parkir, tarif, dan user yang melayani.
- `tb_log_aktivitas`: Mencatat seluruh audit trail / log aktivitas API oleh user/operator.

---

## ⚙️ Rincian Cara Kerja / Alur Sistem (Flow)

### 1. Proses Check-In (Kendaraan Masuk)
Ketika ada kendaraan yang akan masuk area parkir, API menerima _request_ (misalnya Plat Nomor & Jenis Kendaraan dari sisi Front-End/Gate).
* **Validasi Kapasitas**: Sistem memanggil fungsi dari model `TbAreaParkir` (`$area->isTersedia()`). Jika kapasitas (misal: 100) sudah penuh (terisi = 100), maka API me-return error "Area Parkir Penuh!".
* **Pencatatan Data**: Jika kapasitas tersedia, sistem akan membuat *record* baru di `TbTransaksi`.
  - Kolom `waktu_masuk` diisi dengan waktu sekarang (`now()`).
  - Kolom `status` diisi dengan "masuk".
  - ID Kendaraan, Area, dan User (Operator yang sedang login) dicatat.
* **Update Area Parkir**: Sistem akan secara otomatis menambahkan angka pada atribut `terisi` di tabel `TbAreaParkir` (`terisi` + 1).

### 2. Proses Check-Out (Kendaraan Keluar)
Saat kendaraan akan keluar, sistem membaca Plat Nomor atau ID Transaksi karcis parkir.
* **Mencari Transaksi Aktif**: Sistem Query ke `TbTransaksi` untuk mencari data kendaraan tersebut yang statusnya belum keluar (masih "masuk").
* **Kalkulasi Otomatis**: 
  - Kolom `waktu_keluar` dicatat (`now()`).
  - Sistem mencari selisih waktu antara `waktu_masuk` dan `waktu_keluar` menggunakan _Carbon_, dan mendapatkan `durasi` (dalam jam).
  - Mengambil tarif per-jam (mengacu *foreign key* `id_tarif`).
  - `biaya_total` dikalkulasi (misal: `durasi` x `tarif_per_jam`). Jika ada tarif minimum/maksimal, hal tersebut diberlakukan pada fase ini.
* **Penyelesaian Transaksi**: Kolom di-update dengan biaya nominal dan mengganti `status` transaksi menjadi "keluar".
* **Update Area Parkir**: Angka atribut `terisi` pada `TbAreaParkir` otomatis dikurangi 1 (`terisi` - 1), sehingga slot tersebut dapat digunakan kendaraan lain.

### 3. Log dan Laporan
Setiap proses Check-in maupun Check-out yang dilakukan oleh operator, atau perubahan referensi dasar (seperti mengubah tarif), akan terekam ke dalam tabel `TbLogAktivitas`. Hal ini berguna jika suatu saat membutuhkan laporan pendapatan (berdasarkan `TbTransaksi` yang berstatus keluar) dan pengecekan kebocoran/insiden operasional.

---

## 🛠️ Instalasi & Konfigurasi Development

Untuk menjalankan project ini di environment lokal / server dev Anda:

1. **Clone & Masuk ke Direktori**
   Pastikan Anda sudah meletakkan folder project pada path yg sesuai (misal: `laragon/www/parkiryz/`).
   
2. **Install Dependensi Composer**
   Buka terminal di dalam directory project dan jalankan:
   ```bash
   composer install
   ```

3. **Konfigurasi Lingkungan (.env)**
   Salin file konfigurasi *environment*:
   ```bash
   cp .env.example .env
   ```
   Lalu, sesuaikan bagian spesifik Database setup-nya:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=parkiryz
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   *(Pastikan Anda telah membuat database yang kosong dengan nama di atas pada MySQL Anda/PhpMyAdmin)*.

4. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Migrasi Database & Seeding Data**
   Jalankan migrasi untuk men-generate otomatis struktur tabel beserta *dummy data* awal (seperti admin default, tarif dasar, dan area uji coba).
   ```bash
   php artisan migrate --seed
   ```

6. **Jalankan Development Server**
   ```bash
   php artisan serve
   ```
   API kini siap dikonsumsi di `http://127.0.0.1:8000/api/`

---
*Dokumentasi ini dibuat untuk memandu developer maupun analis sistem dalam memahami proses core logic di balik Sistem Manajemen Parkir ini.*
