<?php

namespace App\Services\Forms;

use Illuminate\Validation\Rule;

/**
 * Behavioral form settings (forms.settings JSON): display title, submit
 * text, success message/redirect, submission policy and schedule.
 * `schema_version` is stamped server-side on every save so a future change
 * to this JSON shape can be migrated deliberately instead of guessed at.
 */
final class FormSettingsSchema
{
    public const SCHEMA_VERSION = 1;

    public const DATETIME_FORMAT = 'Y-m-d\TH:i';

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'title' => null,
            'submit_label' => 'Submit',
            'success_message' => 'Thank you! Your response has been recorded.',
            'redirect_url' => null,
            'allow_multiple_submissions' => true,
            'visibility' => 'public',
            'submission_limit' => null,
            'opens_at' => null,
            'closes_at' => null,
            'schema_version' => self::SCHEMA_VERSION,
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function rules(string $prefix = 'settings'): array
    {
        return [
            $prefix => ['nullable', 'array'],
            "{$prefix}.title" => ['nullable', 'string', 'max:200'],
            "{$prefix}.submit_label" => ['nullable', 'string', 'max:80'],
            "{$prefix}.success_message" => ['nullable', 'string', 'max:2000'],
            // http(s) only -- never javascript:/data: etc.
            "{$prefix}.redirect_url" => ['nullable', 'string', 'max:2000', 'url:http,https'],
            "{$prefix}.allow_multiple_submissions" => ['nullable', 'boolean'],
            "{$prefix}.visibility" => ['nullable', Rule::in(['public', 'private'])],
            "{$prefix}.submission_limit" => ['nullable', 'integer', 'min:1', 'max:1000000'],
            "{$prefix}.opens_at" => ['nullable', 'date_format:'.self::DATETIME_FORMAT],
            // "closes after opens" is checked in FormDefinitionValidator::after()
            // -- Laravel's after:<field> fails outright when that field is empty.
            "{$prefix}.closes_at" => ['nullable', 'date_format:'.self::DATETIME_FORMAT],
        ];
    }

    /** Whitelisted, typed copy of already-validated input. @return array<string, mixed> */
    public static function normalize(?array $settings): array
    {
        $settings ??= [];
        $defaults = self::defaults();
        $blankToNull = fn ($value) => is_string($value) && trim($value) === '' ? null : $value;

        return [
            'title' => $blankToNull($settings['title'] ?? null),
            'submit_label' => $blankToNull($settings['submit_label'] ?? null) ?? $defaults['submit_label'],
            'success_message' => $blankToNull($settings['success_message'] ?? null) ?? $defaults['success_message'],
            'redirect_url' => $blankToNull($settings['redirect_url'] ?? null),
            'allow_multiple_submissions' => filter_var($settings['allow_multiple_submissions'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'visibility' => ($settings['visibility'] ?? 'public') === 'private' ? 'private' : 'public',
            'submission_limit' => isset($settings['submission_limit']) && $settings['submission_limit'] !== '' ? (int) $settings['submission_limit'] : null,
            'opens_at' => $blankToNull($settings['opens_at'] ?? null),
            'closes_at' => $blankToNull($settings['closes_at'] ?? null),
            'schema_version' => self::SCHEMA_VERSION,
        ];
    }
}
