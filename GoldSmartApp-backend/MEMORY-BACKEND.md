# GoldSmartApp-backend - Struktur Folder

Dokumen ini menjelaskan struktur folder utama untuk sistem backend PHP dari GoldSmart.

## Root Directory
- `.well-known/`
  Folder standar yang biasanya digunakan untuk kebutuhan verifikasi domain, seperti *SSL/TLS certificate validation* atau konfigurasi *App Links/Universal Links* untuk aplikasi mobile.
- `admin/`
  Folder yang menampung antarmuka dan logika khusus untuk Admin Panel (seperti halaman HTML, CSS, dan skrip admin) yang digunakan untuk mengelola data operasional aplikasi.
- `api/`
  Folder yang berfungsi sebagai *entry point* (titik masuk) untuk setiap *request* (permintaan) API dari aplikasi mobile (contoh: memuat file `index.php` yang meneruskan rute API).
- `config/`
  Berisi file konfigurasi inti sistem, seperti pengaturan koneksi *database* (kredensial SQL), *timezone*, dan variabel-variabel lingkungan (*environment variables*).
- `controllers/`
  Berisi kelas-kelas pengendali (*Controllers*). Folder ini merupakan otak dari logika bisnis backend; bertugas menerima *request* dari pengguna, memanggil *Model* untuk mengolah data, dan mengembalikan respons (biasanya berupa JSON).
- `core/`
  Folder framework buatan internal. Berisi pustaka dasar atau *engine* aplikasi, seperti sistem *Routing*, kelas *Database*, dan pembungkus *Response* untuk menstandarisasi output.
- `database/`
  Biasanya berisi skrip SQL, file migrasi, atau rancangan skema database untuk membangun struktur tabel di server database.
- `logs/`
  Berisi file-file catatan (*log*) sistem. Folder ini mencatat *error* backend, riwayat aktivitas, atau rekaman *debugging* ketika aplikasi mengalami kendala.
- `models/`
  Berisi kelas-kelas *Models* yang merepresentasikan tabel di dalam database. Model bertugas melakukan interaksi langsung dengan database (Query SELECT, INSERT, UPDATE, DELETE).
- `routes/`
  Berisi definisi *Routing*. File di sini memetakan URL yang dipanggil oleh aplikasi mobile (misalnya `/api/login`) ke fungsi spesifik yang ada di dalam *Controllers*.
- `uploads/`
  Folder dinamis yang menyimpan berbagai file media yang diunggah oleh sistem atau pengguna (misalnya: gambar produk, foto profil, bukti transfer). Folder ini biasanya dieksklusi saat proses *update* agar data *production* tidak tertimpa/hilang.
