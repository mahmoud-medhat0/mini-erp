<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Supplier extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'supplier';

    protected $fillable = [
        'code',
        'name',
        'status',
        'email',
        'phone',
        'address',
        'tax_number',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'lock_version' => 'integer',
        ];
    }

    public function payableAllocations(): HasMany
    {
        return $this->hasMany(PayableAllocation::class, 'supplier_id');
    }

    public function outgoingCheques(): HasMany
    {
        return $this->hasMany(OutgoingCheque::class, 'supplier_id');
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
