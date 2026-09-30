export const ORDER_STATUS = {
    draft: { label: 'Draft', badge: 'badge-pending' },
    pending: { label: 'Menunggu', badge: 'badge-pending' },
    completed: { label: 'Selesai', badge: 'badge-completed' },
    void: { label: 'Dibatalkan', badge: 'badge-unpaid' },
};

/**
 * Tahap item menu: Draft → Diproses → Dimasak → Dikirim → Selesai.
 *
 * `cancelled` bukan tahap produksi, melainkan penanda pembatalan yang dipakai
 * void, jadi tidak masuk urutan di atas.
 */
export const ITEM_STATUS = {
    draft: { label: 'Draft', badge: 'badge-unpaid' },
    pending: { label: 'Diproses', badge: 'badge-pending' },
    cooking: { label: 'Dimasak', badge: 'badge-cooking' },
    sent: { label: 'Dikirim', badge: 'badge-prepared' },
    done: { label: 'Selesai', badge: 'badge-done' },
    cancelled: { label: 'Dibatalkan', badge: 'badge-unpaid' },
};

/** Urutan tahap produksi, dipakai untuk progressed/summary di papan dapur. */
export const ITEM_STATUS_FLOW = ['draft', 'pending', 'cooking', 'sent', 'done'];

export const PAYMENT_STATUS = {
    paid: { label: 'Lunas', badge: 'badge-paid' },
    partial: { label: 'Bayar Sebagian', badge: 'badge-cooking' },
    unpaid: { label: 'Belum Bayar', badge: 'badge-unpaid' },
    refunded: { label: 'Diretur', badge: 'badge-unpaid' },
};

export const PAYMENT_TYPE = {
    pay_now: 'Bayar Dulu',
    pay_later: 'Bayar Nanti',
};

export function StatusBadge({ status }) {
    const meta = ORDER_STATUS[status] ?? { label: status, badge: 'badge-pending' };
    return <span className={`badge ${meta.badge}`}>{meta.label}</span>;
}

export function ItemStatusBadge({ status }) {
    const meta = ITEM_STATUS[status] ?? { label: status, badge: 'badge-pending' };
    return <span className={`badge ${meta.badge}`}>{meta.label}</span>;
}

export function PaymentBadge({ status }) {
    const meta = PAYMENT_STATUS[status] ?? { label: status, badge: 'badge-unpaid' };
    return <span className={`badge ${meta.badge}`}>{meta.label}</span>;
}

export const PAY_METHOD_STYLE = {
    cash: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    bank: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
    qris: 'bg-purple-100 text-purple-800 dark:bg-purple-500/15 dark:text-purple-300',
    ewallet: 'bg-pink-100 text-pink-800 dark:bg-pink-500/15 dark:text-pink-300',
};

export const PAY_METHOD_LABEL = { cash: 'Tunai', bank: 'Transfer', qris: 'QRIS', ewallet: 'E-Wallet' };

export function PayMethodBadge({ method }) {
    return <span className={`badge ${PAY_METHOD_STYLE[method] ?? 'badge-pending'}`}>{PAY_METHOD_LABEL[method] ?? method}</span>;
}