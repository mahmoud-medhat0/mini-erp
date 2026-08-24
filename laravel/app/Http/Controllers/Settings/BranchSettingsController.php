<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditLogger;
use App\Http\Controllers\Concerns\AuthorizesSettingsManagement;
use App\Http\Controllers\Concerns\ResolvesLocalizedModelFields;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\Concurrency\OptimisticLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchSettingsController extends Controller
{
    use AuthorizesSettingsManagement;
    use ResolvesLocalizedModelFields;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OptimisticLock $optimisticLock,
    ) {}

    public function index(Request $request): Response
    {
        $locale = $this->locale($request);

        return Inertia::render('Settings/Branches', [
            'branches' => Branch::query()
                ->orderBy('code')
                ->get()
                ->map(fn (Branch $branch): array => [
                    'id' => $branch->id,
                    'code' => $branch->code,
                    'name' => $this->modelTranslation($branch, 'name', $locale),
                    'nameEn' => $this->modelTranslation($branch, 'name', 'en'),
                    'nameAr' => $this->modelTranslation($branch, 'name', 'ar'),
                    'isActive' => $branch->is_active,
                    'lockVersion' => (int) $branch->lock_version,
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:branch,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable'],
        ]);

        $id = (string) Str::uuid();

        DB::table('branch')->insert([
            'id' => $id,
            'code' => $validated['code'],
            'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
            'is_active' => $request->boolean('is_active', true),
            'lock_version' => 0,
        ]);

        $this->auditLogger->record($request->user()->id, 'branch.create', 'branch', $id, after: $validated);

        return back()->with('success', __('Branch saved.'));
    }

    public function update(Request $request, string $branchId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('branch', 'code')->ignore($branchId)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ]);

        $before = (array) DB::table('branch')->where('id', $branchId)->first();

        abort_if($before === [], 404);

        $payload = [
            'code' => $validated['code'],
            'name' => json_encode(['en' => $validated['name_en'], 'ar' => $validated['name_ar']], JSON_THROW_ON_ERROR),
            'is_active' => $request->boolean('is_active'),
        ];

        if (isset($validated['lock_version'])) {
            $this->optimisticLock->update('branch', ['id' => $branchId], (int) $validated['lock_version'], $payload);
        } else {
            DB::table('branch')->where('id', $branchId)->update($payload);
        }

        $this->auditLogger->record($request->user()->id, 'branch.update', 'branch', $branchId, before: $before, after: $validated);

        return back()->with('success', __('Branch saved.'));
    }

    public function destroy(Request $request, string $branchId): RedirectResponse
    {
        $this->authorizeManagement($request, 'settings.branches');

        $branch = DB::table('branch')->where('id', $branchId)->first();
        abort_if(! $branch, 404);

        $before = (array) $branch;

        DB::table('branch')->where('id', $branchId)->delete();

        $this->auditLogger->record($request->user()->id, 'branch.delete', 'branch', $branchId, before: $before);

        return back()->with('success', __('Branch deleted successfully.'));
    }
}
