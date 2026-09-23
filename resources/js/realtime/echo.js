import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo = null;

export function getEcho() {
    if (echo) {
        return echo;
    }

    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return echo;
}

export function listenToOrders(handlers = {}) {
    const channel = getEcho().channel('pos.orders');

    channel.stopListening('order.created');
    channel.stopListening('order.status.updated');
    channel.stopListening('item.status.updated');
    channel.stopListening('payment.processed');

    channel.listen('order.created', (event) => handlers.onOrderCreated?.(event));
    channel.listen('order.status.updated', (event) => handlers.onStatusUpdated?.(event));
    channel.listen('item.status.updated', (event) => handlers.onItemUpdated?.(event));
    channel.listen('payment.processed', (event) => handlers.onPaymentProcessed?.(event));

    return channel;
}