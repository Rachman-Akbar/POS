<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'customer_type' => $this->customer_type,
            'buyer_type' => $this->customer_type,
            'buyer_email' => $this->guest_email,
            'buyer_name' => $this->guest_name,
            'buyer_phone' => $this->guest_phone,
            'buyer_address' => $this->guest_address,
            'buyer_nik' => $this->guest_nik,
            'buyer_npwp' => $this->guest_npwp,
            'buyer_province' => $this->guest_province,
            'buyer_city' => $this->guest_city,
            'buyer_company' => $this->guest_company_name,
            'buyer_postal_code' => $this->guest_postal_code,
            'buyer_country' => $this->guest_country,
            'notes' => $this->guest_notes,
            'guest_email' => $this->guest_email,
            'guest_name' => $this->guest_name,
            'guest_phone' => $this->guest_phone,
            'guest_address' => $this->guest_address,
            'guest_nik' => $this->guest_nik,
            'guest_npwp' => $this->guest_npwp,
            'guest_province' => $this->guest_province,
            'guest_city' => $this->guest_city,
            'guest_company_name' => $this->guest_company_name,
            'guest_postal_code' => $this->guest_postal_code,
            'guest_country' => $this->guest_country,
            'guest_notes' => $this->guest_notes,
            'subtotal' => (float) $this->subtotal,
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'can_edit' => $this->status === 'pending',
            'can_cancel' => $this->status === 'pending',
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'admin_notes' => $this->when($request->user() !== null, $this->admin_notes),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'status_histories' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($history) => [
                'id' => $history->id,
                'from_status' => $history->from_status,
                'to_status' => $history->to_status,
                'notes' => $history->notes,
                'changed_by' => $history->relationLoaded('user') && $history->user ? [
                    'id' => $history->user->id,
                    'name' => $history->user->name,
                ] : null,
                'created_at' => $history->created_at,
            ])->values()),
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
