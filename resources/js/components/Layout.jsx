import TopHeader from './TopHeader';

export default function Layout({ header = {}, children }) {
    return (
        <div className="min-h-screen bg-page flex flex-col overflow-x-clip">
            <TopHeader {...header} />
            {/*
             * Main container seluruh halaman memakai padding 3px ke semua
             * sisi, termasuk 3px ke dalam di kiri dan kanan menuju tengah.
             * 1px terbukti terlalu mepet dan 2px masih terasa menempel tepi
             * layar. Halaman kasir
             * butuh ruang fullest di layar, dan padding lama membuat kolom
             * kanan tergeser sampai menabrak kolom menu saat layar digeser ke
             * kanan. Jarak antar elemen tetap diurus tiap komponen, bukan di
             * sini, supaya panel mana pun bisa rapat tanpa merusak yang lain.
             */}
            <main className="flex-1 w-full mx-auto max-w-[1600px] p-[3px]">{children}</main>
        </div>
    );
}