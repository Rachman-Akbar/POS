<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * Catat perubahan penting: role/permission, harga, metode pembayaran,
     * kas/bank, hapus data, serta perubahan role user.
     *
     * Sengaja tidak melempar error: audit gagal tidak boleh membatalkan
     * operasi bisnis yang sudah berhasil.
     */
    public function log(
        Request $request,
        string $action,
        string $module,
        ?Model $record = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        try {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'module' => $module,
                'record_type' => $record ? class_basename($record) : null,
                'record_id' => $record?->getKey() !== null ? (string) $record->getKey() : null,
                'description' => $description,
                'old_values' => $oldValues ? $this->clean($oldValues) : null,
                'new_values' => $newValues ? $this->clean($newValues) : null,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable) {
            // Audit trail bersifat best-effort.
        }
    }

    /**
     * Buang nilai kosong dan batasi panjang agar kolom JSON tetap rapi.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function clean(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $clean[$key] = is_scalar($value) || $value === null ? $value : json_encode($value);
        }

        return $clean;
    }
}
