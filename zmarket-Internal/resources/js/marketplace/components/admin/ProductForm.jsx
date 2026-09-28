import { useEffect, useMemo, useState } from "react";
import { ImagePlus, Images, Info, Package, Trash2, X } from "lucide-react";
import { fieldClass } from "@/components/Common";
import { api, errorMessage, resourceData } from "@/lib/api";

const MAX_IMAGES = 12;

const emptyForm = () => ({ category_id: "", name: "", slug: "", sku: "", type: "product", description: "", brand: "", price: "", track_stock: true, stock: "0", status: "published", is_featured: false, is_active: true });

function normalizeBoolean(value, fallback = false) {
    if (value === null || value === undefined) return fallback;
    if (typeof value === "string") return !["0", "false", "off", "no", ""].includes(value.toLowerCase());
    return Boolean(value);
}

function productToForm(product) {
    if (!product) return emptyForm();
    return { category_id: String(product.primary_category_id ?? product.category_id ?? ""), name: product.name ?? "", slug: product.slug ?? "", sku: product.sku ?? "", type: product.type ?? "product", description: product.description ?? "", brand: product.brand ?? "", price: String(product.price ?? ""), track_stock: normalizeBoolean(product.track_stock, true), stock: String(product.stock ?? "0"), status: product.status ?? "published", is_featured: normalizeBoolean(product.is_featured), is_active: normalizeBoolean(product.is_active, true) };
}

function existingProductImages(product) {
    if (!product) return [];
    const images = Array.isArray(product.images) && product.images.length ? product.images : Array.isArray(product.image_urls) ? product.image_urls : product.thumbnail ? [product.thumbnail] : [];
    return images.map((image, index) => ({ key: `existing-${image?.id ?? index}`, path: typeof image === "string" ? image : image.path ?? image.url, url: typeof image === "string" ? image : image.url ?? image.path })).filter((image) => image.path && image.url);
}

export function ProductForm({ product, categories, onClose, onSaved }) {
    const [activeTab, setActiveTab] = useState("info");
    const [form, setForm] = useState(() => productToForm(product));
    const [existingImages, setExistingImages] = useState(() => existingProductImages(product));
    const [newImages, setNewImages] = useState([]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");

    const newImagePreviews = useMemo(() => newImages.map((file) => ({ file, url: URL.createObjectURL(file) })), [newImages]);
    useEffect(() => () => { newImagePreviews.forEach((p) => URL.revokeObjectURL(p.url)); }, [newImagePreviews]);

    const imageCount = existingImages.length + newImages.length;
    const update = (key, value) => setForm((c) => ({ ...c, [key]: value }));
    const addFiles = (event) => { const files = Array.from(event.target.files ?? []); setNewImages((c) => [...c, ...files].slice(0, Math.max(0, MAX_IMAGES - existingImages.length))); event.target.value = ""; };

    const submit = async (event) => {
        event.preventDefault(); setSaving(true); setError("");
        try {
            const body = new FormData();
            body.append("primary_category_id", form.category_id); body.append("category_id", form.category_id); body.append("category_ids", JSON.stringify([Number(form.category_id)]));
            body.append("name", form.name); body.append("slug", form.slug); body.append("sku", form.sku); body.append("type", form.type); body.append("description", form.description); body.append("brand", form.brand);
            body.append("price", form.price); body.append("track_stock", form.track_stock ? "1" : "0"); body.append("stock", form.track_stock ? form.stock : "");
            body.append("status", form.status); body.append("is_featured", form.is_featured ? "1" : "0"); body.append("is_active", form.is_active ? "1" : "0");
            body.append("existing_images", JSON.stringify(existingImages.map((image) => image.path)));
            newImages.forEach((file) => body.append("images[]", file));
            const response = product ? await api.post(`/admin/products/${product.id}`, body) : await api.post("/admin/products", body);
            await onSaved(resourceData(response)); onClose();
        } catch (e) { setError(errorMessage(e, "Produk gagal disimpan.")); } finally { setSaving(false); }
    };

    const tabs = [{ id: "info", label: "Informasi", icon: <Info size={16} /> }, { id: "images", label: "Gambar", icon: <Images size={16} /> }, { id: "stock", label: "Harga & Stok", icon: <Package size={16} /> }];

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4">
            <form onSubmit={submit} className="mx-auto my-4 w-full max-w-5xl rounded-2xl border border-gray-100 bg-white shadow-2xl">
                <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                    <div><h2 className="text-lg font-black text-gray-900">{product ? "Edit Produk" : "Tambah Produk"}</h2><p className="mt-0.5 text-sm text-gray-400">Produk menggunakan satu harga, satu SKU, dan satu stok.</p></div>
                    <button type="button" onClick={onClose} className="grid h-8 w-8 place-items-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"><X size={18} /></button>
                </div>
                <div className="flex gap-1 overflow-x-auto border-b border-gray-100 px-6 pt-3">
                    {tabs.map((tab) => <button key={tab.id} type="button" onClick={() => setActiveTab(tab.id)} className={`inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-bold transition ${activeTab === tab.id ? "border-green-600 text-green-700" : "border-transparent text-gray-400 hover:text-gray-600"}`}>{tab.icon}{tab.label}</button>)}
                </div>
                <div className="p-6">
                    {error ? <div className="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-600">{error}</div> : null}
                    {activeTab === "info" ? (
                        <div className="grid gap-4 md:grid-cols-2">
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Nama produk<input required value={form.name} onChange={(e) => update("name", e.target.value)} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Slug<input value={form.slug} onChange={(e) => update("slug", e.target.value)} placeholder="Otomatis dari nama jika kosong" className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Kategori<select required value={form.category_id} onChange={(e) => update("category_id", e.target.value)} className={fieldClass}><option value="">Pilih kategori</option>{categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Jenis<select value={form.type} onChange={(e) => update("type", e.target.value)} className={fieldClass}><option value="product">Produk</option><option value="service">Layanan</option></select></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700 md:col-span-2">Deskripsi<textarea rows={4} value={form.description} onChange={(e) => update("description", e.target.value)} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Brand<input value={form.brand} onChange={(e) => update("brand", e.target.value)} className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Status<select value={form.status} onChange={(e) => update("status", e.target.value)} className={fieldClass}><option value="published">Published</option><option value="draft">Draft</option><option value="archived">Archived</option></select></label>
                            <label className="flex items-center gap-2 text-sm font-semibold text-gray-700"><input type="checkbox" checked={form.is_featured} onChange={(e) => update("is_featured", e.target.checked)} className="rounded" /> Featured</label>
                            <label className="flex items-center gap-2 text-sm font-semibold text-gray-700"><input type="checkbox" checked={form.is_active} onChange={(e) => update("is_active", e.target.checked)} className="rounded" /> Aktif</label>
                        </div>
                    ) : null}
                    {activeTab === "images" ? (
                        <div>
                            <label className="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-dashed border-gray-300 px-5 py-4 text-sm font-semibold text-gray-600 transition hover:border-green-400 hover:text-green-600"><ImagePlus size={18} /> Tambah Gambar<input type="file" accept="image/png,image/jpeg,image/webp" multiple onChange={addFiles} className="hidden" /></label>
                            <p className="mt-2 text-sm text-gray-400">{imageCount}/{MAX_IMAGES} gambar. Gambar pertama menjadi gambar utama.</p>
                            <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                {existingImages.map((image) => <div key={image.key} className="group relative aspect-square overflow-hidden rounded-xl border border-gray-100 bg-gray-50"><img src={image.url} alt="Produk" className="h-full w-full object-cover" /><button type="button" onClick={() => setExistingImages((c) => c.filter((item) => item.key !== image.key))} className="absolute right-2 top-2 rounded-lg bg-white/90 p-1.5 text-gray-400 opacity-0 shadow-sm transition group-hover:opacity-100 hover:text-red-500"><Trash2 size={14} /></button></div>)}
                                {newImagePreviews.map((preview, index) => <div key={`${preview.file.name}-${index}`} className="group relative aspect-square overflow-hidden rounded-xl border border-gray-100 bg-gray-50"><img src={preview.url} alt={preview.file.name} className="h-full w-full object-cover" /><button type="button" onClick={() => setNewImages((c) => c.filter((_, i) => i !== index))} className="absolute right-2 top-2 rounded-lg bg-white/90 p-1.5 text-gray-400 opacity-0 shadow-sm transition group-hover:opacity-100 hover:text-red-500"><Trash2 size={14} /></button></div>)}
                            </div>
                        </div>
                    ) : null}
                    {activeTab === "stock" ? (
                        <div className="grid gap-4 md:grid-cols-2">
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">SKU<input value={form.sku} onChange={(e) => update("sku", e.target.value)} placeholder="Dibuat otomatis jika kosong" className={fieldClass} /></label>
                            <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Harga (Rp)<input required type="number" min="0" step="0.01" value={form.price} onChange={(e) => update("price", e.target.value)} className={fieldClass} /></label>
                            <label className="flex items-center gap-2 text-sm font-semibold text-gray-700 md:col-span-2"><input type="checkbox" checked={form.track_stock} onChange={(e) => update("track_stock", e.target.checked)} className="rounded" /> Pantau stok</label>
                            {form.track_stock ? <label className="grid gap-1.5 text-sm font-semibold text-gray-700">Stok<input required type="number" min="0" step="1" value={form.stock} onChange={(e) => update("stock", e.target.value)} className={fieldClass} /></label> : null}
                        </div>
                    ) : null}
                </div>
                <div className="flex justify-end gap-3 border-t border-gray-100 px-6 py-4">
                    <button type="button" onClick={onClose} className="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">Batal</button>
                    <button disabled={saving} className="rounded-xl bg-green-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm shadow-green-600/20 transition hover:bg-green-700 disabled:bg-gray-200 disabled:shadow-none">{saving ? "Menyimpan..." : "Simpan Produk"}</button>
                </div>
            </form>
        </div>
    );
}
