/**
 * Status & pembayaran untuk order kasir.
 *
 * Dipakai bersama oleh tabel Pesanan, modal detail, dan filter tahap supaya
 * definisi "draft", "belum lunas", dan tahap proses tidak pernah berbeda antar
 * tempat.
 */

/** Kunci filter untuk pesanan yang masih berstatus draft. */
export const DRAFT_FILTER = 'draft';

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
 * Tahap proses sebuah pesanan untuk filter & badge. Draft selalu jadi "Draft"
 * (belum diproses). Selain itu tahap diturunkan dari status item terendah;
 * pesanan tanpa item dianggap masih Dipesan.
 */
export function orderProcessStatus(order) {
    if (isDraftOrder(order)) return DRAFT_FILTER;

    const items = order?.items ?? [];
    if (items.length === 0) return 'pending';
    if (items.every((item) => item.status === 'done')) return 'done';
    if (items.some((item) => item.status === 'pending')) return 'pending';
    if (items.some((item) => item.status === 'cooking')) return 'cooking';

    return 'sent';
}
