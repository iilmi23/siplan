# LAPORAN MAGANG

## Pengembangan Sistem Informasi Production Planning (SIPLAN) Berbasis Web untuk Otomatisasi Pengelolaan Shipping Release pada Departemen PPC PT Jatim Autocomp Indonesia

Disusun oleh:

**ZULHILMI LUTHFIAH**  
**23102030**

Program Studi S1 Informatika  
Fakultas Sains dan Teknologi  
Institut Teknologi, Sains, dan Kesehatan RS dr. Soepraoen Kesdam V/Brw  
2026

---

# LEMBAR PENGESAHAN

## LAPORAN MAGANG

**Pengembangan Sistem Informasi Production Planning (SIPLAN) Berbasis Web untuk Otomatisasi Pengelolaan Shipping Release pada Departemen PPC PT Jatim Autocomp Indonesia**

Disusun oleh:

**ZULHILMI LUTHFIAH**  
**23102030**

Laporan ini telah disetujui sebagai salah satu syarat penyelesaian kegiatan magang pada Program Studi S1 Informatika, Fakultas Sains dan Teknologi, Institut Teknologi, Sains, dan Kesehatan RS dr. Soepraoen Kesdam V/Brw.

Disetujui oleh:

| Pembimbing Lapangan | Pembimbing Utama |
| --- | --- |
| [Nama Pembimbing Lapangan] | [Nama Pembimbing Utama] |
| NIP/NIK: [Isi NIP/NIK] | NUPTK: [Isi NUPTK] |

Mengetahui,  
Kepala Program Studi S1 Informatika

[Nama Kepala Program Studi]  
NUPTK: [Isi NUPTK]

---

# KATA PENGANTAR

Puji syukur penulis panjatkan ke hadirat Tuhan Yang Maha Esa atas rahmat dan karunia-Nya sehingga penulis dapat menyelesaikan Laporan Magang yang berjudul "Pengembangan Sistem Informasi Production Planning (SIPLAN) Berbasis Web untuk Otomatisasi Pengelolaan Shipping Release pada Departemen PPC PT Jatim Autocomp Indonesia".

Laporan ini disusun sebagai salah satu syarat dalam menyelesaikan kegiatan magang pada Program Studi S1 Informatika, Fakultas Sains dan Teknologi, Institut Teknologi, Sains, dan Kesehatan RS dr. Soepraoen Kesdam V/Brw. Kegiatan magang dilaksanakan di PT Jatim Autocomp Indonesia, khususnya pada Departemen Production Planning and Control (PPC).

Selama pelaksanaan magang, penulis memperoleh banyak pengalaman dan pengetahuan baru, terutama mengenai proses bisnis pada bidang production planning, pengelolaan data Shipping Release, serta pengembangan aplikasi berbasis web yang dapat membantu proses kerja di perusahaan. Melalui kegiatan ini, penulis dapat menerapkan ilmu yang telah diperoleh selama perkuliahan, khususnya dalam bidang analisis sistem, perancangan basis data, pemrograman web, dan pengujian sistem.

Dalam proses pelaksanaan magang dan penyusunan laporan ini, penulis menerima banyak bantuan, arahan, serta dukungan dari berbagai pihak. Oleh karena itu, penulis mengucapkan terima kasih kepada:

1. Kedua orang tua yang telah memberikan dukungan moral, doa, dan materi selama pelaksanaan magang.
2. Muchlish Effendy, S.Pd., S.Kes., M.Si., selaku Dekan Fakultas Sains dan Teknologi Institut Teknologi, Sains, dan Kesehatan RS dr. Soepraoen Kesdam V/Brw Malang.
3. M. Syauqi Haris, S.Kom., M.Kom., selaku Kepala Program Studi S1 Informatika yang telah memberikan arahan dan dukungan dalam pelaksanaan kegiatan magang.
4. Risqy Siwi Pradini, S.S.T., M.Kom., selaku Dosen Pembimbing Magang yang telah memberikan bimbingan, masukan, dan arahan selama penyusunan laporan.
5. Avif Prima N., selaku Pembimbing Lapangan di PT Jatim Autocomp Indonesia yang telah memberikan kesempatan, arahan, serta bimbingan selama kegiatan magang.
6. Seluruh karyawan Departemen PPC PT Jatim Autocomp Indonesia yang telah membantu penulis dalam memahami proses kerja dan kebutuhan sistem.
7. Kak Rayhan yang telah membantu memberikan arahan teknis dan masukan selama proses pengembangan aplikasi.
8. Teman-teman dari PENS yang telah memberikan dukungan dan kerja sama selama pelaksanaan magang.

Penulis menyadari bahwa laporan ini masih memiliki kekurangan. Oleh karena itu, penulis mengharapkan kritik dan saran yang membangun agar laporan ini dapat menjadi lebih baik. Semoga laporan ini dapat memberikan manfaat bagi pembaca, perguruan tinggi, serta pihak PT Jatim Autocomp Indonesia.

Pasuruan, [Tanggal Penyusunan]

Penulis

Zulhilmi Luthfiah

---

# ABSTRAK

**ZULHILMI LUTHFIAH. 23102030. Pengembangan Sistem Informasi Production Planning (SIPLAN) Berbasis Web untuk Otomatisasi Pengelolaan Shipping Release pada Departemen PPC PT Jatim Autocomp Indonesia.** Di bawah bimbingan [Nama Pembimbing Utama] sebagai Pembimbing Utama dan [Nama Pembimbing Lapangan] sebagai Pembimbing Lapangan.

Departemen Production Planning and Control (PPC) PT Jatim Autocomp Indonesia memiliki kebutuhan untuk mengelola data Shipping Release sebagai dasar dalam proses perencanaan produksi. Sebelumnya, proses pengelolaan data masih dilakukan menggunakan beberapa file Excel yang saling terhubung melalui link dan formula antarfile. Kondisi tersebut menimbulkan beberapa kendala, seperti risiko link antarfile terputus, perbedaan format file antar customer, kesalahan pembacaan quantity dan tanggal, duplikasi data, serta kesulitan dalam menyusun Summary dan Six-Month Production Plan.

Kegiatan magang ini bertujuan untuk mengembangkan Sistem Informasi Production Planning (SIPLAN) berbasis web yang dapat membantu pengelolaan Shipping Release secara lebih terpusat dan terstruktur. Sistem dikembangkan menggunakan Laravel sebagai backend, React/Inertia sebagai frontend, dan PostgreSQL sebagai basis data. Fitur yang dikembangkan meliputi autentikasi, dashboard, master customer, master port, master carline, master assy, master production week, upload Shipping Release, mapping data customer, Summary, export Excel, Six-Month Production Plan, variance order, dan manajemen user.

Hasil dari kegiatan magang ini adalah aplikasi SIPLAN yang dapat membantu pengguna dalam melakukan import data, melihat preview data, melakukan mapping, menyimpan data ke database, melihat ringkasan data, melakukan export Excel, serta membandingkan perubahan order antar periode. Dengan adanya aplikasi ini, proses pengelolaan Shipping Release menjadi lebih terstruktur, mudah ditelusuri, dan dapat mengurangi risiko kesalahan manual.

**Kata kunci:** Sistem Informasi, Production Planning, Shipping Release, Laravel, PostgreSQL, SIPLAN.

---

# DAFTAR ISI

LEMBAR PENGESAHAN  
KATA PENGANTAR  
ABSTRAK  
DAFTAR ISI  
DAFTAR TABEL  
DAFTAR GAMBAR  
DAFTAR LAMPIRAN  
BAB I PENDAHULUAN  
BAB II PROFIL TEMPAT MAGANG  
BAB III TINJAUAN PUSTAKA  
BAB IV HASIL PELAKSANAAN MAGANG  
BAB V KESIMPULAN DAN SARAN  
DAFTAR PUSTAKA  
LAMPIRAN

---

# DAFTAR TABEL

Tabel 4.1 Timeline Kegiatan Magang  
Tabel 4.2 Fitur Aplikasi SIPLAN  
Tabel 4.3 Kendala dan Solusi Pengembangan Sistem

---

# DAFTAR GAMBAR

Gambar 2.1 Struktur Organisasi PT Jatim Autocomp Indonesia  
Gambar 4.1 Use Case Diagram SIPLAN  
Gambar 4.2 Activity Diagram Upload Shipping Release  
Gambar 4.3 Rancangan Database SIPLAN  
Gambar 4.4 Tampilan Login SIPLAN  
Gambar 4.5 Tampilan Dashboard SIPLAN  
Gambar 4.6 Tampilan Master Data  
Gambar 4.7 Tampilan Upload Shipping Release  
Gambar 4.8 Tampilan Summary  
Gambar 4.9 Tampilan Export Excel  
Gambar 4.10 Tampilan Variance Order

---

# DAFTAR LAMPIRAN

Lampiran 1. Surat Pengantar Magang  
Lampiran 2. Surat Balasan atau Penerimaan Magang  
Lampiran 3. Logbook Kegiatan Magang  
Lampiran 4. Dokumentasi Kegiatan Magang  
Lampiran 5. Dokumentasi Tampilan Aplikasi SIPLAN  
Lampiran 6. Source Code atau Dokumentasi Teknis Sistem

---

# BAB I PENDAHULUAN

## 1.1 Latar Belakang

Program magang mandiri merupakan bagian penting dari proses pembelajaran mahasiswa Program Studi S1 Informatika karena memberikan kesempatan untuk menerapkan kompetensi yang telah dipelajari selama perkuliahan dalam situasi kerja yang sesungguhnya. Berbeda dengan lingkungan akademik yang bersifat terstruktur dan terkendali, dunia kerja menghadirkan permasalahan yang lebih kompleks dan dinamis, serta menuntut solusi yang dirancang sesuai dengan kebutuhan dan kondisi operasional yang sesungguhnya. Melalui kegiatan magang, mahasiswa tidak hanya menguji kemampuan teknisnya, tetapi juga mengembangkan kemampuan komunikasi, pemecahan masalah, dan adaptasi terhadap kebutuhan yang terus berkembang di lingkungan industri.

PT Jatim Autocomp Indonesia merupakan sebuah perusahaan manufaktur *wiring harness* yang berlokasi di Pasuruan, Jawa Timur. Dalam pelaksanaan magang yang berlangsung pada periode 9 Maret hingga 9 Juli 2026, penulis ditempatkan di Departemen *Production Planning and Control* (PPC), yang bertanggung jawab atas perencanaan dan pengendalian produksi. Salah satu tugas utama departemen ini adalah mengelola data *Shipping Release* (SR), yaitu dokumen resmi dari pelanggan yang memuat rencana permintaan dan menjadi dasar penyusunan jadwal produksi serta rencana pengiriman. Selama penempatan inilah penulis menemukan permasalahan nyata dalam pengelolaan data SR tersebut.

Berdasarkan hasil observasi selama magang, proses pengelolaan SR di Departemen PPC masih sepenuhnya dilakukan menggunakan beberapa file Microsoft Excel yang saling terhubung melalui formula dan *link* antarfile. Setiap kali pelanggan menerbitkan file SR baru dengan format yang berbeda-beda, staf PPC harus memprosesnya secara manual satu per satu. Kondisi tersebut menimbulkan berbagai kendala, antara lain proses pengolahan data yang memakan waktu, risiko *human error* akibat penyalinan data secara manual, kesulitan dalam *monitoring* status *order*, sulitnya menelusuri histori perubahan data, serta ketidaksesuaian data antarfile apabila *link* antarfile terputus karena perubahan lokasi atau nama file di server.

Permasalahan-permasalahan tersebut tidak hanya berdampak pada proses input data, tetapi juga pada tahapan perencanaan produksi yang bergantung pada data SR sebagai sumber utamanya. Ketika data SR tidak akurat atau terlambat diperbarui, penyusunan *Summary*, *Six-Month Production Plan* (SPP), dan analisis perubahan *order* antarperiode (*Variance Order*) menjadi tidak dapat diandalkan, sehingga berpotensi mengganggu kelancaran jadwal produksi dan pengiriman kepada pelanggan. Kondisi ini mendorong kebutuhan akan sebuah sistem informasi yang mampu mengelola data SR secara lebih terpusat, terstruktur, dan mudah ditelusuri.

Sebagai solusi terhadap permasalahan tersebut, penulis merancang dan mengembangkan Sistem Informasi *Production Planning* yang selanjutnya disebut SIPLAN, yaitu sebuah aplikasi berbasis web untuk mendukung pengelolaan data *Shipping Release* secara digital dan terintegrasi. Sistem ini dibangun menggunakan Laravel sebagai *framework backend*, React dengan Inertia.js sebagai antarmuka pengguna (*frontend*), TypeScript untuk konsistensi tipe data, serta PostgreSQL sebagai basis data (*database*). Fitur utama yang tersedia meliputi unggah dan pemetaan file Excel SR dari berbagai format pelanggan, *Summary* data *order*, *Six-Month Production Plan* (SPP), *Variance Order*, ekspor data ke Excel, *History Log*, serta manajemen pengguna dan hak akses. Dengan adanya sistem ini, proses pengelolaan data SR di Departemen PPC PT Jatim Autocomp Indonesia diharapkan dapat dilakukan secara lebih efisien, akurat, dan terpusat, sehingga turut mendukung keandalan data sebagai dasar perencanaan produksi. Oleh karena itu, laporan magang ini disusun dengan judul "Rancang Bangun Sistem Informasi Production Planning (SIPLAN) Berbasis Web untuk Pengelolaan Shipping Release pada PT Jatim Autocomp Indonesia".

## 1.2 Rumusan Masalah

Berdasarkan latar belakang tersebut, rumusan masalah dalam kegiatan magang ini adalah sebagai berikut:

1. Bagaimana proses pengelolaan *Shipping Release* pada Departemen PPC PT Jatim Autocomp Indonesia sebelum adanya sistem berbasis web?
2. Apa saja kendala yang terjadi dalam pengelolaan *Shipping Release* menggunakan file Excel yang saling terhubung?
3. Bagaimana merancang dan mengembangkan aplikasi SIPLAN berbasis web untuk membantu pengelolaan *Shipping Release*?
4. Bagaimana aplikasi SIPLAN dapat membantu proses *Summary*, *Six-Month Production Plan* (SPP), *export* Excel, dan analisis *Variance Order*?

## 1.3 Tujuan

Berdasarkan rumusan masalah yang telah diuraikan, tujuan dari pelaksanaan magang mandiri ini dibagi menjadi tujuan umum dan tujuan khusus sebagai berikut:

### 1.3.1 Tujuan Umum
1. Mengaplikasikan kompetensi dan ilmu pemrograman serta rekayasa perangkat lunak yang telah dipelajari selama perkuliahan S1 Informatika ke dalam praktik nyata di dunia industri.
2. Meningkatkan pemahaman mengenai alur kerja, proses bisnis, dan kebutuhan teknologi informasi di Departemen *Production Planning and Control* (PPC) PT Jatim Autocomp Indonesia.
3. Memperoleh pengalaman kerja nyata di industri manufaktur guna meningkatkan daya sang dan kesiapan di dunia profesional.
4. Mengembangkan keterampilan non-teknis (*soft skills*) seperti komunikasi profesional dengan pengguna sistem (*user*), manajemen waktu, dan adaptasi terhadap budaya kerja industri.
5. Membangun relasi profesional dengan praktisi di PT Jatim Autocomp Indonesia sebagai langkah awal dalam merintis karier di bidang teknologi informasi.

### 1.3.2 Tujuan Khusus
1. Mengidentifikasi kendala operasional dalam pengelolaan data *Shipping Release* (SR) di Departemen PPC PT Jatim Autocomp Indonesia yang masih menggunakan Microsoft Excel secara manual.
2. Merancang dan membangun aplikasi Sistem Informasi *Production Planning* (SIPLAN) berbasis web menggunakan *framework* Laravel, React dengan Inertia.js, dan PostgreSQL.
3. Menyediakan fitur pemetaan (*mapping*) data otomatis untuk mengatasi variasi format file Excel *Shipping Release* dari berbagai pelanggan.
4. Memfasilitasi penyusunan laporan pendukung produksi seperti *Summary* order, *Six-Month Production Plan* (SPP), dan analisis perubahan order (*Variance Order*) secara digital dan terintegrasi.

## 1.4 Manfaat

Kegiatan magang ini diharapkan dapat memberikan manfaat bagi mahasiswa, tempat magang, dan perguruan tinggi.

### 1.4.1 Manfaat bagi Mahasiswa

1. Memperoleh pengalaman kerja langsung yang relevan dengan bidang Informatika.
2. Meningkatkan kemampuan dalam menganalisis kebutuhan pengguna dan menerapkannya ke dalam sistem informasi.
3. Mengasah keterampilan pemrograman web menggunakan *framework* Laravel, React dengan Inertia.js, dan PostgreSQL.
4. Meningkatkan kemampuan pemecahan masalah dalam konteks industri.
5. Menambah portofolio nyata yang dapat digunakan sebagai bekal di dunia profesional.

### 1.4.2 Manfaat bagi Tempat Magang

1. Memperoleh aplikasi Sistem Informasi *Production Planning* (SIPLAN) yang dapat membantu pengelolaan data *Shipping Release* (SR).
2. Mengurangi ketergantungan pada file Excel yang saling terhubung dan rawan terjadi kesalahan.
3. Mempermudah proses *upload*, *mapping*, pengolahan, dan *export* data *Shipping Release*.
4. Mendukung penyusunan *Summary*, *Six-Month Production Plan* (SPP), dan analisis *Variance Order* secara lebih terstruktur.
5. Mendapatkan masukan atau solusi teknologi atas permasalahan operasional yang selama ini dihadapi.

### 1.4.3 Manfaat bagi Perguruan Tinggi

1. Meningkatkan hubungan kerja sama antara perguruan tinggi dan dunia industri.
2. Memberikan gambaran kebutuhan kompetensi yang dibutuhkan di dunia kerja.
3. Menjadi bahan evaluasi untuk pengembangan kurikulum agar tetap relevan dengan kebutuhan industri.
4. Meningkatkan reputasi perguruan tinggi melalui keterlibatan mahasiswa dalam penyelesaian masalah nyata di perusahaan.

## 1.5 Ruang Lingkup Kegiatan

Ruang lingkup kegiatan magang ini adalah sebagai berikut:

1. Kegiatan magang dilaksanakan selama empat bulan, mulai tanggal 9 Maret 2026 sampai 9 Juli 2026.
2. Lokasi kegiatan magang adalah PT Jatim Autocomp Indonesia yang berlokasi di Jl. Raya Wonoayu No. 26, Belakang Gempol, Pasuruan.
3. Kegiatan magang difokuskan pada pengembangan aplikasi SIPLAN berbasis web untuk membantu pengelolaan *Shipping Release* pada Departemen PPC.
4. Fitur yang dikembangkan meliputi autentikasi, *dashboard*, master *customer*, master *port*, master *carline*, master *assy*, master *production week*, *upload* *Shipping Release*, *mapping* data, *Summary*, *export* Excel, *Six-Month Production Plan* (SPP), *Variance Order*, dan manajemen *user*.
5. Sistem dikembangkan menggunakan *framework* Laravel, React dengan Inertia.js, dan PostgreSQL.
6. Pengujian dilakukan secara fungsional berdasarkan skenario penggunaan aplikasi oleh pengguna.
7. *Output* yang diharapkan dari kegiatan magang ini adalah aplikasi SIPLAN, dokumentasi kebutuhan sistem, dokumentasi pengujian, serta laporan akhir magang.

---

# BAB II PROFIL TEMPAT MAGANG

## 2.1 Sejarah Singkat Tempat Magang

PT Jatim Autocomp Indonesia merupakan perusahaan otomotif yang berasal dari Penanaman Modal Asing (PMA) asal Jepang. Perusahaan ini berdiri pada 13 Mei 2002 dan berlokasi di Jl. Raya Wonoayu No. 26, Belakang Gempol, Pasuruan. PT Jatim Autocomp Indonesia berfokus pada kegiatan produksi dan perakitan instalasi kabel kendaraan atau *wiring harness*.

PT Jatim Autocomp Indonesia merupakan bagian dari Yazaki Group, yaitu perusahaan multinasional yang bergerak di bidang komponen otomotif, khususnya sistem kelistrikan kendaraan. Yazaki Group memiliki beberapa anak perusahaan di Indonesia, yaitu:
1. PT Autocomp System Indonesia (PASI) yang berlokasi di DKI Jakarta.
2. PT EDS Manufacturing Indonesia (PEMI) yang berlokasi di Balaraja, Tangerang.
3. PT Surabaya Autocomp Indonesia (SAI) yang berlokasi di Ngoro, Mojokerto.
4. PT Semarang Autocomp Manufacturing Indonesia (SAMI) yang berlokasi di Tugu, Semarang.
5. PT Jatim Autocomp Indonesia (JAI) yang berlokasi di Gempol, Pasuruan.
6. PT Subang Autocomp Indonesia (SUAI) yang berlokasi di Subang, Jawa Barat.

## 2.2 Struktur Organisasi

Setiap perusahaan memerlukan struktur organisasi yang jelas agar pembagian tugas, tanggung jawab, dan wewenang setiap bagian dapat berjalan dengan baik. Struktur organisasi juga membantu proses koordinasi antarbagian sehingga kegiatan operasional perusahaan dapat dilakukan secara efektif dan dapat dipertanggungjawabkan.

PT Jatim Autocomp Indonesia memiliki struktur organisasi yang disusun berdasarkan fungsi dan tanggung jawab masing-masing bagian. Struktur tersebut terdiri atas pimpinan perusahaan, *general manager*, *manager*, *supervisor*, hingga staf pada masing-masing departemen. Salah satu departemen yang memiliki peran penting dalam kegiatan operasional perusahaan adalah Departemen *Production Planning and Control* (PPC), yaitu departemen yang bertanggung jawab dalam perencanaan dan pengendalian produksi. Di dalam Departemen PPC sendiri, pembagian kerja dibagi berdasarkan fungsi *production scheduler* (penjadwalan produksi), *material control* (pengendalian bahan baku), dan pengelolaan data permintaan pelanggan (*customer order/shipping release*).

[Masukkan Gambar 2.1 Struktur Organisasi PT Jatim Autocomp Indonesia]

Gambar 2.1 Struktur Organisasi PT Jatim Autocomp Indonesia

Berdasarkan struktur organisasi tersebut, Departemen PPC memiliki keterkaitan erat dengan beberapa bagian lain, seperti bagian produksi, *warehouse* (gudang), *purchasing* (pembelian), dan *customer service* (pelayanan pelanggan). Hubungan tersebut diperlukan karena proses perencanaan produksi membutuhkan data permintaan *customer* secara aktual, ketersediaan material, kapasitas lini produksi, serta jadwal pengiriman barang yang tepat waktu.

## 2.3 Deskripsi Layanan atau Produk

PT Jatim Autocomp Indonesia bergerak di bidang perakitan instalasi kabel kendaraan atau *wiring harness*. *Wiring harness* merupakan sekumpulan kabel yang dirangkai menjadi satu kesatuan dengan berbagai material pendukung, seperti *tube*, *connector*, *terminal*, *clamp*, dan *rubber seal*. Rangkaian tersebut kemudian dipasang pada kendaraan bermotor, khususnya mobil merek Nissan, Toyota, Daihatsu, dan Mazda.

*Wiring harness* memiliki fungsi vital dalam sistem kelistrikan kendaraan. Komponen ini berperan sebagai jalur transmisi arus listrik dan sinyal kendali dari satu komponen elektronik ke komponen lainnya. Oleh karena itu, *wiring harness* sering dianggap sebagai sistem saraf pada kendaraan karena menghubungkan berbagai perangkat elektronik agar dapat bekerja sesuai fungsinya. Kualitas *wiring harness* sangat krusial karena berkaitan langsung dengan keamanan, kenyamanan, dan fungsi dasar kendaraan. Dalam proses produksinya, perusahaan harus memperhatikan ketepatan spesifikasi, kualitas perakitan material, dan kesesuaian jadwal produksi agar kebutuhan pelanggan dapat terpenuhi dengan baik.

## 2.4 Peran Mahasiswa di Tempat Magang

Selama menjalani kegiatan magang mandiri di Departemen *Production Planning and Control* (PPC) PT Jatim Autocomp Indonesia di bawah bimbingan pembimbing lapangan Bapak Avif Prima N., penulis diberikan tanggung jawab untuk mengembangkan sistem aplikasi berbasis web yang bertujuan mendukung operasional pengelolaan data permintaan pelanggan (*customer order*) di lingkungan perusahaan.

Sistem aplikasi berbasis web ini adalah Sistem Informasi *Production Planning* (SIPLAN), sebuah aplikasi yang digunakan untuk mengelola dan memetakan data *Shipping Release* (SR) dari berbagai pelanggan secara terpusat dan terintegrasi. Melalui aplikasi ini, pengguna dapat mengunggah file Excel SR, memetakan format kolom secara dinamis, melihat *Summary* kebutuhan produksi, menyusun *Six-Month Production Plan* (SPP), serta menganalisis perbedaan order antarperiode (*Variance Order*). Hal ini membantu dalam mempercepat penyusunan rencana produksi agar lebih efektif, akurat, dan efisien.

Dalam proses pengembangan sistem tersebut, penulis terlibat aktif di seluruh tahapan mulai dari analisis kebutuhan, desain antarmuka pengguna (UI/UX), pengembangan *frontend* dan *backend*, hingga pengujian sistem secara fungsional. *Framework* Laravel digunakan untuk membangun *backend* aplikasi, sementara React dengan Inertia.js diaplikasikan untuk *frontend* antarmuka pengguna, serta PostgreSQL sebagai sistem manajemen basis data. Selain aspek teknis, penulis juga melakukan komunikasi dan kolaborasi intensif dengan staf internal Departemen PPC PT Jatim Autocomp Indonesia guna mendapatkan masukan dan memastikan aplikasi yang dikembangkan sesuai dengan kebutuhan operasional departemen.

---

# BAB III TINJAUAN PUSTAKA

## 3.1 Sistem Informasi

Sistem informasi adalah sekumpulan komponen yang saling berhubungan untuk mengumpulkan, memproses, menyimpan, dan mendistribusikan informasi guna mendukung pengambilan keputusan dan kendali dalam suatu organisasi [1]. Sistem informasi terdiri dari beberapa komponen utama, yaitu masukan (*input*), proses (*process*), keluaran (*output*), basis data (*database*), teknologi, dan pengguna (*user*). Dalam lingkungan industri, penerapan sistem informasi terintegrasi sangat krusial untuk mempercepat pertukaran data dan meminimalisasi duplikasi informasi operasional.

Dalam pelaksanaan magang di PT Jatim Autocomp Indonesia, konsep sistem informasi ini diterapkan secara penuh pada pengembangan aplikasi SIPLAN. Komponen masukan berupa berkas Excel *Shipping Release* (SR) dari pelanggan, komponen proses meliputi pengunggahan (*upload*), pemetaan (*mapping*), dan kalkulasi *week*, sedangkan komponen keluaran menyajikan visualisasi data *Summary*, *Six-Month Production Plan* (SPP), analisis *Variance Order*, serta ekspor kembali ke berkas Excel.

## 3.2 Production Planning and Control (PPC)

*Production Planning and Control* (PPC) merupakan suatu departemen atau fungsi dalam perusahaan manufaktur yang bertanggung jawab atas perencanaan, pengaturan, dan pengendalian seluruh siklus proses produksi agar berjalan efisien sesuai kapasitas pabrik dan kebutuhan pelanggan [2]. Fungsi utama PPC mencakup peramalan permintaan, perencanaan kapasitas produksi (*capacity planning*), penjadwalan produksi (*scheduling*), pemantauan persediaan bahan baku (*inventory control*), dan koordinasi pengiriman produk akhir.

Pada PT Jatim Autocomp Indonesia, Departemen PPC memerlukan akurasi data yang sangat tinggi karena produk yang dihasilkan berupa *wiring harness* otomotif dengan ribuan variasi komponen (*assy number*). Sistem SIPLAN dirancang untuk mendukung efisiensi kerja staf PPC dalam mengolah data permintaan riil sehingga keputusan penentuan jadwal produksi (*production schedule*) dan alokasi material dapat dilakukan secara cepat dan andal.

## 3.3 Shipping Release

*Shipping Release* (SR) adalah dokumen resmi berisi data permintaan atau pesanan dari pelanggan (*customer order*) yang memuat informasi mengenai jenis produk (*part number*), jumlah (*quantity*), tanggal pengiriman (*delivery date*), tujuan (*destination*), serta pekan produksi (*production week*) [3]. Dokumen ini menjadi basis utama bagi departemen PPC untuk memulai siklus perencanaan manufaktur dan koordinasi logistik pengiriman produk.

Pada Departemen PPC PT Jatim Autocomp Indonesia, data SR dikirimkan secara berkala oleh berbagai pelanggan dengan format berkas yang bervariasi. Melalui SIPLAN, data mentah SR dari pelanggan diproses secara digital guna menghasilkan kalkulasi *Summary* mingguan dan bulanan secara terpusat, menggantikan proses pengolahan manual berbasis Excel yang rentan mengalami kesalahan fatal.

## 3.4 Aplikasi Berbasis Web

Aplikasi berbasis web adalah jenis perangkat lunak yang diakses oleh pengguna melalui media peramban (*web browser*) dengan koneksi jaringan internet atau intranet, tanpa memerlukan proses instalasi aplikasi pada komputer klien secara individual [4]. Arsitektur ini memudahkan proses pemeliharaan sistem (*maintenance*) karena seluruh basis kode program dan data diperbarui secara terpusat pada sisi server (*server-side*).

Pendekatan aplikasi berbasis web ini diimplementasikan pada SIPLAN agar sistem dapat diakses secara simultan oleh beberapa staf Departemen PPC PT Jatim Autocomp Indonesia melalui komputer kerja masing-masing tanpa memerlukan spesifikasi perangkat keras khusus, serta menjamin kolaborasi data secara *real-time*.

## 3.5 Laravel dan Konsep MVC

Laravel merupakan salah satu *framework* pengembangan web berbasis PHP yang menggunakan arsitektur *Model-View-Controller* (MVC) untuk memisahkan logika bisnis, pengelolaan basis data, dan antarmuka pengguna [5]. Laravel menyediakan berbagai pustaka pembantu seperti *routing*, *controller*, migrasi basis data (*migration*), validasi data, sistem autentikasi, serta *Object-Relational Mapping* (ORM) Eloquent untuk interaksi data yang aman.

Dalam pengembangan backend SIPLAN, Laravel digunakan untuk menangani seluruh logika bisnis utama. Konsep MVC diterapkan dengan memisahkan *Model* sebagai representasi tabel basis data PostgreSQL, *Controller* untuk memproses request pengguna (seperti logika penguraian Excel dan kalkulasi *variance*), dan komponen antarmuka yang dijembatani menuju React.

## 3.6 React dan Inertia.js

React adalah *library* JavaScript berbasis komponen yang digunakan untuk membangun antarmuka pengguna yang dinamis dan reaktif [6]. Sementara itu, Inertia.js bertindak sebagai adaptor atau penghubung yang menghubungkan Laravel di sisi *backend* secara langsung dengan React di sisi *frontend* tanpa memerlukan pembuatan arsitektur REST API yang terpisah (*modern monolith*).

Pada sistem SIPLAN, React digunakan untuk merancang antarmuka pengguna yang interaktif dan responsif (seperti menu pratinjau data dan pemetaan kolom dinamis). Inertia.js digunakan untuk melewatkan data dari *controller* Laravel langsung ke komponen React sebagai *props*, sehingga menghasilkan pengalaman aplikasi halaman tunggal (*Single Page Application* - SPA) yang cepat bagi pengguna.

## 3.7 PostgreSQL

PostgreSQL merupakan sistem manajemen basis data relasional (*Relational Database Management System* - RDBMS) sumber terbuka yang terkenal akan keandalan, integritas data, serta kemampuannya dalam mengeksekusi *query* kompleks pada volume data yang besar [7]. PostgreSQL mendukung fungsionalitas transaksi ACID secara penuh untuk menjamin keamanan operasi tulis dan baca.

SIPLAN memanfaatkan basis data PostgreSQL untuk mengelola seluruh data relasional sistem, meliputi data tabel *users*, *customers*, *ports*, *carlines*, *assies*, *production_weeks*, *shipping_releases*, *summaries*, hingga catatan aktivitas (*history logs*). Penggunaan database ini memastikan konsistensi relasi antar data, misalnya keterkaitan nomor *assy* dengan jenis kendaraan (*carline*) pelanggan.

## 3.8 Pengolahan File Excel

Proses pengolahan file Excel secara otomatis merupakan metode membaca, mengurai (*parsing*), memvalidasi, dan mengekstraksi data dari lembar kerja (*spreadsheet*) secara terprogram untuk mempercepat input data masal ke dalam basis data [8]. Hal ini bertujuan untuk meminimalisasi kesalahan manusia (*human error*) akibat aktivitas salin-tempel data secara manual.

Fitur utama SIPLAN adalah memproses pengunggahan berkas Excel *Shipping Release* milik pelanggan. Melalui pustaka *PhpSpreadsheet* di sisi *backend*, sistem melakukan pemindaian lembar kerja (*sheet*), menyajikan pratinjau data, memetakan indeks kolom secara dinamis sesuai templat pelanggan, memvalidasi tipe data, dan menyimpannya secara otomatis ke dalam basis data.

## 3.9 Pengujian Sistem

Pengujian sistem merupakan proses evaluasi fungsionalitas perangkat lunak untuk memastikan bahwa seluruh fitur berjalan sesuai dengan dokumen kebutuhan pengguna [9]. Salah satu metode pengujian yang umum digunakan adalah pengujian kotak hitam (*Black Box Testing*), yang berfokus pada pengujian masukan dan keluaran sistem tanpa perlu memeriksa struktur kode program internal.

Pengujian pada aplikasi SIPLAN dilakukan secara fungsional menggunakan metode *Black Box Testing*. Skenario pengujian mencakup verifikasi proses autentikasi login, operasi CRUD pada modul master data, fungsionalitas pengunggahan dan pemetaan Excel *Shipping Release*, presisi kalkulasi laporan *Summary*, SPP, serta keandalan visualisasi modul *Variance Order*. Pengujian ini memastikan sistem bebas dari galat operasional sebelum diserahkan kepada staf PPC PT Jatim Autocomp Indonesia.

---

# BAB IV HASIL PELAKSANAAN MAGANG

## 4.1 Deskripsi Aktivitas

Kegiatan magang dilaksanakan di Departemen Production Planning and Control (PPC) PT Jatim Autocomp Indonesia. Fokus utama kegiatan adalah pengembangan Sistem Informasi Production Planning (SIPLAN) berbasis web untuk membantu pengelolaan data Shipping Release. Pengembangan dilakukan secara bertahap, dimulai dari pemahaman proses bisnis, analisis kebutuhan, perancangan sistem, implementasi fitur, pengujian, hingga perbaikan berdasarkan masukan pengguna.

Pada tahap awal, penulis melakukan observasi dan diskusi dengan staff PPC untuk memahami alur kerja Shipping Release sampai Six-Month Production Plan. Dari hasil diskusi tersebut, penulis mengetahui bahwa data Shipping Release menjadi salah satu dasar penting dalam perencanaan produksi. Data tersebut perlu dibaca, diolah, diringkas, dan disesuaikan dengan kebutuhan customer.

Permasalahan utama yang ditemukan adalah proses pengelolaan data masih menggunakan beberapa file Excel yang saling terhubung melalui link dan formula antarfile. Kondisi tersebut membuat proses kerja menjadi bergantung pada struktur file tertentu. Apabila salah satu file berubah, berpindah lokasi, atau link antarfile terputus, maka hasil pengolahan data dapat terganggu. Selain itu, format file dari setiap customer tidak selalu sama sehingga proses pembacaan data membutuhkan ketelitian tinggi.

Setelah memahami kebutuhan awal, penulis menyusun rancangan sistem dalam bentuk analisis kebutuhan, use case, activity diagram, dan rancangan database. Database dibuat menggunakan PostgreSQL dengan beberapa tabel utama, seperti users, customers, ports, shipping releases, production weeks, carlines, assies, summaries, dan tabel pendukung lainnya. Rancangan database tersebut digunakan sebagai dasar untuk membangun fitur-fitur utama SIPLAN.

Tahap implementasi dimulai dari pembuatan fitur login, dashboard, sidebar, dan topbar sebagai dasar aplikasi. Setelah itu, penulis mengembangkan fitur master data seperti customer, port, carline, assy, dan production week. Fitur master data dibuat dengan fungsi tambah, lihat, ubah, dan hapus data agar pengguna dapat mengelola data pendukung production planning melalui aplikasi.

Aktivitas berikutnya adalah pengembangan fitur Upload Shipping Release. Pada fitur ini, pengguna dapat mengunggah file Excel Shipping Release, memilih sheet, melihat preview data, melakukan mapping, dan menyimpan data ke database. Pengembangan fitur ini menjadi salah satu bagian yang paling menantang karena format Excel setiap customer tidak selalu sama. Beberapa customer yang dikerjakan dalam proses mapping antara lain TYC, YNA, SAI, dan YC.

Selama proses pengembangan Upload Shipping Release, penulis beberapa kali menemukan kendala pada pembacaan data Excel. Kendala yang muncul antara lain data quantity belum terbaca dengan benar, tanggal tidak sesuai, hasil preview berantakan, dan beberapa data tersimpan sebagai null. Untuk mengatasi hal tersebut, penulis melakukan pengecekan ulang terhadap posisi header, struktur kolom, format tanggal, serta logika parsing sebelum data disimpan ke database.

Setelah data Shipping Release dapat diproses, penulis mengembangkan fitur Summary. Fitur ini digunakan untuk menampilkan hasil pengolahan data Shipping Release dalam bentuk yang lebih mudah dibaca oleh pengguna. Pada fitur Summary, penulis juga membuat export Excel sesuai kebutuhan pengguna. Berdasarkan hasil diskusi dengan user, terdapat beberapa revisi seperti perubahan format export dari kolom menjadi baris, penyesuaian week sampai lima week, penambahan total bulanan, serta penyesuaian format khusus untuk customer tertentu.

Selain Summary, penulis juga mengembangkan fitur Master Week karena perhitungan week menjadi salah satu bagian penting dalam pengolahan data Shipping Release dan SPP. Pada fitur ini, penulis membuat CRUD data week, upload Excel Master Week, serta preview struktur week. Kendala yang ditemukan adalah perhitungan week yang belum sesuai dengan tanggal, sehingga dilakukan perbaikan pada logika pembacaan tanggal dan penyesuaian data week yang digunakan sistem.

Penulis juga mengembangkan fitur Master Carline dan Master Assy. Kedua fitur ini digunakan sebagai data pendukung dalam proses pengelolaan order. Selain CRUD, penulis menambahkan fitur upload Excel pada Master Carline dan Master Assy agar pengguna dapat memasukkan data dalam jumlah besar. Proses upload mencakup download template, upload file, pemilihan sheet, preview data, validasi, dan penyimpanan ke database.

Pada tahap berikutnya, penulis mengembangkan fitur Six-Month Production Plan dan Variance Order. Fitur Six-Month Production Plan digunakan untuk mendukung penyusunan rencana produksi berdasarkan data yang telah diolah. Sementara itu, fitur Variance Order digunakan untuk membandingkan order customer antara periode saat ini dan periode sebelumnya, sehingga pengguna dapat melihat perubahan quantity yang naik atau turun.

Selain fitur utama, penulis juga mengembangkan fitur manajemen user dan pengaturan hak akses. Fitur ini dibuat agar penggunaan aplikasi dapat dibatasi sesuai peran pengguna. Penulis juga menambahkan mode dark dan light untuk meningkatkan kenyamanan tampilan aplikasi.

Secara umum, aktivitas magang tidak hanya berfokus pada pembuatan fitur, tetapi juga pada proses memahami kebutuhan pengguna, menerima revisi, melakukan debugging, dan menyesuaikan sistem dengan kondisi data nyata. Dengan demikian, hasil pekerjaan selama magang dapat dipertanggungjawabkan karena sesuai dengan kebutuhan Departemen PPC dan fitur yang dikembangkan pada aplikasi SIPLAN.

## 4.2 Analisis Sistem Berjalan

Sebelum adanya SIPLAN, proses pengelolaan Shipping Release dilakukan menggunakan Microsoft Excel. Data dari customer diterima dalam bentuk file Excel dengan format yang berbeda-beda. Setelah itu, data diolah kembali oleh staff PPC menggunakan file kerja internal yang saling terhubung dengan link dan formula antarfile.

Alur sistem berjalan secara umum adalah sebagai berikut:

1. Staff PPC menerima file Shipping Release dari customer.
2. Staff membuka file Excel dan memeriksa isi data secara manual.
3. Data yang diperlukan dipindahkan atau dihubungkan ke file Excel lain.
4. Formula dan link antarfile digunakan untuk menyusun Summary dan data pendukung production planning.
5. Hasil pengolahan digunakan untuk menyusun Six-Month Production Plan dan kebutuhan analisis lain.

Proses tersebut memiliki beberapa kelemahan. Pertama, format file dari setiap customer tidak selalu sama sehingga staff harus menyesuaikan cara pembacaan data. Kedua, penggunaan banyak file Excel yang saling terhubung menyebabkan risiko link antarfile terputus. Ketiga, proses manual dapat menimbulkan kesalahan input, kesalahan membaca tanggal, quantity, dan week. Keempat, penelusuran perubahan order menjadi lebih sulit karena data tersebar pada beberapa file.

## 4.3 Analisis Kebutuhan Sistem

Berdasarkan hasil observasi dan diskusi dengan pengguna, kebutuhan sistem dibagi menjadi kebutuhan fungsional dan kebutuhan nonfungsional.

### 4.3.1 Kebutuhan Fungsional

Kebutuhan fungsional aplikasi SIPLAN adalah sebagai berikut:

1. Sistem dapat digunakan oleh pengguna melalui proses login.
2. Sistem dapat menampilkan dashboard sebagai halaman utama.
3. Sistem dapat mengelola master customer, port, carline, assy, dan production week.
4. Sistem dapat menerima upload file Excel Shipping Release.
5. Sistem dapat membaca sheet pada file Excel yang diunggah.
6. Sistem dapat menampilkan preview data sebelum disimpan.
7. Sistem dapat melakukan mapping data sesuai format customer.
8. Sistem dapat menyimpan data Shipping Release ke database.
9. Sistem dapat menampilkan Summary data order.
10. Sistem dapat melakukan export data ke file Excel.
11. Sistem dapat mendukung penyusunan Six-Month Production Plan.
12. Sistem dapat menampilkan variance order antar periode.
13. Sistem dapat mengelola data user dan hak akses.

### 4.3.2 Kebutuhan Nonfungsional

Kebutuhan nonfungsional aplikasi SIPLAN adalah sebagai berikut:

1. Sistem memiliki tampilan yang mudah digunakan oleh pengguna.
2. Sistem dapat mengelola data secara terpusat dalam database.
3. Sistem dapat mengurangi ketergantungan pada file Excel yang saling terhubung.
4. Sistem dapat membantu mengurangi risiko kesalahan manual.
5. Sistem dapat diakses melalui browser pada perangkat kerja perusahaan.
6. Sistem memiliki struktur kode yang mudah dikembangkan kembali.

## 4.4 Perancangan Sistem

Perancangan sistem dilakukan sebelum tahap implementasi agar proses pengembangan lebih terarah. Perancangan yang dibuat meliputi perancangan alur penggunaan sistem, perancangan aktivitas upload Shipping Release, dan perancangan database.

### 4.4.1 Use Case Diagram

Use case diagram digunakan untuk menggambarkan interaksi antara pengguna dan sistem. Pada aplikasi SIPLAN, pengguna dapat melakukan login, mengelola master data, mengunggah Shipping Release, melakukan mapping data, melihat Summary, export Excel, melihat Six-Month Production Plan, melihat variance order, serta mengelola user.

[Masukkan Gambar 4.1 Use Case Diagram SIPLAN]

Gambar 4.1 Use Case Diagram SIPLAN

### 4.4.2 Activity Diagram Upload Shipping Release

Activity diagram upload Shipping Release menggambarkan alur ketika pengguna mengunggah file Excel ke dalam sistem. Proses dimulai dari pengguna memilih menu upload, memilih file Excel, memilih sheet, melihat preview data, melakukan mapping, memvalidasi data, kemudian menyimpan data ke database.

[Masukkan Gambar 4.2 Activity Diagram Upload Shipping Release]

Gambar 4.2 Activity Diagram Upload Shipping Release

### 4.4.3 Perancangan Database

Database SIPLAN dirancang menggunakan PostgreSQL. Database digunakan untuk menyimpan data pengguna, master data, Shipping Release, Summary, Production Week, dan data pendukung lainnya. Beberapa tabel utama yang digunakan antara lain:

1. Tabel users untuk menyimpan data pengguna aplikasi.
2. Tabel customers untuk menyimpan data customer.
3. Tabel ports untuk menyimpan data port atau destination.
4. Tabel carlines untuk menyimpan data carline.
5. Tabel assies untuk menyimpan data assy.
6. Tabel production_weeks untuk menyimpan data week berdasarkan tanggal.
7. Tabel shipping_releases untuk menyimpan data hasil upload Shipping Release.
8. Tabel summaries untuk menyimpan atau menampilkan hasil pengolahan Summary.
9. Tabel spp untuk mendukung penyusunan Six-Month Production Plan.

[Masukkan Gambar 4.3 Rancangan Database SIPLAN]

Gambar 4.3 Rancangan Database SIPLAN

## 4.5 Implementasi Sistem

Implementasi sistem dilakukan menggunakan Laravel, React/Inertia, dan PostgreSQL. Laravel digunakan sebagai backend untuk mengatur proses bisnis, validasi, controller, model, dan koneksi database. React/Inertia digunakan untuk membangun antarmuka pengguna. PostgreSQL digunakan sebagai basis data untuk menyimpan seluruh data sistem.

### 4.5.1 Login dan Dashboard

Fitur login digunakan untuk membatasi akses pengguna ke dalam aplikasi. Pengguna harus memasukkan email dan password agar dapat masuk ke sistem. Setelah berhasil login, pengguna diarahkan ke halaman dashboard.

Dashboard digunakan untuk menampilkan informasi utama aplikasi dan menjadi pintu masuk ke fitur-fitur SIPLAN. Pada halaman ini, pengguna dapat mengakses menu master data, upload Shipping Release, Summary, SPP, variance order, dan manajemen user.

[Masukkan Gambar 4.4 Tampilan Login SIPLAN]

[Masukkan Gambar 4.5 Tampilan Dashboard SIPLAN]

### 4.5.2 Master Data

Fitur master data digunakan untuk mengelola data pendukung production planning. Master data yang dibuat meliputi customer, port, carline, assy, dan production week. Pada fitur ini, pengguna dapat menambahkan, melihat, mengubah, dan menghapus data.

Master Carline dan Master Assy juga dilengkapi dengan fitur upload Excel. Fitur ini dibuat agar pengguna dapat memasukkan data dalam jumlah besar tanpa harus menginput satu per satu. Proses upload meliputi download template, upload file, pemilihan sheet, preview data, validasi, dan penyimpanan ke database.

[Masukkan Gambar 4.6 Tampilan Master Data]

### 4.5.3 Upload Shipping Release

Fitur Upload Shipping Release merupakan fitur utama pada aplikasi SIPLAN. Fitur ini digunakan untuk mengunggah file Excel Shipping Release dari customer. Setelah file diunggah, sistem membaca sheet yang tersedia, menampilkan preview data, kemudian pengguna dapat melakukan mapping sesuai kebutuhan.

Mapping diperlukan karena format file dari setiap customer berbeda. Beberapa customer memiliki posisi header, kolom tanggal, quantity, dan part number yang berbeda. Dengan adanya proses mapping, sistem dapat menyesuaikan pembacaan data sebelum data disimpan ke database.

[Masukkan Gambar 4.7 Tampilan Upload Shipping Release]

### 4.5.4 Summary dan Export Excel

Fitur Summary digunakan untuk menampilkan hasil pengolahan data Shipping Release dalam bentuk yang lebih ringkas dan mudah dibaca. Data Summary membantu pengguna dalam melihat kebutuhan order berdasarkan customer, part number, week, atau periode tertentu.

Selain tampilan pada sistem, fitur Summary juga dilengkapi dengan export Excel. Export Excel dibuat agar pengguna tetap dapat menggunakan hasil olahan data sesuai format yang dibutuhkan dalam pekerjaan. Beberapa penyesuaian dilakukan berdasarkan revisi user, seperti perubahan format dari kolom menjadi baris, penambahan total bulanan, dan penyesuaian week sampai lima week.

[Masukkan Gambar 4.8 Tampilan Summary]

[Masukkan Gambar 4.9 Tampilan Export Excel]

### 4.5.5 Six-Month Production Plan

Fitur Six-Month Production Plan digunakan untuk mendukung penyusunan rencana produksi selama enam bulan. Data yang digunakan berasal dari hasil pengolahan Shipping Release dan master data yang telah tersedia di sistem. Dengan fitur ini, pengguna dapat melihat data pendukung rencana produksi secara lebih terstruktur.

### 4.5.6 Variance Order

Fitur Variance Order digunakan untuk membandingkan order customer antara periode saat ini dan periode sebelumnya. Fitur ini membantu pengguna mengetahui perubahan quantity, baik kenaikan maupun penurunan order. Dengan adanya fitur ini, perubahan order dapat lebih mudah dianalisis dan ditelusuri.

[Masukkan Gambar 4.10 Tampilan Variance Order]

### 4.5.7 Manajemen User dan Hak Akses

Fitur manajemen user digunakan untuk mengelola pengguna aplikasi. Melalui fitur ini, admin dapat menambahkan pengguna, mengubah data pengguna, menghapus pengguna, dan mengatur hak akses. Hak akses diperlukan agar setiap pengguna hanya dapat mengakses fitur sesuai kebutuhan dan perannya.

### 4.5.8 Mode Dark dan Light

Untuk meningkatkan kenyamanan pengguna, aplikasi SIPLAN juga dilengkapi dengan mode dark dan light. Pengguna dapat memilih tampilan sesuai preferensi atau kondisi lingkungan kerja.

## 4.6 Timeline Kegiatan Magang

Timeline kegiatan magang dapat dilihat pada tabel berikut.

| No | Periode | Kegiatan |
| --- | --- | --- |
| 1 | 9 Maret 2026 - 15 Maret 2026 | Pengenalan lingkungan kerja, observasi Departemen PPC, dan pemahaman proses Shipping Release. |
| 2 | 16 Maret 2026 - 31 Maret 2026 | Analisis kebutuhan sistem, diskusi dengan user, dan identifikasi kendala penggunaan Excel yang saling terhubung. |
| 3 | 1 April 2026 - 15 April 2026 | Perancangan sistem, perancangan database, use case, dan activity diagram. |
| 4 | 16 April 2026 - 30 April 2026 | Implementasi login, dashboard, layout aplikasi, dan master data awal. |
| 5 | 1 Mei 2026 - 15 Mei 2026 | Implementasi master customer, port, carline, assy, dan production week. |
| 6 | 16 Mei 2026 - 31 Mei 2026 | Implementasi upload Shipping Release, preview sheet, mapping data, dan penyimpanan data. |
| 7 | 1 Juni 2026 - 15 Juni 2026 | Implementasi Summary, export Excel, dan penyesuaian format berdasarkan kebutuhan user. |
| 8 | 16 Juni 2026 - 30 Juni 2026 | Implementasi Six-Month Production Plan, variance order, dan manajemen user. |
| 9 | 1 Juli 2026 - 9 Juli 2026 | Pengujian sistem, debugging, finalisasi fitur, dokumentasi, dan penyusunan laporan akhir. |

## 4.7 Hasil Kerja

Hasil kerja utama dari kegiatan magang ini adalah aplikasi SIPLAN berbasis web yang dapat digunakan untuk membantu pengelolaan Shipping Release pada Departemen PPC PT Jatim Autocomp Indonesia.

Fitur-fitur yang berhasil dikembangkan dapat dilihat pada tabel berikut.

| No | Fitur | Keterangan |
| --- | --- | --- |
| 1 | Login | Membatasi akses pengguna ke dalam aplikasi. |
| 2 | Dashboard | Menampilkan halaman utama dan akses ke menu sistem. |
| 3 | Master Customer | Mengelola data customer. |
| 4 | Master Port | Mengelola data port atau destination. |
| 5 | Master Carline | Mengelola data carline dan upload Excel. |
| 6 | Master Assy | Mengelola data assy dan upload Excel. |
| 7 | Master Production Week | Mengelola data week berdasarkan tanggal. |
| 8 | Upload Shipping Release | Mengunggah file Excel, memilih sheet, preview, mapping, dan menyimpan data. |
| 9 | Summary | Menampilkan ringkasan data Shipping Release. |
| 10 | Export Excel | Mengekspor data hasil pengolahan sesuai format kebutuhan user. |
| 11 | Six-Month Production Plan | Mendukung penyusunan rencana produksi enam bulan. |
| 12 | Variance Order | Membandingkan order antar periode. |
| 13 | Manajemen User | Mengelola pengguna dan hak akses aplikasi. |
| 14 | Dark/Light Mode | Menyediakan pilihan tampilan untuk kenyamanan pengguna. |

## 4.8 Pencapaian Target

Target utama kegiatan magang adalah menghasilkan aplikasi berbasis web yang dapat membantu proses pengelolaan Shipping Release. Berdasarkan hasil implementasi, target tersebut telah dicapai melalui pengembangan fitur utama seperti upload Shipping Release, mapping data, Summary, export Excel, Six-Month Production Plan, variance order, dan master data.

Aplikasi SIPLAN juga telah membantu mengurangi ketergantungan pada file Excel yang saling terhubung. Data yang sebelumnya tersebar pada beberapa file dapat dikelola secara lebih terpusat melalui database. Selain itu, proses preview dan mapping membantu pengguna memeriksa data sebelum disimpan sehingga risiko kesalahan dapat dikurangi.

Meskipun demikian, aplikasi masih dapat dikembangkan lebih lanjut, terutama pada bagian validasi data, histori perubahan data, dan notifikasi jika terdapat perubahan order yang signifikan.

## 4.9 Kendala yang Dihadapi dan Solusi

Selama proses pengembangan aplikasi, terdapat beberapa kendala yang dihadapi. Kendala dan solusi yang dilakukan dapat dilihat pada tabel berikut.

| No | Kendala | Solusi |
| --- | --- | --- |
| 1 | Format file Excel dari setiap customer berbeda-beda. | Membuat proses mapping agar sistem dapat menyesuaikan struktur data customer. |
| 2 | Posisi header, tanggal, dan quantity tidak selalu sama. | Melakukan pengecekan struktur file dan menyesuaikan logika parsing. |
| 3 | Beberapa data hasil upload terbaca null. | Memperbaiki validasi dan pembacaan cell Excel sebelum data disimpan. |
| 4 | Perhitungan week belum sesuai dengan tanggal. | Menyesuaikan logika perhitungan dengan data Master Production Week. |
| 5 | Format export Excel mengalami beberapa revisi. | Melakukan penyesuaian format export berdasarkan kebutuhan user. |
| 6 | Pengguna membutuhkan fitur input data dalam jumlah besar. | Menambahkan fitur upload Excel pada Master Carline dan Master Assy. |

## 4.10 Kontribusi kepada Tempat Magang

Kontribusi utama dari kegiatan magang ini adalah tersedianya aplikasi SIPLAN yang dapat membantu Departemen PPC dalam mengelola Shipping Release secara lebih terpusat. Aplikasi ini membantu mengurangi ketergantungan pada beberapa file Excel yang saling terhubung, mempermudah proses pencarian dan pengolahan data, serta mendukung pembuatan Summary, Six-Month Production Plan, dan variance order.

Dengan adanya SIPLAN, data Shipping Release dapat disimpan dalam database sehingga lebih mudah ditelusuri. Proses upload dan preview juga membantu pengguna memeriksa data sebelum disimpan. Selain itu, fitur export Excel tetap disediakan agar hasil pengolahan data dapat digunakan sesuai kebutuhan pekerjaan di Departemen PPC.

Secara keseluruhan, aplikasi SIPLAN diharapkan dapat meningkatkan efisiensi kerja, mengurangi risiko human error, dan mendukung proses perencanaan produksi secara lebih baik.

---

# BAB V KESIMPULAN DAN SARAN

## 5.1 Kesimpulan

Berdasarkan kegiatan magang yang telah dilaksanakan, dapat disimpulkan bahwa pengembangan aplikasi SIPLAN berbasis web dapat menjadi solusi untuk membantu proses pengelolaan Shipping Release pada Departemen PPC PT Jatim Autocomp Indonesia. Sebelumnya, proses pengelolaan data masih dilakukan menggunakan beberapa file Excel yang saling terhubung melalui link dan formula antarfile, sehingga rawan terjadi kesalahan data, putus link, perbedaan format, dan kesulitan dalam penelusuran perubahan order.

Aplikasi SIPLAN dikembangkan menggunakan Laravel, React/Inertia, dan PostgreSQL. Fitur yang dihasilkan meliputi autentikasi, dashboard, master data, upload Shipping Release, mapping data, Summary, export Excel, Six-Month Production Plan, variance order, dan manajemen user. Melalui aplikasi ini, proses pengelolaan data menjadi lebih terstruktur, terpusat, dan mudah digunakan oleh pengguna.

Selain menghasilkan aplikasi, kegiatan magang ini juga memberikan pengalaman bagi penulis dalam memahami proses bisnis di industri, menganalisis kebutuhan pengguna, merancang database, membangun aplikasi web, melakukan debugging, serta menyesuaikan sistem berdasarkan revisi dari user.

## 5.2 Saran

Berdasarkan hasil kegiatan magang dan pengembangan aplikasi SIPLAN, saran yang dapat diberikan adalah sebagai berikut:

1. Aplikasi SIPLAN dapat dikembangkan lebih lanjut dengan fitur validasi data yang lebih detail agar kesalahan pada saat upload file dapat dideteksi lebih awal.
2. Perusahaan dapat melakukan backup database secara berkala untuk menjaga keamanan data.
3. Pengguna perlu diberikan dokumentasi atau panduan penggunaan agar proses adaptasi terhadap sistem dapat berjalan lebih mudah.
4. Pengembangan selanjutnya dapat menambahkan fitur histori perubahan data agar setiap perubahan order dapat ditelusuri secara lebih lengkap.
5. Sistem dapat dikembangkan dengan fitur notifikasi apabila terdapat perubahan quantity atau variance order yang signifikan.
6. Aplikasi dapat dikembangkan lebih lanjut agar dapat menampilkan laporan dalam bentuk grafik atau visualisasi data.

## 5.3 Refleksi Mahasiswa

Melalui kegiatan magang ini, penulis memperoleh pengalaman langsung dalam menghadapi permasalahan nyata di dunia industri. Penulis belajar bahwa pengembangan sistem tidak hanya berkaitan dengan kemampuan teknis, tetapi juga kemampuan memahami kebutuhan pengguna, berkomunikasi dengan baik, menerima masukan, dan menyesuaikan sistem dengan kondisi data yang sebenarnya.

Penulis juga memperoleh pemahaman baru mengenai proses kerja Departemen PPC, khususnya dalam pengelolaan Shipping Release sebagai dasar perencanaan produksi. Dari sisi teknis, penulis dapat meningkatkan kemampuan dalam menggunakan Laravel, React/Inertia, PostgreSQL, pengolahan file Excel, debugging, dan pengujian sistem.

Kegiatan magang ini menjadi pengalaman berharga karena penulis dapat menerapkan ilmu yang diperoleh selama perkuliahan ke dalam proyek nyata yang memiliki manfaat bagi perusahaan.

## 5.4 Ucapan Terima Kasih

Penulis mengucapkan terima kasih kepada PT Jatim Autocomp Indonesia, khususnya Departemen Production Planning and Control, yang telah memberikan kesempatan kepada penulis untuk melaksanakan kegiatan magang. Penulis juga mengucapkan terima kasih kepada pembimbing lapangan, dosen pembimbing, seluruh staff PPC, keluarga, dan teman-teman yang telah memberikan dukungan selama kegiatan magang dan penyusunan laporan ini.

---

# DAFTAR PUSTAKA

[1] F. H. Muhammad, A. R. Taufiq, dan M. C. Wibowo, "Pengembangan Sistem Informasi Manajemen Berbasis Web Pada Perusahaan Penempatan Pekerja Migran Indonesia," *Jurnal Pengembangan Teknologi Informasi dan Ilmu Komputer*, vol. 8, no. 6, pp. 2400-2409, Jun. 2024. [Online]. Tersedia: https://j-ptiik.ub.ac.id/index.php/j-ptiik/article/view/13813

[2] R. P. Pratama, N. H. Wardani, dan W. N. Alim, "Pengembangan Sistem Informasi Terintegrasi Modul Perencanaan dan Realisasi Produksi pada Perusahaan Roti," *Jurnal Pengembangan Teknologi Informasi dan Ilmu Komputer*, vol. 7, no. 5, pp. 2100-2108, May 2023. [Online]. Tersedia: https://j-ptiik.ub.ac.id/index.php/j-ptiik/article/view/12711

[3] M. B. Soeltanong dan C. Sasongko, "Perencanaan Produksi dan Pengendalian Persediaan pada Perusahaan Manufaktur," *Jurnal Riset Akuntansi & Perpajakan*, vol. 8, no. 1, pp. 45-56, Jun. 2021. [Online]. Tersedia: https://journal.univpancasila.ac.id/index.php/jrap/article/view/2157

[4] A. D. Pratiwi dan A. Heriadi, "Sistem Informasi Manajemen Keuangan Berbasis Web Menggunakan Framework Laravel," *JTIM - Jurnal Teknologi Informasi dan Multimedia*, vol. 15, no. 2, pp. 1-5, Dec. 2023. [Online]. Tersedia: https://jtim.polinema.ac.id/index.php/jtim/article/view/434

[5] A. Hidayat, M. T. Anshori, dan R. A. Setyawan, "Pengembangan Sistem Informasi Inventaris Barang Berbasis Web Menggunakan Framework Laravel," *Jurnal Pengembangan Teknologi Informasi dan Ilmu Komputer*, vol. 7, no. 4, pp. 1800-1808, Apr. 2023. [Online]. Tersedia: https://j-ptiik.ub.ac.id/index.php/j-ptiik/article/view/12344

[6] A. B. Setiawan, N. H. Wardani, dan R. A. Setyawan, "Analisis Performa Load Time Halaman Statis antara Pendekatan Inertia dan REST API pada Laravel," *Jurnal Pengembangan Teknologi Informasi dan Ilmu Komputer*, vol. 9, no. 1, pp. 45-53, Jan. 2025. [Online]. Tersedia: https://j-ptiik.ub.ac.id/index.php/j-ptiik/article/view/13444

[7] K. W. Wedding, R. A. Setyawan, dan N. H. Wardani, "Pengembangan Sistem Informasi Jasa Pernikahan Berbasis Web Menggunakan Database PostgreSQL," *Jurnal Pengembangan Teknologi Informasi dan Ilmu Komputer*, vol. 8, no. 2, pp. 900-908, Feb. 2024. [Online]. Tersedia: https://j-ptiik.ub.ac.id/index.php/j-ptiik/article/view/13100

[8] D. Haryanto dan R. A. Setyawan, "Otomatisasi Ekstraksi Data Spreadsheet Menggunakan Server-Side Parsing pada Sistem Informasi Logistik," *Jurnal Pengembangan Teknologi Informasi dan Ilmu Komputer*, vol. 6, no. 12, pp. 5800-5808, Dec. 2022. [Online]. Tersedia: https://j-ptiik.ub.ac.id/index.php/j-ptiik/article/view/11888

[9] Y. Astuti, N. H. Wardani, dan R. A. Setyawan, "Pengujian Fungsional Web Menggunakan Metode Black Box Testing dengan Equivalence Partitioning," *Jurnal Pengembangan Teknologi Informasi dan Ilmu Komputer*, vol. 7, no. 6, pp. 2900-2908, Jun. 2023. [Online]. Tersedia: https://j-ptiik.ub.ac.id/index.php/j-ptiik/article/view/12999

---

# LAMPIRAN

## Lampiran 1. Surat Pengantar Magang

[Masukkan surat pengantar magang]

## Lampiran 2. Surat Balasan atau Penerimaan Magang

[Masukkan surat balasan atau surat penerimaan magang]

## Lampiran 3. Logbook Kegiatan Magang

[Masukkan logbook kegiatan magang]

## Lampiran 4. Dokumentasi Kegiatan Magang

[Masukkan dokumentasi kegiatan selama magang]

## Lampiran 5. Dokumentasi Tampilan Aplikasi SIPLAN

[Masukkan screenshot halaman login, dashboard, master data, upload Shipping Release, Summary, export Excel, SPP, variance order, dan manajemen user]

## Lampiran 6. Dokumentasi Teknis Sistem

[Masukkan dokumentasi teknis, rancangan database, atau potongan source code penting jika diperlukan]
