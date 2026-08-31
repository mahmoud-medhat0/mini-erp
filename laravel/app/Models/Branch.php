<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

#[Fillable(['id', 'code', 'name', 'is_active'])]
#[Translatable('name')]
class Branch extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'branch';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function rentableItems(): HasMany
    {
        return $this->hasMany(RentableItem::class, 'branch_id');
    }

    public function rentalContracts(): HasMany
    {
        return $this->hasMany(RentalContract::class, 'branch_id');
    }

    public function rentalInvoices(): HasMany
    {
        return $this->hasMany(RentalInvoice::class, 'branch_id');
    }
}
