<?php

namespace Database\Seeders;

use App\Models\QrCategory;
use App\Models\QrCategoryField;
use App\Models\QrConferenceType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Recreates the ACTUAL old tool's form -- inspected directly from
 * IEEEQRCODEGENERATOR-main/app.py and templates/index.html, not assumed.
 * See docs/CERTIFICATE_SYSTEM.md §Simple QR tool for the full inspection
 * notes this is based on.
 *
 * Real findings that shaped this seeder:
 * - The old tool has exactly ONE form/category (BECITHCON 2026), not
 *   several. CONFERENCE_OPTIONS = ["IEEE BECITHCON 2026"] (under type
 *   "Conference"), EVENT_OPTIONS = ["BECITHCON 2026"] (under type "Event").
 * - PRESET_ROLES = ["Session Chair", "Invited Speaker", "Keynote Speaker",
 *   "Volunteer"] -- there is NO "Other" option and no custom-role text
 *   field anywhere in the real code, despite that being a plausible-looking
 *   example in an earlier planning draft. Not implemented here because it
 *   doesn't reflect the actual tool.
 * - Conference and Session are both optional per-submission (checkboxes in
 *   the old UI, preserved here as-is: an optional Session field, and a
 *   conference type+name pair chosen live from `qr_conference_types`/
 *   `qr_conference_options` rather than a fixed category default).
 *
 * Runs in every environment (not gated to local/testing like
 * AdminUserSeeder) -- unlike dev admin credentials, this is real reference
 * data the tool needs to be usable at all, matching the brief's explicit
 * requirement that a fresh install ships with the real old-tool category
 * already available.
 */
class QrCategorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->orderBy('id')->first();
        if ($admin === null) {
            $this->command?->warn('QrCategorySeeder skipped — no user exists yet to own the category. Re-run after creating the first admin (php artisan app:make-admin).');

            return;
        }

        $category = QrCategory::query()->updateOrCreate(
            ['slug' => 'becithcon-2026'],
            [
                'name' => 'BECITHCON 2026',
                'event_name' => 'IEEE BECITHCON 2026',
                'description' => 'Matches the original IEEEQRCODEGENERATOR-main tool\'s form exactly — see docs/CERTIFICATE_SYSTEM.md §Simple QR tool.',
                'is_active' => true,
                'created_by' => $admin->id,
            ]
        );

        QrCategoryField::query()->updateOrCreate(
            ['qr_category_id' => $category->id, 'key' => 'recipient_name'],
            [
                'label' => 'Name',
                'type' => 'text',
                'required' => true,
                'sort_order' => 1,
                'is_recipient_name' => true,
                'show_on_verification' => true,
            ]
        );

        QrCategoryField::query()->updateOrCreate(
            ['qr_category_id' => $category->id, 'key' => 'role'],
            [
                'label' => 'Role',
                'type' => 'dropdown',
                'required' => true,
                // Exact PRESET_ROLES from app.py -- no "Other" option (see
                // this class's docblock for why).
                'options' => ['Session Chair', 'Invited Speaker', 'Keynote Speaker', 'Volunteer'],
                'sort_order' => 2,
                'is_recipient_name' => false,
                'show_on_verification' => true,
            ]
        );

        QrCategoryField::query()->updateOrCreate(
            ['qr_category_id' => $category->id, 'key' => 'session'],
            [
                'label' => 'Session',
                'type' => 'long_text',
                'required' => false, // optional in the old tool (the "Include session" checkbox)
                'sort_order' => 3,
                'is_recipient_name' => false,
                'show_on_verification' => true,
            ]
        );

        // The real defaultConferenceTypes/CONFERENCE_OPTIONS/EVENT_OPTIONS
        // from the old tool's index.html + app.py.
        $conferenceType = QrConferenceType::query()->firstOrCreate(['name' => 'Conference']);
        $conferenceType->options()->firstOrCreate(['name' => 'IEEE BECITHCON 2026']);

        $eventType = QrConferenceType::query()->firstOrCreate(['name' => 'Event']);
        $eventType->options()->firstOrCreate(['name' => 'BECITHCON 2026']);

        $this->command?->info('Seeded the BECITHCON 2026 QR category (matching the real old tool).');
    }
}
