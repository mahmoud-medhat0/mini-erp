<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class BankAccount extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'bank_account';

    protected $fillable = [
        'code',
        'name',
        'bank_name',
        'branch_id',
        'account_number',
        'iban',
        'swift',
        'gl_account_id',
        'currency',
        'is_active',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    public array $translatable = ['name', 'bank_name'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function currencyRef(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
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
