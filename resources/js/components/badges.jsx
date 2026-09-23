export const ORDER_STATUS = {
    pending: { label: 'Menunggu', badge: 'badge-pending' },
    completed: { label: 'Selesai', badge: 'badge-completed' },
};

export const ITEM_STATUS = {
    pending: { label: 'Menunggu', badge: 'badge-pending' },
    cooking: { label: 'Dimasak', badge: 'badge-cooking' },
    done: { label: 'Selesai', badge: 'badge-done' },
};

export const PAYMENT_STATUS = {
    paid: { label: 'Lunas', badge: 'badge-paid' },
    unpaid: { label: 'Belum Bayar', badge: 'badge-unpaid' },
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
    cash: 'bg-emerald-100 text-emerald-800',
    bank: 'bg-blue-100 text-blue-800',
    qris: 'bg-purple-100 text-purple-800',
};

export const PAY_METHOD_LABEL = { cash: 'Tunai', bank: 'Transfer', qris: 'QRIS' };

export function PayMethodBadge({ method }) {
    return <span className={`badge ${PAY_METHOD_STYLE[method] ?? 'badge-pending'}`}>{PAY_METHOD_LABEL[method] ?? method}</span>;
}