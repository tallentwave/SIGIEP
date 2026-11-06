<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardian extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_suspected' => 'boolean', 'can_be_contacted' => 'boolean'];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
