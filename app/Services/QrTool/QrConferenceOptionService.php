<?php

namespace App\Services\QrTool;

use App\Models\QrConferenceOption;
use App\Models\QrConferenceType;
use Illuminate\Support\Facades\DB;

/**
 * Persists what the old tool's "Add type option" / "Add conference name"
 * buttons kept only in the browser's `localStorage` — see
 * docs/CERTIFICATE_SYSTEM.md §Simple QR tool: old-tool-parity rebuild.
 * Global lists (not per-category), matching the old tool having exactly
 * one such pair of lists.
 */
class QrConferenceOptionService
{
    public function addType(string $name): QrConferenceType
    {
        $name = trim($name);

        return QrConferenceType::query()->firstOrCreate(
            ['name' => $name],
        );
    }

    /**
     * Removing a type for FUTURE generation only — never touches an
     * already-created `qr_certificates` row (it stores the resolved
     * conference name as a plain string, no FK). Cascade-deletes that
     * type's own name options, matching the old tool's behavior of
     * dropping the whole type + its names together.
     */
    public function removeType(QrConferenceType $type): void
    {
        DB::transaction(function () use ($type) {
            $type->options()->delete();
            $type->delete();
        });
    }

    public function addOption(QrConferenceType $type, string $name): QrConferenceOption
    {
        $name = trim($name);

        return QrConferenceOption::query()->firstOrCreate(
            ['qr_conference_type_id' => $type->id, 'name' => $name],
        );
    }

    public function removeOption(QrConferenceOption $option): void
    {
        $option->delete();
    }
}
