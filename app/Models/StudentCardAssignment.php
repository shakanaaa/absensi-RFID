<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCardAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(RfidCard::class, 'rfid_card_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
