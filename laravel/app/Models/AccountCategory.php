<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class AccountCategory extends Model
{
    use HasTranslations, HasUuids;

    protected $table = 'account_category';

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    protected $fillable = [
        'code',
        'name',
        'normal_balance',
        'statement_type',
        'is_contra',
        'sort_order',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'is_contra' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return HasMany<AccountType, $this>
     */
    public function accountTypes(): HasMany
    {
        return $this->hasMany(AccountType::class, 'account_category_id');
    }
}
