<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditEventType;
use App\Enums\DeletableRecordType;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Deletion\DeletionRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsForms;
use Tests\TestCase;
use TMAn1An\FormBuilder\Enums\FormFieldType;
use TMAn1An\FormBuilder\Enums\FormStatus;
use TMAn1An\FormBuilder\Models\Form;
use TMAn1An\FormBuilder\Models\FormField;
use TMAn1An\FormBuilder\Styling\FormStyleSchema;

/**
 * The admin Form Builder: creating forms, saving the full definition
 * (fields, order, layout, design, custom code, conditions), and the
 * guarantees around it -- unique keys, field ownership, optimistic
 * locking, authorization, duplication, archiving, and audit logging.
 * See docs/FORM_BUILDER.md.
 */
class FormBuilderTest extends TestCase
{
    use BuildsForms, RefreshDatabase;

    public function test_admin_creates_a_draft_form_and_lands_on_the_builder(): void
    {
        $manager = User::factory()->create();

        $response = $this->actingAs($manager)->post('/admin/forms', ['name' => 'Volunteer Registration', 'description' => 'Sign up to help.']);

        $form = Form::firstOrFail();
        $response->assertRedirect("/admin/forms/{$form->id}/edit");
        $this->assertSame('volunteer-registration', $form->slug);
        $this->assertSame(FormStatus::Draft, $form->status);
        $this->assertSame($manager->id, $form->created_by);
        $this->assertSame(0, $form->fields()->count(), 'New forms start empty -- no hardcoded demo fields.');

        $this->get("/admin/forms/{$form->id}/edit")
            ->assertOk()
            ->assertSee('form-builder-data', false)
            ->assertSee('js/form-builder.js', false);
    }

    public function test_fields_options_order_widths_and_settings_persist_after_reload(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', 'full_name', 'Full name', ['required' => true, 'settings' => ['width' => 50, 'placeholder' => 'Your name', 'help_text' => 'As on your ID', 'max_length' => 120]]),
            $this->fieldDef('email', 'email', 'Email', ['required' => true, 'settings' => ['width' => 50]]),
            $this->fieldDef('select', 'department', 'Department', ['settings' => ['width' => 33, 'options' => [
                ['label' => 'CSE', 'value' => 'cse'],
                ['label' => 'EEE', 'value' => 'eee'],
                ['label' => 'Other', 'value' => 'other'],
            ], 'default_value' => 'cse']]),
            $this->fieldDef('heading', 'intro_heading', 'Intro', ['settings' => ['content' => 'About you', 'heading_level' => 'h2', 'text_align' => 'center']]),
            $this->fieldDef('number', 'age', 'Age', ['settings' => ['min' => '16', 'max' => '99', 'width' => 25]]),
        ])->assertOk()->assertJsonPath('state.form.version', 2);

        $fields = $form->fresh()->fields;
        $this->assertSame(['full_name', 'email', 'department', 'intro_heading', 'age'], $fields->pluck('key')->all());
        $this->assertSame([1, 2, 3, 4, 5], $fields->pluck('sort_order')->all());

        $name = $fields[0];
        $this->assertTrue($name->required);
        $this->assertEquals(['width' => 50, 'placeholder' => 'Your name', 'help_text' => 'As on your ID', 'max_length' => 120], $name->settings);

        $department = $fields[2];
        $this->assertSame(FormFieldType::Select, $department->type);
        $this->assertSame(33, $department->setting('width'));
        $this->assertSame([['label' => 'CSE', 'value' => 'cse'], ['label' => 'EEE', 'value' => 'eee'], ['label' => 'Other', 'value' => 'other']], $department->options());
        $this->assertSame('cse', $department->setting('default_value'));

        $this->assertSame(['content' => 'About you', 'heading_level' => 'h2', 'text_align' => 'center'], $fields[3]->settings);

        // Reorder: move "age" to the top; ids are kept, order changes.
        $payload = $this->fieldsAsPayload($form);
        array_unshift($payload, array_pop($payload));
        $this->saveDefinition($manager, $form, $payload)->assertOk();

        $this->assertSame(['age', 'full_name', 'email', 'department', 'intro_heading'], $form->fresh()->fields->pluck('key')->all());
        $this->assertSame(5, FormField::count(), 'Reordering must update rows in place, not recreate them.');

        // The builder reloads exactly what was stored.
        $this->get("/admin/forms/{$form->id}/edit")->assertOk()->assertSee('"key":"department"', false)->assertSee('"label":"Other"', false);
    }

    public function test_builder_order_is_kept_for_fields_options_and_conditions_of_any_type(): void
    {
        // Regression: validated() rebuilds arrays in rule order, which once
        // put an HTML block (extra per-type rules) ahead of earlier fields.
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);
        $options = [['label' => 'Zeta', 'value' => 'z'], ['label' => 'Alpha', 'value' => 'a'], ['label' => 'Mid', 'value' => 'm']];

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', 'first', 'First'),
            $this->fieldDef('radio', 'second', 'Second', ['settings' => ['options' => $options]]),
            $this->fieldDef('divider', 'third', 'Third'),
            $this->fieldDef('text', 'fourth', 'Fourth', ['conditional_rules' => ['action' => 'show', 'match' => 'any', 'conditions' => [
                ['field' => 'second', 'operator' => 'equals', 'value' => 'm'],
                ['field' => 'first', 'operator' => 'is_not_empty', 'value' => ''],
            ]]]),
            $this->fieldDef('html', 'fifth', 'Fifth', ['settings' => ['content' => '<p>x</p>']]),
        ])->assertOk();

        $fields = $form->fresh()->fields;
        $this->assertSame(['first', 'second', 'third', 'fourth', 'fifth'], $fields->pluck('key')->all());
        $this->assertSame(['z', 'a', 'm'], array_column($fields[1]->options(), 'value'));
        $this->assertSame(['second', 'first'], array_column($fields[3]->conditional_rules['conditions'], 'field'));
    }

    public function test_unknown_setting_keys_are_dropped_not_stored(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', 'name', 'Name', ['settings' => ['placeholder' => 'x', 'onclick' => 'alert(1)', 'options' => [['label' => 'a', 'value' => 'a']]]]),
        ])->assertOk();

        $this->assertSame(['placeholder' => 'x'], FormField::firstOrFail()->settings);
    }

    public function test_design_settings_and_colors_persist_and_render_as_css_variables(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $style = FormStyleSchema::defaults();
        $style['form']['background'] = '#fafafa';
        $style['form']['border_radius'] = 20;
        $style['form']['shadow'] = 'lg';
        $style['label']['color'] = '#123456';
        $style['input']['border_color'] = '#abcdef';
        $style['input']['focus_border_color'] = '#ff0000';
        $style['button']['background'] = '#00843d';
        $style['button']['width'] = 'full';

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', 'name', 'Name', ['style_settings' => ['label_color' => '#990000', 'margin_bottom' => 12]]),
        ], ['style_settings' => $style])->assertOk();

        $form->refresh();
        $this->assertSame('#fafafa', $form->style_settings['form']['background']);
        $this->assertSame(20, $form->style_settings['form']['border_radius']);
        $this->assertSame('#123456', $form->style_settings['label']['color']);
        $this->assertSame('full', $form->style_settings['button']['width']);
        $this->assertSame(['label_color' => '#990000', 'margin_bottom' => 12], FormField::firstOrFail()->style_settings);

        $this->get("/admin/forms/{$form->id}/preview")->assertOk()
            ->assertSee('--ff-bg:#fafafa', false)
            ->assertSee('--ff-radius:20px', false)
            ->assertSee('--ff-label-color:#123456', false)
            ->assertSee('--ff-btn-width:100%', false)
            ->assertSee('--ff-shadow:0 12px 32px', false)
            ->assertSee('--ff-label-color:#990000;--ff-field-mb:12px', false);
    }

    public function test_invalid_colors_and_out_of_range_design_values_are_rejected(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $style = FormStyleSchema::defaults();
        $style['form']['background'] = 'red;}body{display:none';
        $style['form']['padding'] = 5000;
        $style['button']['alignment'] = 'url(javascript:alert(1))';

        $this->saveDefinition($manager, $form, [], ['style_settings' => $style])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['style_settings.form.background', 'style_settings.form.padding', 'style_settings.button.alignment']);

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', 'name', 'Name', ['style_settings' => ['label_color' => 'expression(alert(1))']]),
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.0.style_settings.label_color']);
    }

    public function test_field_keys_must_be_unique_and_well_formed(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', 'name', 'Name'),
            $this->fieldDef('email', 'name', 'Email'),
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.1.key']);

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', '1bad-Key', 'Name'),
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.0.key']);

        $this->assertSame(0, FormField::count());
    }

    public function test_duplicate_option_values_and_foreign_default_are_rejected(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('radio', 'pick', 'Pick', ['settings' => [
                'options' => [['label' => 'A', 'value' => 'a'], ['label' => 'A again', 'value' => 'a']],
                'default_value' => 'zzz',
            ]]),
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.0.settings.options', 'fields.0.settings.default_value']);
    }

    public function test_a_field_id_from_another_form_is_rejected(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager, 'Form A');
        $other = $this->createFormVia($manager, 'Form B');
        $this->saveDefinition($manager, $other, [$this->fieldDef('text', 'secret', 'Secret')])->assertOk();
        $foreign = $other->fields()->firstOrFail();

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', 'hijack', 'Hijack', ['id' => $foreign->id]),
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.0.id']);

        $this->assertSame('secret', $foreign->fresh()->key);
        $this->assertSame($other->id, $foreign->fresh()->form_id);
    }

    public function test_stale_version_is_rejected_with_409_instead_of_overwriting(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);
        $this->saveDefinition($manager, $form, [$this->fieldDef('text', 'a', 'A')])->assertOk();

        $this->saveDefinition($manager, $form, [$this->fieldDef('text', 'b', 'B')], ['version' => 1])
            ->assertStatus(409);

        $this->assertSame(['a'], $form->fresh()->fields->pluck('key')->all());
    }

    public function test_html_block_is_sanitized_on_save_and_render(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('html', 'notice', 'Notice', ['settings' => ['content' => '<h3>Important Notice</h3><p onclick="steal()">Please fill this form <strong>carefully</strong>.</p><script>alert(1)</script><a href="javascript:alert(2)">x</a><iframe src="https://evil.test"></iframe>']]),
        ])->assertOk();

        $stored = FormField::firstOrFail()->setting('content');
        $this->assertStringContainsString('<h3>Important Notice</h3>', $stored);
        $this->assertStringContainsString('<strong>carefully</strong>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('<iframe', $stored);

        // Even a row written straight to the DB (bypassing the builder) is sanitized at render time.
        FormField::firstOrFail()->update(['settings' => ['content' => '<p>ok</p><script>alert("db")</script><img src=x onerror=alert(3)>']]);
        $this->get("/admin/forms/{$form->id}/preview")->assertOk()
            ->assertSee('<p>ok</p>', false)
            ->assertDontSee('alert("db")', false)
            ->assertDontSee('onerror', false);
    }

    public function test_custom_css_persists_and_is_scoped_to_the_form(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $form = $this->createFormVia($admin);

        $this->saveDefinition($admin, $form, [$this->fieldDef('text', 'name', 'Name')], [
            'custom_css' => "body { background: red; }\n.ff-label { text-transform: uppercase; }\n@import url(https://evil.test/x.css);\n.x::after { content: '</style><script>alert(1)</script>'; }",
            'custom_html_before' => '<p class="lead">Welcome!</p><script>alert(1)</script>',
            'custom_js' => 'console.log("never runs")',
        ])->assertOk();

        $form->refresh();
        $this->assertStringContainsString('.ff-label { text-transform: uppercase; }', $form->custom_css, 'Stored as typed.');
        $this->assertSame('<p class="lead">Welcome!</p>', $form->custom_html_before);
        $this->assertSame('console.log("never runs")', $form->custom_js);

        $scope = '#ff-form-'.$form->id;
        $this->get("/admin/forms/{$form->id}/preview")->assertOk()
            ->assertSee($scope.'{background: red;}', false)
            ->assertSee($scope.' .ff-label{text-transform: uppercase;}', false)
            ->assertDontSee('@import', false)
            ->assertDontSee('</style><script>', false)
            ->assertSee('<p class="lead">Welcome!</p>', false)
            ->assertDontSee('never runs', false);
    }

    public function test_custom_code_is_restricted_to_super_admin(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        foreach (['custom_css' => '.x{color:red}', 'custom_html_before' => '<p>hi</p>', 'custom_html_after' => '<p>bye</p>', 'custom_js' => 'alert(1)'] as $key => $value) {
            $this->saveDefinition($manager, $form, [], [$key => $value])
                ->assertStatus(422)
                ->assertJsonValidationErrors([$key]);
        }
        $this->assertNull($form->fresh()->custom_css);

        // A manager saving without those keys leaves a super admin's custom code untouched.
        $admin = User::factory()->superAdmin()->create();
        $this->saveDefinition($admin, $form, [], ['custom_css' => '.x{color:red}'])->assertOk();
        $this->saveDefinition($manager, $form, [$this->fieldDef('text', 'name', 'Name')])->assertOk();
        $this->assertSame('.x{color:red}', $form->fresh()->custom_css);

        // ...and the manager's builder never receives the raw code.
        $this->actingAs($manager)->get("/admin/forms/{$form->id}/edit")->assertOk()
            ->assertSee('"custom_css":null', false)
            ->assertSee('"has_custom_code":true', false);
    }

    public function test_conditional_rules_persist_and_references_are_validated(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);
        $options = [['label' => 'CSE', 'value' => 'cse'], ['label' => 'Other', 'value' => 'other']];
        $rule = ['action' => 'show', 'match' => 'all', 'conditions' => [['field' => 'department', 'operator' => 'equals', 'value' => 'other']]];

        $this->saveDefinition($manager, $form, [
            $this->fieldDef('select', 'department', 'Department', ['settings' => ['options' => $options]]),
            $this->fieldDef('text', 'other_department', 'Other department', ['required' => true, 'conditional_rules' => $rule]),
        ])->assertOk();
        $this->assertSame($rule, $form->fresh()->fields[1]->conditional_rules);

        // Unknown key
        $bad = $rule;
        $bad['conditions'][0]['field'] = 'nope';
        $this->saveDefinition($manager, $form, [
            $this->fieldDef('select', 'department', 'Department', ['settings' => ['options' => $options]]),
            $this->fieldDef('text', 'other_department', 'Other', ['conditional_rules' => $bad]),
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.1.conditional_rules.conditions.0.field']);

        // A field placed BELOW (forward reference) is rejected -- no cycles possible.
        $this->saveDefinition($manager, $form, [
            $this->fieldDef('text', 'other_department', 'Other', ['conditional_rules' => $rule]),
            $this->fieldDef('select', 'department', 'Department', ['settings' => ['options' => $options]]),
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.0.conditional_rules.conditions.0.field']);

        // Unknown operator
        $bad = $rule;
        $bad['conditions'][0]['operator'] = 'matches_regex';
        $this->saveDefinition($manager, $form, [
            $this->fieldDef('select', 'department', 'Department', ['settings' => ['options' => $options]]),
            $this->fieldDef('text', 'other_department', 'Other', ['conditional_rules' => $bad]),
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.1.conditional_rules.conditions.0.operator']);
    }

    public function test_publishing_requires_an_input_field_and_is_audited(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->saveDefinition($manager, $form, [$this->fieldDef('heading', 'title', 'Title')], ['intent' => 'publish'])
            ->assertStatus(422)->assertJsonValidationErrors(['fields']);
        $this->assertSame(FormStatus::Draft, $form->fresh()->status);

        $this->saveDefinition($manager, $form, [$this->fieldDef('text', 'name', 'Name')], ['intent' => 'publish'])
            ->assertOk()->assertJsonPath('state.form.status', 'active');

        $form->refresh();
        $this->assertSame(FormStatus::Active, $form->status);
        $this->assertNotNull($form->published_at);

        $events = AuditLog::query()->where('record_type', DeletableRecordType::Form)->where('record_id', $form->id)->pluck('event_type')->all();
        $this->assertContains(AuditEventType::FormCreated, $events);
        $this->assertContains(AuditEventType::FormUpdated, $events);
        $this->assertContains(AuditEventType::FormPublished, $events);
    }

    public function test_autosave_saves_but_does_not_flood_the_logbook(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->saveDefinition($manager, $form, [$this->fieldDef('text', 'name', 'Name')], ['autosave' => true])->assertOk();

        $this->assertSame(1, $form->fields()->count());
        $this->assertSame(0, AuditLog::query()->where('event_type', AuditEventType::FormUpdated)->count());
    }

    public function test_deactivate_archive_and_restore_lifecycle(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $form = $this->publishedForm($manager, [$this->fieldDef('text', 'name', 'Name')]);

        $this->actingAs($manager)->post("/admin/forms/{$form->id}/deactivate")->assertRedirect();
        $this->assertSame(FormStatus::Inactive, $form->fresh()->status);

        // Archiving is super_admin only.
        $this->actingAs($manager)->post("/admin/forms/{$form->id}/archive")->assertForbidden();
        $this->actingAs($admin)->post("/admin/forms/{$form->id}/archive")->assertRedirect('/admin/forms');
        $this->assertSame(FormStatus::Archived, $form->fresh()->status);

        // Archived forms are read-only for everyone.
        $this->saveDefinition($admin, $form->fresh(), [$this->fieldDef('text', 'x', 'X')])->assertForbidden();
        $this->actingAs($admin)->get("/admin/forms/{$form->id}/edit")->assertOk();

        $this->actingAs($admin)->post("/admin/forms/{$form->id}/restore")->assertRedirect();
        $this->assertSame(FormStatus::Inactive, $form->fresh()->status);

        $events = AuditLog::query()->where('record_id', $form->id)->where('record_type', DeletableRecordType::Form)->pluck('event_type');
        $this->assertTrue($events->contains(AuditEventType::FormDeactivated));
        $this->assertTrue($events->contains(AuditEventType::FormArchived));
        $this->assertTrue($events->contains(AuditEventType::FormRestored));
    }

    public function test_there_is_no_route_to_hard_delete_a_form(): void
    {
        $manager = User::factory()->superAdmin()->create();
        $form = $this->createFormVia($manager);

        foreach (["/admin/forms/{$form->id}", "/admin/forms/{$form->id}/edit", "/admin/forms/{$form->id}/builder"] as $url) {
            $status = $this->actingAs($manager)->delete($url)->status();
            $this->assertContains($status, [404, 405], "DELETE {$url} must not be routable.");
        }
        $this->assertModelExists($form);
    }

    public function test_duplicate_copies_definition_but_not_submissions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $form = $this->publishedForm($admin, [
            $this->fieldDef('select', 'department', 'Department', ['settings' => ['width' => 50, 'options' => [['label' => 'CSE', 'value' => 'cse'], ['label' => 'Other', 'value' => 'other']]], 'style_settings' => ['label_color' => '#112233']]),
            $this->fieldDef('text', 'other_department', 'Other', ['conditional_rules' => ['action' => 'show', 'match' => 'all', 'conditions' => [['field' => 'department', 'operator' => 'equals', 'value' => 'other']]]]),
            $this->fieldDef('html', 'notice', 'Notice', ['settings' => ['content' => '<p>Read me</p>']]),
        ], ['custom_css' => '.ff-label{color:red}', 'custom_html_after' => '<p>Thanks</p>']);

        $this->post("/forms/{$form->slug}", ['department' => 'cse'])->assertRedirect();
        $this->assertSame(1, $form->submissions()->count());

        $this->actingAs($admin)->post("/admin/forms/{$form->id}/duplicate")->assertRedirect();

        $copy = Form::query()->whereKeyNot($form->id)->firstOrFail();
        $this->assertSame('Copy of '.$form->name, $copy->name);
        $this->assertNotSame($form->slug, $copy->slug);
        $this->assertSame(FormStatus::Draft, $copy->status);
        $this->assertSame(0, $copy->submissions()->count());
        $this->assertSame($form->settings, $copy->settings);
        $this->assertSame($form->style_settings, $copy->style_settings);
        $this->assertSame('.ff-label{color:red}', $copy->custom_css);
        $this->assertSame('<p>Thanks</p>', $copy->custom_html_after);

        $copied = $copy->fields;
        $this->assertSame(['department', 'other_department', 'notice'], $copied->pluck('key')->all());
        $this->assertSame($form->fields[0]->options(), $copied[0]->options());
        $this->assertSame(['label_color' => '#112233'], $copied[0]->style_settings);
        $this->assertSame($form->fields[1]->conditional_rules, $copied[1]->conditional_rules);
        $this->assertSame('<p>Read me</p>', $copied[2]->setting('content'));

        $this->assertTrue(AuditLog::query()->where('event_type', AuditEventType::FormDuplicated)->where('record_id', $copy->id)->exists());
    }

    public function test_form_audit_entries_show_in_the_logbook(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->createFormVia($admin, 'Logbook Visible Form');

        $this->actingAs($admin)->get('/admin/logbook')->assertOk()
            ->assertSee('Form created')
            ->assertSee('Logbook Visible Form');
    }

    public function test_form_record_type_is_never_a_deletion_target(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $form = $this->createFormVia($admin);

        $this->expectException(\LogicException::class);
        app(DeletionRequestService::class)->request(DeletableRecordType::Form, $form->id, $admin, 'nope');
    }

    public function test_guests_and_inactive_users_cannot_reach_the_builder(): void
    {
        $owner = User::factory()->create();
        $form = $this->createFormVia($owner);
        auth()->logout();

        $this->get('/admin/forms')->assertRedirect('/admin/login');
        $this->putJson("/admin/forms/{$form->id}/builder", [])->assertUnauthorized();
        $this->get("/admin/forms/{$form->id}/submissions")->assertRedirect('/admin/login');
        $this->get("/admin/forms/{$form->id}/submissions/export.xlsx")->assertRedirect('/admin/login');

        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get('/admin/forms')->assertRedirect('/admin/login');
    }

    public function test_builder_and_lists_render_for_both_roles(): void
    {
        $manager = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $form = $this->createFormVia($manager, 'Render Check');

        foreach ([$manager, $admin] as $user) {
            $this->actingAs($user)->get('/admin/forms')->assertOk()->assertSee('Render Check');
            $this->actingAs($user)->get('/admin/forms/create')->assertOk();
            $this->actingAs($user)->get('/admin/forms/submissions')->assertOk()->assertSee('Render Check');
            $this->actingAs($user)->get("/admin/forms/{$form->id}/edit")->assertOk();
            $this->actingAs($user)->get("/admin/forms/{$form->id}/preview")->assertOk()->assertSee('Preview');
        }
        // Nav entries exist.
        $this->actingAs($manager)->get('/admin')->assertSee('All Forms')->assertSee('Create Form');
    }

    public function test_sanitize_preview_endpoint_scopes_css_and_cleans_html(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->actingAs($manager)->postJson("/admin/forms/{$form->id}/builder/sanitize", ['kind' => 'html', 'content' => '<p>a</p><script>x()</script>'])
            ->assertOk()->assertExactJson(['result' => '<p>a</p>']);
        $this->actingAs($manager)->postJson("/admin/forms/{$form->id}/builder/sanitize", ['kind' => 'css', 'content' => 'p{color:red}'])
            ->assertOk()->assertExactJson(['result' => "#ff-form-{$form->id} p{color:red;}"]);
    }
}
