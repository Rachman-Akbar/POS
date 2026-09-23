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