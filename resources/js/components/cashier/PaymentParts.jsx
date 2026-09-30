import { ChevronDown, QrCode, Wallet } from 'lucide-react';
import CurrencyInput from '../CurrencyInput';
import { PriceRow } from '../Price';
import QrisQrCode from '../QrisQrCode';

/**
 * Potongan UI pembayaran yang dipakai bersama oleh panel "Cek Pesanan" dan
 * halaman "Detail Pesanan" supaya tampilan metode pembayaran dan rekening
 * tujuan selalu sama.
 */

/**
 * Lebar tetap kolom nilai pada blok total (Subtotal, Diskon, PPN, Grand Total,
 * Nominal) supaya simbol Rp dan angka selalu lurus dalam satu kolom.
 *
 * Diekspor supaya kedua halaman memakai lebar yang persis sama.
 */
export const TOTAL_VALUE = 'w-36 shrink-0';

/**
 * Cangkang panel transaksi untuk kolom kanan.
 *
 * Dipakai bersama oleh "Cek Pesanan" dan "Detail Pesanan" supaya keduanya
 * punya geometri yang identik: menempel di bawah header, tinggi dibatasi
 * terhadap viewport, dan isi panjang digulir di dalam panel — bukan terpotong
 * di luar layar. Judulnya ikut menempel agar tetap terlihat saat digulir.
 *
 * Sengaja tanpa `card`: kolom kanan halaman kasir tidak memakai garis atau
 * sudut membulat, hanya jarak 2px dari kolom menu. `min-w-0` mencegah
 * isi yang lebar (baris tabel, QRIS) memaksa kolom ini melebar dan menabrak
 * katalog saat layar digeser ke kanan.
 */
/**
 * Kolom transaksi.
 *
 * Default (`fill` belum aktif): panel ikut tinggi halaman dengan `sticky`,
 * dipakai halaman detail pesanan yang layout-nya satu kolom.
 *
 * `fill` dipakai layar kasir, yang kolomnya sudah dikunci setinggi layar dan
 * digulirnya ditangani oleh induknya. Tanpa mode ini, panel punya scroller
 * sendiri sementara halaman ikut tergulir, jadi menggulir transaksi ikut
 * menyeret kolom menu.
 */
export function TransactionCard({ title, icon: Icon, actions, fill = false, children }) {
    const shell = fill
        ? 'min-w-0 bg-surface p-[3px]'
        : 'min-w-0 bg-surface p-[3px] sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto overflow-x-hidden scrollbar-thin';

    return (
        <div className={shell}>
            <div className="sticky top-0 z-10 bg-surface flex items-center justify-between gap-1 p-[3px] pb-[3px]">
                <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-1">
                    {Icon ? <Icon size={16} /> : null} {title}
                </h3>
                {actions}
            </div>

            {children}
        </div>
    );
}

/**
 * Pilihan metode pembayaran.
 *
 * Tanpa icon dan tanpa border/background: yang tidak aktif hanya teks redup,
 * yang aktif menyala. Label "Metode" juga dihapus di pemanggil karena pilihan
 * ini sudah jelas dari isinya.
 */
export function MethodChip({ method, active, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            title={method.name}
            className={`whitespace-nowrap px-2 py-1 rounded-md text-xs font-semibold transition-colors cursor-pointer ${
                active
                    ? 'bg-accent text-on-accent'
                    : 'text-muted hover:text-content hover:bg-surface-2'
            }`}
        >
            {method.name}
        </button>
    );
}

/**
 * Tombol "Bayar" untuk sidebar transaksi.
 *
 * Semula metode, QRIS, dan input nominal diletakkan dalam satu dropdown yang
 * harus dibuka dulu. Sekarang panel pembayaran dibuka langsung dari tombolnya,
 * dan urutannya dibalik: input nominal dulu, metode pembayaran di bawahnya.
 * Alasannya, angka selalu jadi keputusan pertama kasir — nominal menentukan
 * kembalian dan kelayakan bayar sebagian, sementara metode baru menyesuaikan
 * setelah nominal diketahui. Dengan urutan lama metode mengambil ruang di atas
 * input sehingga nominal ter-desak ke bawah.
 *
 * Menutup cukup dengan menekan tombol yang sama, jadi tidak ada tombol kedua
 * dan tidak ada kondisi modal yang harus di-reset.
 *
 * Nilai yang dibutuhkan:
 *
 * - `open`/`onToggle`  - keadaan panel, dikendalikan pemanggil.
 * - `methods`/`method`/`onMethodChange` - daftar metode dan yang aktif.
 * - `qrisId`           - QR statis; panel QRIS hanya muncul bila diisi.
 * - `amount`           - `{ raw, onChange, placeholder, disabled }` untuk
 *                        input nominal. `disabled: true` dipakai order yang
 *                        sudah lunas sehingga nominal ditampilkan, bukan diisi.
 * - `status`           - `{ text, cls }` untuk badge Lunas/Belum Lunas.
 * - `change`           - kembalian; barisnya disembunyikan saat 0.
 * - `note`/`hint`      - keterangan singkat di atas dan di bawah input.
 * - `summary`          - nilai ringkas pada tombol saat panel ditutup.
 */
export function PaymentButton({
    open,
    onToggle,
    methods = [],
    method,
    onMethodChange,
    qrisId = null,
    amount,
    status = null,
    change = 0,
    note = null,
    hint = null,
    summary = null,
}) {
    return (
        <div className="mt-3">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={open}
                title={open ? 'Tutup form pembayaran' : 'Isi pembayaran'}
                className={`btn w-full justify-between ${open ? 'btn-secondary' : 'btn-primary'}`}
            >
                <span className="flex items-center gap-1.5 uppercase tracking-wide">
                    <Wallet size={14} /> Bayar
                </span>

                <span className="flex items-center gap-2 min-w-0">
                    {!open && status && <span className={`badge shrink-0 ${status.cls}`}>{status.text}</span>}
                    {!open && summary}
                    <ChevronDown
                        size={15}
                        className={`shrink-0 transition-transform ${open ? 'rotate-180' : ''}`}
                    />
                </span>
            </button>

            {open && (
                <div className="mt-2 space-y-2 rounded-xl border border-line bg-surface-2/40 p-2">
                    {note}

                    {amount && (
                        <div className="flex items-center gap-2">
                            <CurrencyInput
                                value={amount.raw}
                                onChange={amount.onChange}
                                placeholder={amount.placeholder}
                                disabled={amount.disabled}
                                wrapperClassName="flex-1 min-w-0"
                                className="input !py-2.5 text-right text-base font-bold disabled:opacity-60"
                            />
                            {status && <span className={`badge shrink-0 ${status.cls}`}>{status.text}</span>}
                        </div>
                    )}

                    {change > 0 && (
                        <div className="flex items-center justify-between gap-3 rounded-lg bg-surface-2 px-3 py-2">
                            <span className="text-xs font-semibold text-muted">Kembalian</span>
                            <PriceRow
                                value={change}
                                className={TOTAL_VALUE}
                                symbolClassName="text-muted"
                                amountClassName="font-bold text-positive"
                            />
                        </div>
                    )}

                    {methods.length > 0 && (
                        <div className="pt-1">
                            <span className="mb-1.5 block text-[11px] font-semibold uppercase tracking-wide text-muted">
                                Metode pembayaran
                            </span>
                            <div className="flex flex-wrap gap-1.5">
                                {methods.map((item) => (
                                    <MethodChip
                                        key={item.code}
                                        method={item}
                                        active={method === item.code}
                                        onClick={() => onMethodChange(item.code)}
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    {qrisId && (
                        <div className="bg-accent-soft rounded-lg p-3">
                            <div className="flex items-center gap-2 mb-2 text-accent-ink font-bold text-sm">
                                <QrCode size={15} /> QRIS &mdash; Scan untuk Bayar
                            </div>
                            <QrisQrCode value={qrisId} />
                            <div className="text-[11px] text-muted mt-2 text-center">{qrisId}</div>
                        </div>
                    )}

                    {hint}
                </div>
            )}
        </div>
    );
}
