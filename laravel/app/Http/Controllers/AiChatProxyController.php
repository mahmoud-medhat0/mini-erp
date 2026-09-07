<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiChatProxyController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        abort_unless(config('services.mini_erp_ai.enabled', false), 404);

        $validated = $request->validate([
            'question' => ['nullable', 'string', 'max:4000', 'required_without:image_base64'],
            'history' => ['sometimes', 'array', 'max:8'],
            'history.*' => ['array:role,content'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:4000'],
            'is_voice' => ['sometimes', 'boolean'],
            'image_base64' => ['nullable', 'string', 'max:7000000', 'required_without:question'],
        ]);

        if (($validated['is_voice'] ?? false) && ! (bool) config('services.mini_erp_ai.voice_enabled', false)) {
            throw ValidationException::withMessages([
                'is_voice' => __('Voice input is disabled.'),
            ]);
        }

        if (
            isset($validated['image_base64'])
            && ! (bool) config('services.mini_erp_ai.vision_enabled', true)
        ) {
            throw ValidationException::withMessages([
                'image_base64' => __('Image input is disabled.'),
            ]);
        }

        $serviceUrl = rtrim(trim((string) config('services.mini_erp_ai.service_url')), '/');
        $secret = trim((string) config('services.mini_erp_ai.secret'));
        $requireSecret = (bool) config('services.mini_erp_ai.require_secret', true);

        if (! $this->validServiceUrl($serviceUrl) || ($requireSecret && $secret === '')) {
            Log::error('Mini ERP AI service configuration is incomplete.');

            return response()->json([
                'detail' => __('The AI assistant is not configured.'),
            ], 503);
        }

        $requestId = (string) Str::uuid();
        $validated['tenant_id'] = $this->contextId((string) $request->user()->getAuthIdentifier());

        $timeout = max(10, min((int) config('services.mini_erp_ai.timeout_seconds', 90), 120));
        $headers = ['X-Request-ID' => $requestId];
        if ($secret !== '') {
            $headers['X-App-Secret'] = $secret;
        }

        try {
            $upstream = Http::acceptJson()
                ->asJson()
                ->withHeaders($headers)
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(min(10, $timeout))
                ->timeout($timeout)
                ->post($serviceUrl.'/chat', $validated);
        } catch (ConnectionException $exception) {
            Log::warning('Mini ERP AI service connection failed.', [
                'request_id' => $requestId,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'detail' => __('The AI assistant is temporarily unavailable.'),
                'request_id' => $requestId,
            ], 502);
        }

        if (! $upstream->successful()) {
            Log::warning('Mini ERP AI service returned an error.', [
                'request_id' => $requestId,
                'status' => $upstream->status(),
            ]);

            return response()->json([
                'detail' => $this->upstreamErrorMessage($upstream->status()),
                'request_id' => $requestId,
            ], $this->upstreamStatus($upstream->status()));
        }

        $payload = $upstream->json();
        if (! is_array($payload) || ! isset($payload['answer']) || ! is_string($payload['answer'])) {
            Log::warning('Mini ERP AI service returned an invalid response.', [
                'request_id' => $requestId,
            ]);

            return response()->json([
                'detail' => __('The AI assistant returned an invalid response.'),
                'request_id' => $requestId,
            ], 502);
        }

        $images = array_values(array_filter(
            array_slice(is_array($payload['images'] ?? null) ? $payload['images'] : [], 0, 10),
            static fn (mixed $image): bool => is_string($image)
                && preg_match('#^(?:https://|/(?!/)|data:image/(?:png|jpe?g|webp);base64,)#i', $image) === 1
        ));

        return response()->json([
            'answer' => $payload['answer'],
            'images' => $images,
            'speech_text' => is_string($payload['speech_text'] ?? null)
                ? $payload['speech_text']
                : $payload['answer'],
            'request_id' => is_string($payload['request_id'] ?? null)
                ? $payload['request_id']
                : $requestId,
        ]);
    }

    private function validServiceUrl(string $serviceUrl): bool
    {
        if (filter_var($serviceUrl, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($serviceUrl, PHP_URL_SCHEME));

        return $scheme === 'https'
            || ($scheme === 'http' && app()->environment(['local', 'testing']));
    }

    private function contextId(string $userId): string
    {
        $hash = hash_hmac('sha256', $userId, (string) config('app.key'));

        return 'user_'.substr($hash, 0, 48);
    }

    private function upstreamStatus(int $status): int
    {
        return match ($status) {
            422 => 422,
            429 => 429,
            default => 502,
        };
    }

    private function upstreamErrorMessage(int $status): string
    {
        return match ($status) {
            422 => __('The AI assistant could not process the submitted data.'),
            429 => __('The AI assistant request limit has been reached. Please try again shortly.'),
            default => __('The AI assistant is temporarily unavailable.'),
        };
    }
}
