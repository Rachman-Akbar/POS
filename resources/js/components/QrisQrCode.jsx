import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { Loader2 } from 'lucide-react';

export default function QrisQrCode({ value, size = 'w-full aspect-square' }) {
    const [dataUrl, setDataUrl] = useState('');

    useEffect(() => {
        let active = true;
        QRCode.toDataURL(value || 'QRIS-STATIS-0001', {
            width: 600,
            margin: 2,
            color: { dark: '#170a33', light: '#ffffff' },
        })
            .then((url) => {
                if (active) setDataUrl(url);
            })
            .catch(() => {
                if (active) setDataUrl('');
            });
        return () => {
            active = false;
        };
    }, [value]);

    return (
        <div className={`${size} bg-white rounded-xl overflow-hidden flex items-center justify-center border border-gray-200`}>
            {dataUrl ? (
                <img src={dataUrl} alt="QRIS" className="w-full h-full object-contain p-2" />
            ) : (
                <Loader2 size={24} className="animate-spin text-purple-600" />
            )}
        </div>
    );
}