<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RfidCard extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'date'];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentCardAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(StudentCardAssignment::class)->where('status', 'active');
    }
}
