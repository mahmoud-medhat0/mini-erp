<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Tool extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'tool';

    protected $fillable = [
        'code',
        'name',
        'description',
        'tool_category_id',
        'serial_number',
        'quantity',
        'status',
        'branch_id',
        'custodian_employee_id',
        'location_note',
        'notes',
        'is_active',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name', 'description'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class, 'tool_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'custodian_employee_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ToolMovement::class, 'tool_id')->latest('at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
