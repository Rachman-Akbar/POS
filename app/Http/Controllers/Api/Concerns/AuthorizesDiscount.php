<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Diskon mengurangi uang yang benar-benar diterima, jadi ia diberikan permission
 * sendiri dan tidak bisa dipakai diam-diam oleh role yang hanya boleh membaca.
 *
 * Pemeriksaan ini dipakai di semua jalur yang bisa memberi diskon: membuat
 * pesanan, menyimpan draft, dan mengoreksi transaksi. Diskon nol tetap boleh
 * supaya kasir tidak terhenti.
 */
trait AuthorizesDiscount
{
    private function authorizeDiscount(Request $request, float $discount): void
    {
        if ($discount <= 0 || $this->mayDiscount($request->user())) {
            return;
        }

        abort(
            Response::HTTP_FORBIDDEN,
            'Anda tidak memiliki hak untuk memberi diskon. Hubungi supervisor.',
        );
    }

    private function mayDiscount(?User $user): bool
    {
        return (bool) $user?->hasPermission('pos.discount');
    }
}
