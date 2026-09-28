import { currency } from "@/lib/format";

export const fieldClass = "rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition-all duration-200 focus:border-green-500 focus:ring-2 focus:ring-green-100 disabled:bg-gray-50 disabled:text-gray-400";

export function Loading({ label = "Memuat data..." }) {
    return (
        <div className="flex min-h-52 items-center justify-center gap-3 text-gray-400">
            <span className="h-6 w-6 animate-spin rounded-full border-2 border-green-600 border-t-transparent" />
            <span className="text-sm font-medium">{label}</span>
        </div>
    );
}

export function Empty({ title, action }) {
    return (
        <div className="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center">
            <div className="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full bg-green-50">
                <svg className="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                </svg>
            </div>
            <h3 className="text-base font-bold text-gray-700">{title}</h3>
            {action ? <div className="mt-5">{action}</div> : null}
        </div>
    );
}

export function QueryError({ message = "Data belum dapat dimuat." }) {
    return (
        <div className="rounded-2xl border border-red-100 bg-red-50/50 px-6 py-10 text-center">
            <div className="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-full bg-red-100">
                <svg className="h-7 w-7 text-red-500" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
            </div>
            <h3 className="font-bold text-red-700">{message}</h3>
            <button
                type="button"
                onClick={() => window.location.reload()}
                className="mt-4 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-red-700"
            >
                Muat ulang
            </button>
        </div>
    );
}

export function productPrice(product) {
    return currency.format(Number(product?.price) || 0);
}

const statusClass = {
    pending: "bg-amber-50 text-amber-700 border border-amber-200",
    confirmed: "bg-blue-50 text-blue-700 border border-blue-200",
    processing: "bg-violet-50 text-violet-700 border border-violet-200",
    completed: "bg-green-50 text-green-700 border border-green-200",
    cancelled: "bg-red-50 text-red-700 border border-red-200",
};

export function StatusBadge({ status }) {
    return (
        <span className={`inline-flex rounded-lg px-2.5 py-1 text-xs font-bold ${statusClass[status] ?? "bg-gray-100 text-gray-600"}`}>
            {status === 'completed' ? 'Selesai' : status === 'pending' ? 'Menunggu' : status === 'confirmed' ? 'Dikonfirmasi' : status === 'processing' ? 'Diproses' : status === 'cancelled' ? 'Dibatalkan' : status}
        </span>
    );
}
