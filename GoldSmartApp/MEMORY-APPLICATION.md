# GoldSmartApp (Frontend) - Struktur Folder

Dokumen ini menjelaskan struktur folder utama untuk aplikasi mobile frontend React Native (Expo) dari GoldSmart.

## Root Directory
- `assets/`
  Menyimpan file statis seperti gambar (png, jpg), ikon, dan font yang digunakan di dalam aplikasi. File di sini di-*bundle* bersama aplikasi saat di-build.
- `node_modules/`
  Folder bawaan Node.js yang berisi semua *dependency* (library) pihak ketiga yang diinstal melalui npm/yarn.
- `src/`
  Merupakan folder utama yang berisi seluruh kode sumber (source code) aplikasi.

## Folder `src/`
- `api/`
  Berisi konfigurasi klien HTTP (seperti Axios) dan fungsi-fungsi untuk melakukan panggilan ke backend API. File di sini biasanya dikelompokkan berdasarkan fitur (contoh: `cart.js`, `products.js`, `auth.js`).
- `navigation/`
  Berisi pengaturan navigasi aplikasi menggunakan React Navigation. Di sini didefinisikan alur perpindahan antar layar, seperti *Stack Navigator* (tumpukan layar) dan *Tab Navigator* (menu bawah).
- `screens/`
  Berisi komponen-komponen UI (User Interface) utama yang mewakili halaman atau layar penuh di dalam aplikasi (contoh: halaman Home, Produk, Keranjang, Mining, dan Profil).
- `store/`
  Berisi logika manajemen *state* global menggunakan library seperti Zustand. Digunakan untuk menyimpan data yang perlu diakses dari berbagai halaman secara bersamaan (contoh: status login *user* dan isi keranjang belanja).
- `utils/`
  Berisi fungsi-fungsi pembantu (*helper functions*) dan utilitas kecil yang dapat digunakan berulang kali di seluruh aplikasi, seperti fungsi untuk memformat angka mata uang (Rupiah) atau tanggal.
