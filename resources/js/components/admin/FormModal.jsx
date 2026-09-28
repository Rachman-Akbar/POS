import { useEffect } from 'react';
import { X } from 'lucide-react';

/**
 * Label + kontrol form, dengan pesan error validasi dari API.
 */
export function Field({ label, error, hint, children }) {
    return (
        <div>
            <label className="label">{label}</label>
            {children}
            {error && <p className="text-[11px] text-negative mt-1">{error}</p>}
            {!error && hint && <p className="text-[11px] text-muted mt-1">{hint}</p>}
        </div>
    );
}

/**
 * Kolom berpindah dua, dipakai form master data yang banyak field.
 */
export function FieldRow({ children, className = 'grid grid-cols-1 sm:grid-cols-2 gap-3' }) {
    return <div className={className}>{children}</div>;
}

/**
 * Shell modal yang dipakai semua form master data.
 */
export default function FormModal({
    title,
    subtitle,
    onClose,
    onSubmit,
    submitLabel = 'Simpan',
    pending = false,
    disabled = false,
    width = 'max-w-md',
    children,
}) {
    useEffect(() => {
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onClick={onClose}>
            <div
                className={`bg-surface rounded-2xl w-full ${width} max-h-[88vh] flex flex-col`}
                onClick={(event) => event.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-3 px-5 pt-5 pb-3 border-b border-line">
                    <div className="min-w-0">
                        <h3 className="font-bold">{title}</h3>
                        {subtitle && <p className="text-xs text-muted mt-0.5">{subtitle}</p>}
                    </div>
                    <button className="btn-icon w-8 h-8 text-muted hover:bg-surface-3 shrink-0" onClick={onClose} title="Tutup">
                        <X size={18} />
                    </button>
                </div>

                <div className="overflow-y-auto px-5 py-4">{children}</div>

                <div className="px-5 py-3 border-t border-line flex items-center justify-end gap-2">
                    <button className="btn btn-ghost" onClick={onClose} disabled={pending}>
                        Batal
                    </button>
                    <button className="btn btn-primary" onClick={onSubmit} disabled={disabled || pending}>
                        {pending ? 'Menyimpan...' : submitLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
