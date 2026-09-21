<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class RecurringTemplate extends Model
{
    use HasFactory, HasTranslations, HasUuids;

    protected $table = 'recurring_template';

    protected $fillable = [
        'code',
        'name',
        'document_type',
        'frequency',
        'interval_count',
        'start_date',
        'end_date',
        'next_run_date',
        'last_generated_date',
        'status',
        'template_payload',
        'generated_count',
        'last_error',
        'lock_version',
        'created_by',
        'updated_by',
    ];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'interval_count' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'next_run_date' => 'date:Y-m-d',
            'last_generated_date' => 'date:Y-m-d',
            'template_payload' => 'array',
            'generated_count' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'recurring_template_id');
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
