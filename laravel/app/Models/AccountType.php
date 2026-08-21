<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class AccountType extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'account_type';

    protected $fillable = [
        'account_category_id',
        'code',
        'name',
        'normal_balance',
        'statement_type',
        'category',
        'is_contra',
        'sort_order',
        'is_system',
        'is_active',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'is_contra' => 'boolean',
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function accountCategory(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(AccountGroup::class, 'account_type_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'account_type_id');
    }
}
