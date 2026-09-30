<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\SalesInvoice;
use App\Models\SalesReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Batas data yang boleh dilihat satu user (data scope).
 *
 * Permission menjawab "boleh melakukan apa", scope menjawab "data mana yang
 * boleh dilihat". Untuk POS, kasir tetap boleh menangani pesanan milik siapa pun
 * — itu memang tugasnya di kasir yang sama — tapi angka penjualan milik kasir
 * lain bukan haknya. Jadi scope hanya menyaring transaksi yang sudah settles,
 * bukan antrean pesanan yang masih aktif.
 */
class TransactionScope
{
    /**
     * Filter query order berdasarkan hak lihat transaksi.
     *
     * @template T of Builder<Order>
     *
     * @param  T  $query
     * @return T
     */
    public function orders(Builder $query, ?User $user): Builder
    {
        if ($this->seesEverything($user)) {
            return $query;
        }

        $settled = $this->settledStatuses();

        return $query->where(function (Builder $inner) use ($user, $settled): void {
            // Pesanan yang masih menunggu pembayaran tetap terlihat supaya
            // kasir bisa melayani order yang dibuat kasir/pelayan lain.
            $inner->whereNotIn('payment_status', $settled)
                ->orWhere('user_id', $user?->getKey());
        });
    }

    /**
     * @template T of Builder<SalesInvoice>
     *
     * @param  T  $query
     * @return T
     */
    public function invoices(Builder $query, ?User $user): Builder
    {
        if ($this->seesEverything($user)) {
            return $query;
        }

        $settled = $this->settledStatuses();

        return $query->where(function (Builder $inner) use ($user, $settled): void {
            $inner->whereHas(
                'order',
                fn (Builder $orders) => $orders
                    ->whereNotIn('payment_status', $settled)
                    ->orWhere('user_id', $user?->getKey())
            );
        });
    }

    /**
     * @template T of Builder<SalesReceipt>
     *
     * @param  T  $query
     * @return T
     */
    public function receipts(Builder $query, ?User $user): Builder
    {
        if ($this->seesEverything($user)) {
            return $query;
        }

        return $query->whereHas(
            'invoice.order',
            fn (Builder $orders) => $orders->where('user_id', $user?->getKey())
        );
    }

    /**
     * Pembatalan dan retur selalu milik transaksi yang sama, jadi user tanpa
     * `transaction.view.all` tidak boleh menyentuh transaksi yang di luar
     * jangkauannya meski ia memegang permission koreksi (mis. admin area yang
     * sedang shift di kasir).
     */
    public function canCorrect(?User $user, Order $order): bool
    {
        if ($this->seesEverything($user)) {
            return true;
        }

        return (int) $order->user_id === (int) $user?->getKey();
    }

    private function seesEverything(?User $user): bool
    {
        return $user === null || $user->hasPermission('transaction.view.all');
    }

    /**
     * @return array<int, string>
     */
    private function settledStatuses(): array
    {
        return [
            PaymentStatus::Paid->value,
            PaymentStatus::Refunded->value,
        ];
    }
}
