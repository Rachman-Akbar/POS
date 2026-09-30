---
paths:
  - 'resources/js/**/*.{jsx,js}'
---

# JS

## Free variable di JSX lolos build, harus diuji di browser sungguhan
Build Vite/rollup TIDAK menangkap free variable di JSX — identifier yang tidak didefinisikan tetap lolos `npm run build` dan baru meledak saat dieksekusi, biasanya baru muncul React melepas seluruh tree sehingga halaman jadi blank (tidak ada error di terminal, hanya `document.body.scrollHeight === 0`).

Dua kali hal ini menimpa halaman kasir: `cartViewOpen` lalu `onViewChange`. Keduanya adalah nama prop milik komponen anak (`CheckoutPanel`) yang ikut ditulis di dalam JSX komponen induk (`CashierDashboard`) — `grep` akan bilang "sudah ada" karena nama itu memang ada di scope fungsi lain, sehingga perbaikan berdasarkan grep selalu meleset.

Aturan: sebelum menyatakan selesai setiap perubahan UI, jalankan Chromium headless + chromedriver, login, klik alur yang-diubah, lalu cek `Runtime.exceptionThrown` dan `document.querySelector('main')` masih ada. Jangan percaya `npm run build` sebagai bukti UI benar. Hati-hati juga: teks yang di-`uppercase` via CSS terbaca `CEK PESANAN` di `innerText`, jadi jangan melakukan pencocokan memakai huruf kapital persis.
