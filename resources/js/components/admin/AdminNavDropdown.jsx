import { ChevronDown, LayoutGrid } from 'lucide-react';
import { useState } from 'react';
import { useClickOutside } from '../../hooks/useClickOutside';

/**
 * Dropdown navigasi halaman admin.
 *
 * Menggantikan rail yang selama ini membentang horizontal di atas konten.
 * Dropdown ini adalah satu "tab": tombol kiri menempel di baris yang sama
 * dengan judul halaman, dan saat dibuka menampilkan seluruh modul admin yang
 * dikelompokkan menurut cara pakainya (Katalog / Pembayaran / Akses / Sistem).
 * Memilih salah satu berpindah ke halaman itu, tombol sama menutupnya lagi.
 */
export default function AdminNavDropdown({ groups, active, onChange }) {
    const [open, setOpen] = useState(false);
    const ref = useClickOutside(() => setOpen(false));

    const items = groups.flatMap((group) => group.items);
    const current = items.find((item) => item.key === active) ?? items[0];
    if (!current) return null;

    const Icon = current.icon ?? LayoutGrid;

    return (
        <div className="relative shrink-0" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                title="Pilih halaman admin"
                className="btn btn-ghost !px-3 justify-between gap-3"
            >
                <span className="flex items-center gap-2 min-w-0">
                    <span className="w-6 h-6 rounded-md bg-accent text-on-accent flex items-center justify-center shrink-0">
                        <Icon size={13} />
                    </span>
                    <span className="truncate">{current.label}</span>
                    {current.count !== undefined && (
                        <span className="badge badge-muted">{current.count}</span>
                    )}
                </span>
                <ChevronDown size={15} className={`text-muted shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="absolute left-0 top-13 mt-1 w-64 max-h-[70vh] overflow-y-auto scrollbar-thin bg-surface rounded-xl py-1.5 shadow-xl z-40">
                    {groups.map((group) => {
                        if (group.items.length === 0) return null;

                        return (
                            <div key={group.key} className="mb-1">
                                <div className="px-3 pt-2 pb-1 text-[11px] font-semibold text-muted uppercase tracking-wide">
                                    {group.label}
                                </div>
                                {group.items.map((item) => {
                                    const isActive = item.key === active;
                                    const ItemIcon = item.icon ?? LayoutGrid;

                                    return (
                                        <button
                                            key={item.key}
                                            type="button"
                                            onClick={() => {
                                                onChange(item.key);
                                                setOpen(false);
                                            }}
                                            className={`w-full flex items-center gap-2.5 px-3 py-2 text-sm text-left transition-colors cursor-pointer ${
                                                isActive ? 'bg-accent-soft text-accent-ink font-semibold' : 'hover:bg-surface-2'
                                            }`}
                                        >
                                            <ItemIcon size={15} className="shrink-0" />
                                            <span className="flex-1 min-w-0 truncate">{item.label}</span>
                                            {item.count !== undefined && (
                                                <span className="text-[11px] font-bold text-muted">{item.count}</span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}