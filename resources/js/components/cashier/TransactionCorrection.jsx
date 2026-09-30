import { useMemo, useState } from 'react';
import { Ban, RotateCcw, ShieldAlert } from 'lucide-react';
import { formatIDR, parseNumber } from '../../api/client';
import { Swal, confirmAction } from '../../utils/alerts';

/**
 * Panel koreksi transaksi: pembatalan penuh (void) dan retur sebagian.
 *
 * Ini bukan lapisan keamanan. Tombol hanya muncul bila user memegang
 * `transaction.void` / `transaction.refund`, tapi endpoint-nya yang benar-benar
 * menolak kasir. Panel ini sengaja dibuat sepi supaya tidak ada kesan kasir
 * boleh menyentuh uang hanya karena menu ini kebetulan terbuka.
 *
 * Dua aturan yang dijaga di sini, sama seperti di backend:
 *
 * - Void mengembalikan seluruh uang yang masuk dan menutup pesanan, jadi tidak
 *   bisa dipakai bersamaan dengan retur pada order yang sama.
 * - Retur tidak boleh melebihi sisa uang yang benar-benar sudah diterima.
 */
export default function TransactionCorrection({ order, canVoid, canRefund, onVoid, onRefund, busy = false }) {
    const [refundOpen, setRefundOpen] = useState(false);
    const [refundRaw, setRefundRaw] = useState('');

    const receipts = order?.invoice?.receipts ?? [];

    /** Sisa uang per penerimaan yang masih boleh diretur. */
    const refundable = useMemo(
        () =>
            receipts
                .map((receipt) => ({
                    id: receipt.id,
                    label: receipt.payment_method?.toUpperCase() ?? '-',
                    amount: Math.max(0, Number(receipt.gross_amount) - Number(receipt.refund_amount ?? 0)),
                }))
                .filter((receipt) => receipt.amount > 0),
        [receipts],
    );

    const refundableTotal = useMemo(
        () => refundable.reduce((sum, receipt) => sum + receipt.amount, 0),
        [refundable],
    );

    const voided = order?.status === 'void';
    const canAct = !voided && !busy;

    /**
     * Alasan wajib diisi karena pembukuan dan audit log menyimpan alasannya.
     * SweetAlert dipakai, bukan modal sendiri, supaya alasan yang diketik kasir
     * tidak hilang saat daftar pesanan di-refetch.
     */
    const askReason = async (title, intro, confirmLabel) => {
        const { value } = await Swal.fire({
            title,
            html: intro,
            input: 'text',
            inputPlaceholder: 'Contoh: pelanggan membatalkan pesanan, input harga keliru',
            inputAttributes: { maxlength: 255 },
            showCancelButton: true,
            confirmButtonText: confirmLabel,
            cancelButtonText: 'Batal',
            confirmButtonColor: '#ea580c',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            inputValidator: (value) => (value && value.trim() ? null : 'Alasan wajib diisi.'),
        });

        return value ? value.trim() : null;
    };

    const handleVoid = async () => {
        const reason = await askReason(
            'Pembatalkan transaksi?',
            `Seluruh uang yang sudah masuk <b>${formatIDR(refundableTotal)}</b> akan dikembalikan ke pelanggan, stok dikembalikan, dan pesanan ditutup sebagai <b>Dibatalkan</b>. Tindakan ini tercatat di audit log.`,
            'Ya, batalkan',
        );

        if (!reason) return;

        const confirmed = await confirmAction(
            'Yakin membatalkan transaksi ini?',
            'Pembatalan tidak bisa dibatalkan dan transaksi tidak bisa dibayar lagi.',
            'Ya, batalkan',
        );

        if (confirmed.isConfirmed) {
            await onVoid(reason);
        }
    };

    const handleRefund = async () => {
        if (refundable.length === 0) return;

        if (refundable.length > 1) {
            await Swal.fire({
                title: 'Pilih penerimaan pembayaran',
                text: 'Pesanan ini menerima pembayaran lebih dari sekali. Retur selalu masuk ke penerimaan tertentu.',
                icon: 'info',
            });

            return;
        }

        const receipt = refundable[0];
        const raw = refundRaw === '' ? receipt.amount : parseNumber(refundRaw);
        const amount = Math.min(raw, receipt.amount);

        if (!(amount > 0)) {
            await Swal.fire({ title: 'Nominal retur tidak valid', icon: 'warning' });

            return;
        }

        const reason = await askReason(
            'Retur sebagian?',
            `Uang <b>${formatIDR(amount)}</b> dari pembayaran <b>${receipt.label}</b> akan dikembalikan. Retur tidak mengembalikan barang, hanya uang.`,
            'Ya, retur',
        );

        if (!reason) return;

        await onRefund(receipt.id, amount, reason);
        setRefundRaw('');
        setRefundOpen(false);
    };

    if (voided) {
        return (
            <div className="mt-4 rounded-xl bg-surface-2 p-3 flex items-start gap-2">
                <ShieldAlert size={16} className="text-muted shrink-0 mt-0.5" />
                <div className="text-[11px] text-muted">
                    <span className="font-semibold text-content">Pesanan ini sudah dibatalkan.</span>{' '}
                    {order.voided_at && `Dibatalkan ${new Date(order.voided_at).toLocaleString('id-ID')}.`}{' '}
                    {order.void_reason && `Alasan: ${order.void_reason}`}
                </div>
            </div>
        );
    }

    if (!canVoid && !canRefund) return null;

    return (
        <div className="mt-4 pt-3 border-t border-surface-2">
            <div className="flex items-center justify-between gap-2 mb-2">
                <span className="label !mb-0">Koreksi Transaksi</span>
                <span className="text-[11px] text-muted">
                    {refundableTotal > 0
                        ? `Uang masuk ${formatIDR(refundableTotal)}`
                        : 'Belum ada uang masuk'}
                </span>
            </div>

            <div className="flex flex-wrap gap-2">
                {canRefund && refundableTotal > 0 && (
                    <button
                        onClick={() => setRefundOpen((open) => !open)}
                        disabled={!canAct}
                        className="btn btn-secondary justify-center"
                        title="Kembalikan sebagian uang kepada pelanggan"
                    >
                        <RotateCcw size={15} /> Retur Sebagian
                    </button>
                )}

                {canVoid && (
                    <button
                        onClick={handleVoid}
                        disabled={!canAct}
                        className="btn btn-danger justify-center"
                        title="Batalkan seluruh transaksi dan kembalikan semua uang"
                    >
                        <Ban size={15} /> Batalkan Transaksi
                    </button>
                )}
            </div>

            {refundOpen && canRefund && refundable.length === 1 && (
                <div className="mt-3 rounded-xl bg-surface-2 p-3 space-y-2">
                    <label className="label" htmlFor={`refund-${order.id}`}>
                        Nominal retur (maks. {formatIDR(refundable[0].amount)})
                    </label>
                    <input
                        id={`refund-${order.id}`}
                        className="input"
                        inputMode="numeric"
                        value={refundRaw}
                        onChange={(event) => setRefundRaw(event.target.value.replace(/\D/g, ''))}
                        placeholder={String(refundable[0].amount)}
                    />
                    <button
                        onClick={handleRefund}
                        disabled={busy}
                        className="btn btn-primary w-full justify-center"
                    >
                        <RotateCcw size={15} /> Proses Retur
                    </button>
                </div>
            )}

            <p className="text-[11px] text-muted mt-2">
                Setiap koreksi wajib beralasan dan tercatat atas nama Anda.
            </p>
        </div>
    );
}
