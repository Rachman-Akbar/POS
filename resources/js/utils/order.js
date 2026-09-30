/**
 * Status & pembayaran untuk order kasir.
 *
 * Dipakai bersama oleh tabel Pesanan, modal detail, dan filter tahap supaya
 * definisi "draft", "belum lunas", dan tahap proses tidak pernah berbeda antar
 * tempat.
 */

/** Kunci filter untuk pesanan yang masih berstatus draft. */
/**
 * Label meja yang ditampilkan ke kasir.
 *
 * Sumbernya bisa entri pengaturan (angka polos seperti `1`) atau nilai yang
 * sudah tersimpan di `orders.table_number` (sudah berawalan `Meja `). Entri
 * angka diberi awalan "Meja", sisanya dipakai apa adanya supaya "Take Away"
 * tidak tampil menjadi "Meja Take Away".
 *
 * Idempoten: aman dijalankan berulang pada nilai yang sudah berawalan "Meja ".
 *
 * @param {string|int|null} entry
 * @return {string}
 */
export function tableLabel(entry) {
    const value = String(entry ?? '').trim();

    if (value === '') {
        return '-';
    }

    return /^\d+$/.test(value) ? `Meja ${value}` : value;
}

export const DRAFT_FILTER = 'draft';

/** Kunci filter untuk pesanan yang sudah dibatalkan. */
export const VOID_FILTER = 'void';

/**
 * True bila order masih berstatus draft, yaitu order tersimpan yang belum
 * diproses: belum ada invoice, belum reserving stok, belum masuk dapur.
 */
export function isDraftOrder(order) {
    return order?.status === 'draft';
}

/**
 * Total pembayaran yang sudah diterima. Order yang belum di-finalize (draft)
 * tidak punya invoice sama sekali, jadi selalu nol.
 */
export function receivedOf(order) {
    const receipts = order?.invoice?.receipts ?? [];
    return receipts.reduce((sum, receipt) => sum + Number(receipt.gross_amount), 0);
}

/** Sisa tagihan sebuah order. */
export function remainingOf(order) {
    return Number(order?.total_amount ?? 0) - receivedOf(order);
}

/**
 * Sisa uang per penerimaan pembayaran yang masih boleh diretur.
 *
 * Dipakai oleh panel koreksi di detail pesanan (untuk memilih penerimaan) dan
 * oleh prompt pembatalan di kolom aksi daftar pesanan, supaya angka "uang yang
 * masih bisa dikembalikan" di kedua tempat tidak pernah berbeda.
 */
export function refundableReceiptsOf(order) {
    const receipts = order?.invoice?.receipts ?? [];

    return receipts
        .map((receipt) => ({
            id: receipt.id,
            label: receipt.payment_method?.toUpperCase() ?? '-',
            amount: Math.max(0, Number(receipt.gross_amount) - Number(receipt.refund_amount ?? 0)),
        }))
        .filter((receipt) => receipt.amount > 0);
}

/** Total uang yang masih bisa dikembalikan dari sebuah order. */
export function refundableOf(order) {
    return refundableReceiptsOf(order).reduce((sum, receipt) => sum + receipt.amount, 0);
}

/**
 * Order dianggap belum lunas bila masih ada sisa tagihan. Ambang 0.004 dipakai
 * agar pembulatan rupiah tidak membuat order yang sebenarnya lunas ikut
 * terhitung belum lunas.
 */
export function isUnpaidOrder(order) {
    return remainingOf(order) > 0.004;
}

/**
 * Kunci filter status pembayaran di tabel Pesanan.
 *
 * Draft tidak punya invoice sehingga selalu masuk kelompok "unpaid".
 */
export const PAYMENT_FILTER = {
    All: 'all',
    Paid: 'paid',
    Unpaid: 'unpaid',
};

/**
 * Kelompok status pembayaran sebuah order: sudah lunas atau masih ada sisa.
 * Memakai isUnpaidOrder supaya angka yang difilter identik dengan badge di
 * tabel dan angka sisa tagihan di Detail Pesanan.
 */
export function paymentFilterOf(order) {
    return isUnpaidOrder(order) ? PAYMENT_FILTER.Unpaid : PAYMENT_FILTER.Paid;
}

/** True bila order lolos filter status pembayaran yang sedang dipilih. */
export function matchesPaymentFilter(order, filter) {
    if (!filter || filter === PAYMENT_FILTER.All) return true;

    return paymentFilterOf(order) === filter;
}

/**
 * Tahap proses sebuah pesanan untuk filter & badge.
 *
 * Alur tahap: Draft → Diproses (pending) → Dimasak → Dikirim → Selesai.
 * Pesanan draft selalu "Draft", pesanan yang dibatalkan "Dibatalkan", dan
 * sisanya diturunkan dari status item paling awal yang masih berjalan; item
 * yang semuanya selesai menghasilkan "Selesai".
 *
 * Item `cancelled` diabaikan: void menandai seluruh item pesanan itu
 * `cancelled`, sehingga memakainya untuk menentukan tahap akan membuat
 * pesanan yang sudah dibatalkan terlihat seperti masih berjalan di dapur.
 */
export function orderProcessStatus(order) {
    if (isDraftOrder(order)) return DRAFT_FILTER;
    if (isVoidedOrder(order)) return VOID_FILTER;

    const items = (order?.items ?? []).filter((item) => item.status !== 'cancelled');
    if (items.length === 0) return 'pending';

    // Item yang masih `draft` berarti pesanan ini belum masuk produksi meski
    // status order-nya sudah bukan draft, jadi tahapnya masih Draft.
    if (items.some((item) => item.status === 'draft')) return DRAFT_FILTER;

    if (items.every((item) => item.status === 'done')) return 'done';
    if (items.some((item) => item.status === 'pending')) return 'pending';
    if (items.some((item) => item.status === 'cooking')) return 'cooking';

    return 'sent';
}

/**
 * Tanggal pesanan untuk tabel, format ringkas `dd MMM, HH.mm`.
 *
 * @param {string|null} value
 * @return {string}
 */
export function formatOrderDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** True bila pesanan sudah dibatalkan admin. */
export function isVoidedOrder(order) {
    return order?.status === 'void';
}

/** True bila uang pada pesanan sudah dikembalikan seluruhnya lewat retur. */
export function isRefundedOrder(order) {
    return order?.payment_status === 'refunded';
}
