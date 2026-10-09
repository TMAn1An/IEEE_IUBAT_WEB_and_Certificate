<?php

namespace Tests\Concerns;

use App\Models\Form;
use App\Models\User;
use App\Services\Forms\Style\FormStyleSchema;
use Illuminate\Testing\TestResponse;

/**
 * Builds forms the way the real builder does: POST /admin/forms, then a
 * JSON PUT of the full definition to /admin/forms/{form}/builder. Tests
 * therefore exercise the same validation/normalization path as the UI.
 */
trait BuildsForms
{
    /** @param  array<string, mixed>  $extra */
    protected function fieldDef(string $type, string $key, string $label, array $extra = []): array
    {
        return array_replace_recursive([
            'id' => null,
            'type' => $type,
            'label' => $label,
            'key' => $key,
            'required' => false,
            'is_active' => true,
            'settings' => [],
            'style_settings' => [],
            'conditional_rules' => null,
        ], $extra);
    }

    /** @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $overrides */
    protected function definitionPayload(Form $form, array $fields, array $overrides = []): array
    {
        return array_replace([
            'version' => $form->fresh()->lock_version,
            'intent' => 'save',
            'name' => $form->name,
            'slug' => $form->slug,
            'description' => $form->description,
            'settings' => $form->settings ?? [],
            'style_settings' => FormStyleSchema::defaults(),
            'fields' => $fields,
        ], $overrides);
    }

    /** @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $overrides */
    protected function saveDefinition(User $user, Form $form, array $fields, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)->putJson("/admin/forms/{$form->id}/builder", $this->definitionPayload($form, $fields, $overrides));
    }

    protected function createFormVia(User $user, string $name = 'Volunteer Registration'): Form
    {
        $this->actingAs($user)->post('/admin/forms', ['name' => $name])->assertRedirect();

        return Form::query()->where('name', $name)->latest('id')->firstOrFail();
    }

    /**
     * A published form with the given fields, built through the real endpoints.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $overrides
     */
    protected function publishedForm(User $user, array $fields, array $overrides = []): Form
    {
        $form = $this->createFormVia($user, $overrides['name'] ?? 'Published Form '.uniqid());
        $this->saveDefinition($user, $form, $fields, ['intent' => 'publish'] + $overrides)->assertOk();
        auth()->logout();

        return $form->fresh();
    }

    /** Current builder state of a saved form, as the builder would reload it. */
    protected function fieldsAsPayload(Form $form): array
    {
        return $form->fresh()->fields()->get()->map(fn ($f) => [
            'id' => $f->id,
            'type' => $f->type->value,
            'label' => $f->label,
            'key' => $f->key,
            'required' => $f->required,
            'is_active' => $f->is_active,
            'settings' => $f->settings ?? [],
            'style_settings' => $f->style_settings ?? [],
            'conditional_rules' => $f->conditional_rules,
        ])->all();
    }
}
