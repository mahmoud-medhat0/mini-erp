<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class FinancialStatementLine extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'financial_statement_line';

    public array $translatable = ['name'];

    protected $fillable = [
        'code',
        'statement_type',
        'section_code',
        'name',
        'normal_balance',
        'sort_order',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'financial_statement_line_id');
    }
}
