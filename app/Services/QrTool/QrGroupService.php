<?php

namespace App\Services\QrTool;

use App\Models\QrGroup;
use Illuminate\Support\Str;

/**
 * Auto-creates/reuses a QrGroup from Event Type + Event Name + Role,
 * reproducing the old tool's automatic per-combination Excel files (e.g.
 * `Conference_IEEE_BECITHCON_2026_Role_Session_Chair.xlsx`) as a database
 * concept. The admin never creates a group directly through a form of its
 * own fields — see docs/CERTIFICATE_SYSTEM.md §Simple QR tool: automatic
 * grouping.
 *
 * Deliberately separate from the OPTIONS mechanism
 * (QrConferenceType/QrConferenceOption, and a category field's own
 * `options` JSON for roles): those are selectable dropdown values an admin
 * curates ahead of time. A group is an automatically created combination of
 * whichever values were actually submitted — the two concepts are not
 * interchangeable.
 */
class QrGroupService
{
    private const NO_CONFERENCE = 'No Conference';

    /**
     * Find-or-create the group for this combination. Blank/omitted
     * event type or name (the old tool's "Include conference/event"
     * checkbox left unchecked) normalize to "No Conference" — matching the
     * old tool's own `No_Conference` filename placeholder — rather than
     * leaving the group's identity ambiguous.
     */
    public function resolve(?string $eventType, ?string $eventName, string $role): QrGroup
    {
        $eventType = $this->normalizeDisplay($eventType) ?: self::NO_CONFERENCE;
        $eventName = $this->normalizeDisplay($eventName) ?: self::NO_CONFERENCE;
        $role = $this->normalizeDisplay($role);

        $key = $this->groupKey($eventType, $eventName, $role);

        return QrGroup::query()->firstOrCreate(
            ['group_key' => $key],
            ['event_type' => $eventType, 'event_name' => $eventName, 'role' => $role, 'is_active' => true]
        );
    }

    /**
     * The uniqueness key: trimmed, whitespace-collapsed, lowercased, joined
     * with a control character that can never appear in normal input --
     * safer than a plain delimiter like "|" which a conference name could
     * theoretically contain.
     */
    public function groupKey(string $eventType, string $eventName, string $role): string
    {
        return implode("\x1F", [
            Str::lower($this->normalizeDisplay($eventType) ?: self::NO_CONFERENCE),
            Str::lower($this->normalizeDisplay($eventName) ?: self::NO_CONFERENCE),
            Str::lower($this->normalizeDisplay($role)),
        ]);
    }

    public function label(QrGroup $group): string
    {
        return "{$group->event_type} / {$group->event_name} / {$group->role}";
    }

    /**
     * Reproduces the old tool's `get_excel_file()` filename convention
     * exactly (inspected from IEEEQRCODEGENERATOR-main/app.py's
     * `filename_part()`): non-alphanumeric runs become a single underscore,
     * leading/trailing underscores trimmed, capped at 70 chars, "Unnamed"
     * if that leaves nothing.
     */
    public function exportFilename(QrGroup $group): string
    {
        $part = fn (string $value) => $this->filenamePart($value);

        return "{$part($group->event_type)}_{$part($group->event_name)}_Role_{$part($group->role)}.xlsx";
    }

    private function filenamePart(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]+/', '_', $value);
        $clean = trim($clean, '_');
        $clean = substr($clean, 0, 70);

        return $clean !== '' ? $clean : 'Unnamed';
    }

    private function normalizeDisplay(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }
}
