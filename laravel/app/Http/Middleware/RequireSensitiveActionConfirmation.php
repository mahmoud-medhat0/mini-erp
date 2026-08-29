<?php

namespace App\Http\Middleware;

use App\Support\Security\SensitiveActionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class RequireSensitiveActionConfirmation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! $routeName || ! SensitiveActionRegistry::has($routeName)) {
            return $next($request);
        }

        $config = SensitiveActionRegistry::get($routeName);
        $expectedCode = $config['confirmation_code'];
        $reasonRequired = $config['reason_required'];

        $rules = [
            'confirm_action' => ['required', 'string', 'in:'.$expectedCode],
        ];

        if ($reasonRequired) {
            $rules['reason'] = ['required', 'string', 'min:3', 'max:1000'];
        } else {
            $rules['reason'] = ['nullable', 'string', 'max:1000'];
        }

        $messages = [
            'confirm_action.required' => __('Confirmation action code is required for this sensitive operation.'),
            'confirm_action.in' => __('Invalid confirmation action code provided.'),
            'reason.required' => __('A justification reason of at least 3 characters is required for this operation.'),
            'reason.min' => __('A justification reason of at least 3 characters is required for this operation.'),
            'reason.max' => __('The justification reason must not exceed 1000 characters.'),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $rawReason = $request->input('reason');
        $reason = is_string($rawReason) ? trim($rawReason) : null;

        if ($reason === '') {
            $reason = null;
        }

        if ($reasonRequired && ($reason === null || mb_strlen($reason) < 3)) {
            $validator->errors()->add(
                'reason',
                __('A justification reason of at least 3 characters is required for this operation.')
            );

            throw new ValidationException($validator);
        }

        $request->attributes->set('sensitive_action_code', $expectedCode);
        $request->attributes->set('sensitive_action_reason', $reason);

        $user = $request->user();
        $activity = activity('default')
            ->event('sensitive_action.confirmed')
            ->withProperties([
                'sensitive_action_code' => $expectedCode,
                'sensitive_action_confirmed' => true,
                'sensitive_action_reason' => $reason,
                'route_name' => $routeName,
                'actor_id' => $user?->id,
                'request_id' => $request->header('X-Request-ID') ?? (string) Str::uuid(),
                'ip' => $request->ip(),
                'device' => $request->userAgent(),
            ]);

        if ($user) {
            $activity->causedBy($user);
        } else {
            $activity->causedByAnonymous();
        }

        $activity->log('sensitive_action.confirmed');

        return $next($request);
    }
}
