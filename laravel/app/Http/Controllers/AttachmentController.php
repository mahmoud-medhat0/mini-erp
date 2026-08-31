<?php

namespace App\Http\Controllers;

use App\Application\Attachments\AttachmentService;
use App\Http\Requests\Attachments\ListAttachmentRequest;
use App\Http\Requests\Attachments\StoreAttachmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(ListAttachmentRequest $request, AttachmentService $service): JsonResponse
    {
        $validated = $request->validated();

        $attachments = $service->listForEntity(
            entityType: $validated['entity_type'],
            entityId: $validated['entity_id'],
            user: $request->user(),
        );

        return response()->json(['attachments' => $attachments]);
    }

    public function store(StoreAttachmentRequest $request, AttachmentService $service): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        $attachment = $service->upload(
            file: $validated['file'],
            entityType: $validated['entity_type'],
            entityId: $validated['entity_id'],
            user: $request->user(),
            displayName: $validated['name'] ?? null,
        );

        if ($request->wantsJson()) {
            return response()->json(['attachment' => $attachment], 201);
        }

        return back()->with('success', __('Attachment uploaded.'));
    }

    public function show(Request $request, string $id, AttachmentService $service): StreamedResponse
    {
        return $service->download($id, $request->user());
    }

    public function destroy(Request $request, string $id, AttachmentService $service): JsonResponse|RedirectResponse
    {
        $service->delete($id, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['message' => __('Attachment deleted successfully.')]);
        }

        return back()->with('success', __('Attachment deleted.'));
    }
}
