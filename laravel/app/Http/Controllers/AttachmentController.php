<?php

namespace App\Http\Controllers;

use App\Application\Attachments\AttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, AttachmentService $service): RedirectResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'max:100', Rule::in(array_keys(config('erp_attachments.entities', [])))],
            'entity_id' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $service->upload(
            file: $validated['file'],
            entityType: $validated['entity_type'],
            entityId: $validated['entity_id'],
            user: $request->user(),
        );

        return back()->with('success', __('Attachment uploaded.'));
    }

    public function show(Request $request, string $id, AttachmentService $service): StreamedResponse
    {
        return $service->download($id, $request->user());
    }
}
