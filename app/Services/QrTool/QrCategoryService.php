<?php

namespace App\Services\QrTool;

use App\Models\QrCategory;
use Illuminate\Support\Str;

/**
 * Deliberately much smaller than App\Services\Certificates\Templates\
 * TemplateService -- no draft/active/archived lifecycle, no activation
 * validation gate. `is_active` is a plain boolean toggle; a category with
 * zero fields or no recipient-name field simply can't be used to generate
 * yet (checked at generation time, not as a separate "activation" concept).
 */
class QrCategoryService
{
    /** A unique, URL-safe slug derived from $name, appending -2, -3, ... on collision. */
    public function generateUniqueSlug(string $name, ?int $ignoreCategoryId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (
            QrCategory::query()
                ->where('slug', $slug)
                ->when($ignoreCategoryId, fn ($query) => $query->where('id', '!=', $ignoreCategoryId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
