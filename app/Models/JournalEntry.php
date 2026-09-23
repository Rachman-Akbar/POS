<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'type',
        'date',
        'description',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * @return HasMany<JournalDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(JournalDetail::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
