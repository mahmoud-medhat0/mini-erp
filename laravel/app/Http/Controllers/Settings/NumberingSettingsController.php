<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NumberingSettingsController extends Controller
{
    use AuthorizesSettingsManagement;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Settings/Numbering', [
            'sequences' => DB::table('number_sequence')
                ->select([
                    'number_sequence.id',
                    'number_sequence.key',
                    'number_sequence.doc_type',
                    'number_sequence.prefix',
                    'number_sequence.include_year',
                    'number_sequence.padding',
                    'number_sequence.reset_policy',
                    'number_sequence.next_value',
                ])
                ->orderBy('number_sequence.doc_type')
                ->get()
                ->map(fn (object $sequence): array => [
                    'id' => $sequence->id,
                    'key' => $sequence->key,
                    'docType' => $sequence->doc_type,
                    'prefix' => $sequence->prefix,
                    'includeYear' => (bool) $sequence->include_year,
                    'padding' => (int) $sequence->padding,
                    'resetPolicy' => $sequence->reset_policy,
                    'nextValue' => (int) $sequence->next_value,
                    'preview' => $this->previewNumber($sequence),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.numbering');

        $validated = $this->validateNumbering($request);
        $id = (string) Str::uuid();

        $exists = DB::table('number_sequence')
            ->where('key', $validated['key'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['key' => __('The numbering key already exists.')]);
        }

        DB::table('number_sequence')->insert([
            'id' => $id,
            ...$this->numberingPayload($validated),
        ]);

        $this->auditLogger->record($request->user()->id, 'number_sequence.create', 'number_sequence', $id, after: $validated);

        return back()->with('success', __('Numbering saved.'));
    }

    public function update(Request $request, string $sequenceId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.numbering');

        $validated = $this->validateNumbering($request);

        return DB::transaction(function () use ($request, $sequenceId, $validated): RedirectResponse {
            $sequence = DB::table('number_sequence')
                ->where('id', $sequenceId)
                ->lockForUpdate()
                ->first();

            abort_if(! $sequence, 404);

            $before = (array) $sequence;

            if ($validated['key'] !== $sequence->key) {
                return back()->withErrors(['key' => __('Numbering keys cannot be changed after creation.')]);
            }

            if ((int) $validated['next_value'] < (int) $sequence->next_value) {
                return back()->withErrors(['next_value' => __('Cannot reduce next number below current sequence state.')]);
            }

            DB::table('number_sequence')
                ->where('id', $sequenceId)
                ->update($this->numberingPayload($validated));

            $this->auditLogger->record($request->user()->id, 'number_sequence.update', 'number_sequence', $sequenceId, before: $before, after: $validated);

            return back()->with('success', __('Numbering saved.'));
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validateNumbering(Request $request): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'doc_type' => ['required', 'string', 'max:100'],
            'prefix' => ['required', 'string', 'max:20'],
            'include_year' => ['nullable'],
            'padding' => ['required', 'integer', 'min:1', 'max:12'],
            'reset_policy' => ['required', 'string', Rule::in(['never', 'yearly', 'monthly'])],
            'next_value' => ['required', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function numberingPayload(array $validated): array
    {
        return [
            'key' => $validated['key'],
            'doc_type' => $validated['doc_type'],
            'prefix' => $validated['prefix'],
            'include_year' => request()->boolean('include_year'),
            'padding' => (int) $validated['padding'],
            'reset_policy' => $validated['reset_policy'],
            'next_value' => (int) $validated['next_value'],
        ];
    }

    private function previewNumber(object $sequence): string
    {
        $parts = array_filter([
            (string) $sequence->prefix,
            (bool) $sequence->include_year ? now()->year : null,
            str_pad((string) max(1, (int) $sequence->next_value), (int) $sequence->padding, '0', STR_PAD_LEFT),
        ], static fn ($part): bool => $part !== null && $part !== '');

        return implode('-', $parts);
    }
}
