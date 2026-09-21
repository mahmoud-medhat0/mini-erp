<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolMovement extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tool_movement';

    protected $fillable = [
        'tool_id',
        'event_type',
        'from_status',
        'to_status',
        'from_custodian_employee_id',
        'to_custodian_employee_id',
        'from_branch_id',
        'to_branch_id',
        'reason',
        'actor_id',
        'at',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'at' => 'datetime',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'tool_id');
    }

    public function fromCustodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_custodian_employee_id');
    }

    public function toCustodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_custodian_employee_id');
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
