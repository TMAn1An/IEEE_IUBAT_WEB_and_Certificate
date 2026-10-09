<?php

namespace App\Models;

use App\Enums\FormStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A general-purpose dynamic form. Independent of the certificate/QR
 * systems. Every write goes through App\Services\Forms\FormBuilderService
 * (validated + whitelisted by FormDefinitionValidator first) -- never
 * straight from request input. See docs/FORM_BUILDER.md.
 */
class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'settings',
        'style_settings',
        'custom_css',
        'custom_html_before',
        'custom_html_after',
        'custom_js',
        'lock_version',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FormStatus::class,
            'settings' => 'array',
            'style_settings' => 'array',
            'lock_version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** Every field including archived ones, in builder order. @return HasMany<FormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<FormField, $this> */
    public function activeFields(): HasMany
    {
        return $this->fields()->where('is_active', true);
    }

    /** @return HasMany<FormSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param  Builder<Form>  $query */
    public function scopeNotArchived(Builder $query): void
    {
        $query->where('status', '!=', FormStatus::Archived);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /** The HTML id every scoped style/custom CSS rule for this form hangs off. */
    public function wrapperId(): string
    {
        return 'ff-form-'.$this->id;
    }

    /** Public display title (falls back to the internal name). */
    public function title(): string
    {
        $title = trim((string) $this->setting('title', ''));

        return $title !== '' ? $title : $this->name;
    }
}
