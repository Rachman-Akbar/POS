import Swal from 'sweetalert2';

const theme = {
    confirmButtonColor: '#ea580c',
    cancelButtonColor: '#6b7280',
    reverseButtons: true,
    background: document.body.classList.contains('dark') ? '#1e293b' : '#ffffff',
    color: document.body.classList.contains('dark') ? '#f1f5f9' : '#1f2937',
};

export { Swal };

export function notifySuccess(title, text = '') {
    return Swal.fire({
        icon: 'success',
        title,
        text,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
    });
}

export function notifyError(title, text = '') {
    return Swal.fire({
        icon: 'error',
        title,
        text,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
    });
}

export function confirmAction(title, text = '', confirmLabel = 'Ya, lanjutkan') {
    return Swal.fire({
        title,
        text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: confirmLabel,
        cancelButtonText: 'Batal',
        ...theme,
    });
}

/**
 * Minta alasan singkat untuk sebuah koreksi.
 *
 * Backend menyimpan alasan koreksi di audit log dan ada yang mewajibkan
 * isinya, jadi dialog ini tidak pernah menerima alasan kosong. Dipakai dari
 * detail pesanan maupun dari kolom aksi daftar pesanan supaya bentuk
 * permintaannya selalu sama.
 *
 * @returns {Promise<string|null>} alasannya, atau null bila dibatalkan
 */
export async function askCorrectionReason(title, html, confirmLabel = 'Ya, lanjutkan') {
    const { value } = await Swal.fire({
        title,
        html,
        input: 'text',
        inputPlaceholder: 'Contoh: pelanggan membatalkan pesanan, input harga keliru',
        inputAttributes: { maxlength: 255 },
        showCancelButton: true,
        confirmButtonText: confirmLabel,
        cancelButtonText: 'Batal',
        ...theme,
        inputValidator: (value) => (value && value.trim() ? null : 'Alasan wajib diisi.'),
    });

    return value ? value.trim() : null;
}