import { Banknote, ChevronDown, CreditCard, Landmark, QrCode, Wallet } from 'lucide-react';
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
 * Cangkang panel transaksi untuk sidebar kanan.
 *
 * Dipakai bersama oleh "Cek Pesanan" dan "Detail Pesanan" supaya keduanya
 * punya geometri yang identik: menempel di bawah header, tinggi dibatasi
 * terhadap viewport, dan isi panjang digulir di dalam panel — bukan terpotong
 * di luar layar. Judulnya ikut menempel agar tetap terlihat saat digulir.
 */
export function TransactionCard({ title, icon: Icon, actions, children }) {
    return (
        <div className="card sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto scrollbar-thin">
            <div className="sticky top-0 z-10 bg-surface flex items-center justify-between gap-2 pb-3">
                <h3 className="font-bold text-sm uppercase tracking-wide flex items-center gap-2">
                    {Icon ? <Icon size={16} /> : null} {title}
                </h3>
                {actions}
            </div>

            {children}
        </div>
    );
}

const METHOD_ICONS = { kas: Banknote, bank: Landmark, qris: QrCode };

/**
 * Pilihan metode pembayaran berbentuk chip.
 */
export function MethodChip({ method, active, onClick }) {
    const Icon = METHOD_ICONS[method.type] ?? METHOD_ICONS[method.code] ?? CreditCard;
    return (
        <button
            onClick={onClick}
            className={`whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-semibold inline-flex items-center gap-1.5 transition-colors cursor-pointer ${
                active ? 'bg-accent text-on-accent' : 'bg-surface-3 text-muted hover:bg-surface-3/70'
            }`}
        >
            <Icon size={14} /> {method.name}
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
