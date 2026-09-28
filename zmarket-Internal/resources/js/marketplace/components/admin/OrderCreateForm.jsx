import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Plus, Trash2, X } from "lucide-react";
import { fieldClass, Loading } from "@/components/Common";
import { api, errorMessage, paginatedData, resourceData } from "@/lib/api";

const blankLine = () => ({ product_id: "", quantity: "1" });
const blankBuyer = () => ({ customer_type: "individual", name: "", email: "", phone: "", address: "", nik: "", npwp: "", province: "", city: "", company_name: "", postal_code: "", country: "Indonesia", notes: "", payment_method: "internal_billing" });

export function OrderCreateForm({ onClose, onSaved }) {
    const [buyer, setBuyer] = useState(blankBuyer());
    const [items, setItems] = useState([blankLine()]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const products = useQuery({ queryKey: ["admin-products-for-order"], queryFn: async () => paginatedData(await api.get("/admin/products", { params: { per_page: 100, status: "published" } })).data });
    const business = buyer.customer_type === "business";
    const updateBuyer = (key) => (event) => setBuyer((c) => ({ ...c, [key]: event.target.value }));
    const updateLine = (index, patch) => setItems((c) => c.map((item, i) => i === index ? { ...item, ...patch } : item));

    const submit = async (event) => {
        event.preventDefault(); setSaving(true); setError("");
        try {
            await api.post("/admin/orders", { ...buyer, nik: business ? buyer.nik : null, npwp: business ? buyer.npwp : null, province: business ? buyer.province : null, city: business ? buyer.city : null, company_name: business ? buyer.company_name : null, postal_code: business ? buyer.postal_code : null, country: business ? buyer.country : null, notes: buyer.notes || null, items: items.map((item) => ({ product_id: Number(item.product_id), quantity: Number(item.quantity) })) });
            await onSaved(); onClose();
        } catch (e) { setError(errorMessage(e, "Order gagal dibuat.")); } finally { setSaving(false); }
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4">
            <form onSubmit={submit} className="mx-auto my-4 w-full max-w-5xl rounded-2xl border border-gray-100 bg-white shadow-2xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div><h2 className="text-lg font-black text-gray-900">Buat Order</h2><p className="mt-0.5 text-sm text-gray-400">Harga dan stok dihitung ulang oleh backend.</p></div>
                    <button type="button" onClick={onClose} className="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"><X size={18} /></button>
                </div>
                <div className="p-6">
                    {error ? <div className="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-600">{error}</div> : null}
                    <div className="grid grid-cols-2 gap-1.5 rounded-xl border border-gray-200 bg-gray-50 p-1">
                        <button type="button" onClick={() => setBuyer((c) => ({ ...c, customer_type: "individual" }))} className={`rounded-lg px-4 py-2.5 text-sm font-bold transition ${!business ? "bg-white text-green-700 shadow-sm" : "text-gray-500"}`}>Perorangan</button>
                        <button type="button" onClick={() => setBuyer((c) => ({ ...c, customer_type: "business" }))} className={`rounded-lg px-4 py-2.5 text-sm font-bold transition ${business ? "bg-white text-green-700 shadow-sm" : "text-gray-500"}`}>Badan Usaha</button>
                    </div>
                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        {business ? <>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700 md:col-span-2">Perusahaan<input required value={buyer.company_name} onChange={updateBuyer("company_name")} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Nama penanggung jawab<input required value={buyer.name} onChange={updateBuyer("name")} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">NIK<input required inputMode="numeric" maxLength={16} pattern="[0-9]{16}" value={buyer.nik} onChange={updateBuyer("nik")} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">NPWP<input required value={buyer.npwp} onChange={updateBuyer("npwp")} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Negara<input required value={buyer.country} onChange={updateBuyer("country")} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Provinsi<input required value={buyer.province} onChange={updateBuyer("province")} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Kota<input required value={buyer.city} onChange={updateBuyer("city")} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Kode Pos<input required value={buyer.postal_code} onChange={updateBuyer("postal_code")} className={fieldClass} /></label>
                        </> : <label className="grid gap-1.5 text-sm font-semibold text-gray-700 md:col-span-2">Nama buyer<input required value={buyer.name} onChange={updateBuyer("name")} className={fieldClass} /></label>}
                        <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Email buyer<input required type="email" value={buyer.email} onChange={updateBuyer("email")} className={fieldClass} /></label>
                        <label className="grid gap-1.5 text-sm font-semibold text-gray-700">No. HP<input required value={buyer.phone} onChange={updateBuyer("phone")} className={fieldClass} /></label>
                        <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Metode pembayaran<select value={buyer.payment_method} onChange={updateBuyer("payment_method")} className={fieldClass}><option value="internal_billing">Internal Billing</option><option value="bank_transfer">Transfer Manual</option><option value="cod">COD</option></select></label>
                        <label className="grid gap-1.5 text-sm font-semibold text-gray-700 md:col-span-2">Alamat<textarea required rows={3} value={buyer.address} onChange={updateBuyer("address")} className={fieldClass} /></label>
                        <label className="grid gap-1.5 text-sm font-semibold text-gray-700 md:col-span-2">Catatan<textarea rows={2} value={buyer.notes} onChange={updateBuyer("notes")} className={fieldClass} /></label>
                    </div>
                    <section className="mt-6 rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div><h3 className="text-sm font-bold text-gray-900">Item Order</h3><p className="text-xs text-gray-400">Produk yang sama hanya boleh dipilih satu kali.</p></div>
                            <button type="button" onClick={() => setItems((c) => [...c, blankLine()])} className="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-3.5 py-2 text-xs font-bold text-white transition hover:bg-gray-800"><Plus size={14} /> Tambah</button>
                        </div>
                        {products.isLoading ? <Loading label="Memuat produk..." /> : (
                            <div className="mt-4 grid gap-3">
                                {items.map((item, index) => (
                                    <div key={index} className="grid gap-2 md:grid-cols-[1fr_120px_auto]">
                                        <select required value={item.product_id} onChange={(event) => updateLine(index, { product_id: event.target.value })} className={fieldClass}>
                                            <option value="">Pilih produk</option>
                                            {products.data?.filter((p) => p.is_active).map((p) => <option key={p.id} value={p.id}>{p.name} · {p.sku} · Rp {Number(p.price).toLocaleString("id-ID")}</option>)}
                                        </select>
                                        <input required type="number" min="1" max="999" value={item.quantity} onChange={(event) => updateLine(index, { quantity: event.target.value })} className={fieldClass} />
                                        <button type="button" disabled={items.length === 1} onClick={() => setItems((c) => c.filter((_, i) => i !== index))} className="grid h-11 w-11 place-items-center rounded-lg border border-gray-200 text-gray-400 transition hover:border-red-300 hover:text-red-500 disabled:opacity-30"><Trash2 size={16} /></button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
                <div className="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" onClick={onClose} className="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Batal</button>
                    <button disabled={saving || products.isLoading} className="rounded-xl bg-green-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700 disabled:bg-gray-200 disabled:shadow-none">{saving ? "Menyimpan..." : "Buat Order"}</button>
                </div>
            </form>
        </div>
    );
}
