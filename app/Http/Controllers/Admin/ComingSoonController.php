<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Honest placeholder pages for admin nav sections that don't have real
 * functionality yet (see docs/CHANGELOG.md for which phase builds each one).
 * Kept as one controller/view rather than several near-identical ones.
 * Templates moved to a real Admin\TemplateController in Phase 3.
 */
class ComingSoonController extends Controller
{
    public function certificates(): View
    {
        return $this->comingSoon('Certificates', 'Phase 5', 'Generate a single certificate from a template and download it.');
    }

    public function bulkGeneration(): View
    {
        return $this->comingSoon('Bulk Generation', 'Phase 7', 'Download an Excel template, fill it, and generate many certificates at once.');
    }

    public function batches(): View
    {
        return $this->comingSoon('Batches', 'Phase 7', 'Track the status of bulk-generation runs and download their results.');
    }

    private function comingSoon(string $title, string $phase, string $description): View
    {
        return view('admin.coming-soon', compact('title', 'phase', 'description'));
    }
}
