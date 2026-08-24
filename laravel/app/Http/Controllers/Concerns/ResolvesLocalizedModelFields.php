<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Throwable;

trait ResolvesLocalizedModelFields
{
    protected function locale(Request $request): string
    {
        return $request->user()?->locale === 'ar' || app()->getLocale() === 'ar' ? 'ar' : 'en';
    }

    protected function modelTranslation(object $model, string $field, string $locale): string
    {
        if (method_exists($model, 'getTranslation')) {
            try {
                $value = $model->getTranslation($field, $locale, false);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            } catch (Throwable) {
                //
            }
        }

        return $this->translationFromJson($model->{$field} ?? null, $locale);
    }

    protected function translationFromJson(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            return (string) ($value[$locale] ?? $value['en'] ?? reset($value) ?: '');
        }

        if (! is_string($value) || $value === '') {
            return '';
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return (string) ($decoded[$locale] ?? $decoded['en'] ?? reset($decoded) ?: '');
        }

        return $value;
    }
}
