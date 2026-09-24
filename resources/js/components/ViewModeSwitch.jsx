import { LayoutGrid, Table2, GalleryHorizontal } from 'lucide-react';

export const VIEW_MODES = [
    { key: 'grid', label: 'Grid', icon: LayoutGrid },
    { key: 'table', label: 'Tabel', icon: Table2 },
    { key: 'hero', label: 'Gallery', icon: GalleryHorizontal },
];

export default function ViewModeSwitch({ value = 'grid', onChange, className = '' }) {
    const index = Math.max(0, VIEW_MODES.findIndex((mode) => mode.key === value));
    const current = VIEW_MODES[index] ?? VIEW_MODES[0];
    const next = VIEW_MODES[(index + 1) % VIEW_MODES.length];
    const Icon = current.icon;

    return (
        <button
            type="button"
            onClick={() => onChange?.(next.key)}
            title={`Tampilan: ${current.label} — klik untuk ${next.label}`}
            aria-label={`Ganti tampilan, saat ini ${current.label}`}
            className={`btn-icon w-9 h-9 !rounded-lg !bg-orange-600 !text-white hover:!bg-orange-700 shrink-0 ${className}`}
        >
            <Icon size={16} />
        </button>
    );
}
