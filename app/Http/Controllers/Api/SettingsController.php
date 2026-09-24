<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * Public POS settings + payment methods (everything the UI needs).
     */
    public function index(): JsonResponse
    {
        $settings = Setting::where('is_active', true)->get();

        return response()->json([
            'data' => [
                'store_name' => Setting::get('pos.store_name', 'POS'),
                'ppn_rate' => (float) Setting::get('pos.ppn_rate', 0),
                'qris_id' => Setting::get('pos.qris_id', ''),
                'receipt_footer' => Setting::get('pos.receipt_footer', 'Terima kasih!'),
                'cashier' => Setting::cashierFlags(),
                'table_numbers' => Setting::tableNumbers(),
                'payment_methods' => PaymentMethod::where('is_active', true)
                    ->orderBy('id')
                    ->get(['id', 'code', 'type', 'name', 'mdr_rate']),
                'raw' => $settings,
            ],
        ]);
    }
}
