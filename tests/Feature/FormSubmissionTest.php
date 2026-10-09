<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsForms;
use Tests\TestCase;
use TMAn1An\FormBuilder\Models\Form;
use TMAn1An\FormBuilder\Models\FormField;
use TMAn1An\FormBuilder\Models\FormSubmission;
use TMAn1An\FormBuilder\Models\FormSubmissionValue;

/**
 * Public /forms/{slug}: availability, server-side validation built from the
 * stored definition, conditional logic, and the snapshot storage that keeps
 * old submissions meaningful after the form changes. See
 * docs/FORM_BUILDER.md §Server-side validation / §Historical snapshot strategy.
 */
class FormSubmissionTest extends TestCase
{
    use BuildsForms, RefreshDatabase;

    private function departmentOptions(): array
    {
        return [['label' => 'Computer Science', 'value' => 'cse'], ['label' => 'Electrical', 'value' => 'eee'], ['label' => 'Other', 'value' => 'other']];
    }

    /** A realistic form: inputs, choices, a conditional field, content blocks, a hidden field. */
    private function registrationForm(array $overrides = []): Form
    {
        $manager = User::factory()->create();

        return $this->publishedForm($manager, [
            $this->fieldDef('heading', 'intro', 'Intro', ['settings' => ['content' => 'Register now']]),
            $this->fieldDef('text', 'full_name', 'Full name', ['required' => true, 'settings' => ['max_length' => 60]]),
            $this->fieldDef('email', 'email', 'Email', ['required' => true]),
            $this->fieldDef('number', 'age', 'Age', ['settings' => ['min' => '16', 'max' => '99']]),
            $this->fieldDef('date', 'arrival', 'Arrival date'),
            $this->fieldDef('select', 'department', 'Department', ['required' => true, 'settings' => ['options' => $this->departmentOptions()]]),
            $this->fieldDef('text', 'other_department', 'Other department', [
                'required' => true,
                'conditional_rules' => ['action' => 'show', 'match' => 'all', 'conditions' => [['field' => 'department', 'operator' => 'equals', 'value' => 'other']]],
            ]),
            $this->fieldDef('checkbox_group', 'interests', 'Interests', ['settings' => ['options' => [['label' => 'Robotics', 'value' => 'robotics'], ['label' => 'AI', 'value' => 'ai']]]]),
            $this->fieldDef('radio', 'tshirt', 'T-shirt', ['settings' => ['options' => [['label' => 'Small', 'value' => 's'], ['label' => 'Large', 'value' => 'l']]]]),
            $this->fieldDef('checkbox', 'consent', 'Consent', ['required' => true, 'settings' => ['checkbox_text' => 'I agree']]),
            $this->fieldDef('hidden', 'source', 'Source', ['settings' => ['default_value' => 'website']]),
            $this->fieldDef('phone', 'phone', 'Phone'),
        ], $overrides);
    }

    private function validAnswers(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'Ayesha Rahman',
            'email' => 'ayesha@example.com',
            'age' => '21',
            'arrival' => '2026-11-02',
            'department' => 'cse',
            'interests' => ['robotics', 'ai'],
            'tshirt' => 'l',
            'consent' => '1',
            'phone' => '01712-345678',
        ], $overrides);
    }

    public function test_public_page_renders_an_active_form(): void
    {
        $form = $this->registrationForm();

        $this->get("/forms/{$form->slug}")->assertOk()
            ->assertSee($form->title())
            ->assertSee('Register now')
            ->assertSee('name="full_name"', false)
            ->assertSee('name="interests[]"', false)
            ->assertSee('Computer Science')
            ->assertSee('data-ff-logic', false)
            ->assertSee('js/form-runtime.js', false)
            ->assertSee('IEEE', false); // inside the normal site chrome
    }

    public function test_active_public_form_accepts_a_valid_submission_with_snapshots(): void
    {
        $form = $this->registrationForm();

        $this->post("/forms/{$form->slug}", $this->validAnswers())
            ->assertRedirect("/forms/{$form->slug}")
            ->assertSessionHas('form_submitted', $form->id);

        $submission = FormSubmission::sole();
        $this->assertSame($form->id, $submission->form_id);
        $this->assertNull($submission->submitted_by);
        $this->assertSame($form->lock_version, $submission->form_version);

        $values = $submission->values->keyBy('field_key');
        $this->assertSame('Ayesha Rahman', $values['full_name']->value);
        $this->assertSame('Full name', $values['full_name']->field_label_snapshot);
        $this->assertSame('text', $values['full_name']->field_type_snapshot);
        $this->assertSame('cse', $values['department']->value);
        $this->assertSame('Computer Science', $values['department']->display_value, 'Option LABEL is snapshotted for display.');
        $this->assertSame(['robotics', 'ai'], $values['interests']->value);
        $this->assertSame('Robotics, AI', $values['interests']->display_value);
        $this->assertTrue($values['consent']->value);
        $this->assertSame('Yes', $values['consent']->display_value);
        $this->assertSame('website', $values['source']->value, 'Hidden field value comes from the definition.');
        $this->assertFalse($values->has('other_department'), 'Hidden-by-condition field is not stored.');
        $this->assertFalse($values->has('intro'), 'Content blocks store nothing.');

        $this->get("/forms/{$form->slug}")->assertSee('Thank you! Your response has been recorded.');
    }

    public function test_conditional_field_is_required_only_when_visible(): void
    {
        $form = $this->registrationForm();

        // Hidden (department != other): not required, and any posted value is ignored.
        $this->post("/forms/{$form->slug}", $this->validAnswers(['department' => 'cse', 'other_department' => 'sneaky']))
            ->assertSessionHasNoErrors();
        $this->assertFalse(FormSubmissionValue::query()->where('field_key', 'other_department')->exists());

        // Visible (department = other): now required...
        $this->post("/forms/{$form->slug}", $this->validAnswers(['department' => 'other']))
            ->assertSessionHasErrorsIn('form_'.$form->id, ['other_department']);
        $this->assertSame(1, FormSubmission::count());

        // ...and stored when given.
        $this->post("/forms/{$form->slug}", $this->validAnswers(['department' => 'other', 'other_department' => 'Civil']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Civil', FormSubmissionValue::query()->where('field_key', 'other_department')->value('display_value'));
    }

    public function test_section_visibility_hides_the_fields_inside_it(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [
            $this->fieldDef('radio', 'attending', 'Attending?', ['required' => true, 'settings' => ['options' => [['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']]]]),
            $this->fieldDef('section', 'travel', 'Travel details', ['conditional_rules' => ['action' => 'show', 'match' => 'all', 'conditions' => [['field' => 'attending', 'operator' => 'equals', 'value' => 'yes']]]]),
            $this->fieldDef('text', 'flight', 'Flight number', ['required' => true]),
            $this->fieldDef('section', 'other', 'Anything else'),
            $this->fieldDef('long_text', 'notes', 'Notes'),
        ]);

        $this->post("/forms/{$form->slug}", ['attending' => 'no', 'notes' => 'n/a'])->assertSessionHasNoErrors();
        $this->post("/forms/{$form->slug}", ['attending' => 'yes', 'notes' => 'n/a'])->assertSessionHasErrorsIn('form_'.$form->id, ['flight']);
    }

    public function test_server_validation_follows_field_types(): void
    {
        $form = $this->registrationForm();

        $this->post("/forms/{$form->slug}", $this->validAnswers([
            'full_name' => str_repeat('x', 61),
            'email' => 'not-an-email',
            'age' => '12',
            'arrival' => '02/11/2026',
            'phone' => '<script>',
        ]))->assertSessionHasErrorsIn('form_'.$form->id, ['full_name', 'email', 'age', 'arrival', 'phone']);

        $this->post("/forms/{$form->slug}", $this->validAnswers(['full_name' => '', 'consent' => null]))
            ->assertSessionHasErrorsIn('form_'.$form->id, ['full_name', 'consent']);

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_invalid_choice_values_are_rejected(): void
    {
        $form = $this->registrationForm();

        $this->post("/forms/{$form->slug}", $this->validAnswers(['department' => 'hacked']))->assertSessionHasErrorsIn('form_'.$form->id, ['department']);
        $this->post("/forms/{$form->slug}", $this->validAnswers(['tshirt' => 'xxl']))->assertSessionHasErrorsIn('form_'.$form->id, ['tshirt']);
        $this->post("/forms/{$form->slug}", $this->validAnswers(['interests' => ['robotics', 'injected']]))->assertSessionHasErrorsIn('form_'.$form->id, ['interests.1']);
        $this->post("/forms/{$form->slug}", $this->validAnswers(['department' => ['cse']]))->assertSessionHasErrorsIn('form_'.$form->id, ['department']);

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_unknown_field_keys_are_rejected(): void
    {
        $form = $this->registrationForm();

        $this->post("/forms/{$form->slug}", $this->validAnswers(['is_admin' => '1']))
            ->assertSessionHasErrorsIn('form_'.$form->id, ['form']);

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_manipulated_hidden_and_read_only_values_are_ignored(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [
            $this->fieldDef('text', 'name', 'Name', ['required' => true]),
            $this->fieldDef('hidden', 'campaign', 'Campaign', ['settings' => ['default_value' => 'fall-2026']]),
            $this->fieldDef('text', 'event', 'Event', ['settings' => ['default_value' => 'BECITHCON', 'read_only' => true]]),
            $this->fieldDef('text', 'internal', 'Internal', ['settings' => ['default_value' => 'x', 'disabled' => true]]),
        ]);

        $this->post("/forms/{$form->slug}", ['name' => 'A', 'campaign' => 'tampered', 'event' => 'tampered', 'internal' => 'tampered'])
            ->assertSessionHasNoErrors();

        $values = FormSubmission::sole()->values->keyBy('field_key');
        $this->assertSame('fall-2026', $values['campaign']->value);
        $this->assertSame('BECITHCON', $values['event']->value);
        $this->assertFalse($values->has('internal'), 'Disabled fields are never stored.');
    }

    public function test_draft_inactive_and_archived_forms_do_not_accept_submissions(): void
    {
        $manager = User::factory()->create();
        $draft = $this->createFormVia($manager, 'Draft Form');
        $this->saveDefinition($manager, $draft, [$this->fieldDef('text', 'name', 'Name')])->assertOk();
        auth()->logout();

        $this->get("/forms/{$draft->slug}")->assertNotFound();
        $this->post("/forms/{$draft->slug}", ['name' => 'x'])->assertNotFound();

        $form = $this->registrationForm();
        $this->actingAs($manager)->post("/admin/forms/{$form->id}/deactivate");
        auth()->logout();

        $this->get("/forms/{$form->slug}")->assertNotFound();
        $this->post("/forms/{$form->slug}", $this->validAnswers())->assertNotFound();

        $this->assertSame(0, FormSubmission::count());
    }

    public function test_private_form_requires_sign_in(): void
    {
        $form = $this->registrationForm(['settings' => ['visibility' => 'private']]);

        $this->get("/forms/{$form->slug}")->assertNotFound();
        $this->post("/forms/{$form->slug}", $this->validAnswers())->assertNotFound();

        $staff = User::factory()->create();
        $this->actingAs($staff)->get("/forms/{$form->slug}")->assertOk();
        $this->actingAs($staff)->post("/forms/{$form->slug}", $this->validAnswers())->assertSessionHasNoErrors();
        $this->assertSame($staff->id, FormSubmission::sole()->submitted_by);
    }

    public function test_submission_limit_schedule_and_single_submission_rules(): void
    {
        $limited = $this->registrationForm(['name' => 'Limited', 'settings' => ['submission_limit' => 1]]);
        $this->post("/forms/{$limited->slug}", $this->validAnswers())->assertSessionHasNoErrors();
        $this->post("/forms/{$limited->slug}", $this->validAnswers())->assertRedirect("/forms/{$limited->slug}");
        $this->assertSame(1, $limited->submissions()->count());
        $this->flushSession();
        $this->get("/forms/{$limited->slug}")->assertOk()->assertSee('reached its response limit');

        $once = $this->registrationForm(['name' => 'Once', 'settings' => ['allow_multiple_submissions' => false]]);
        $this->post("/forms/{$once->slug}", $this->validAnswers())->assertSessionHasNoErrors();
        $this->post("/forms/{$once->slug}", $this->validAnswers());
        $this->assertSame(1, $once->submissions()->count());

        $future = $this->registrationForm(['name' => 'Future', 'settings' => ['opens_at' => Carbon::now()->addDay()->format('Y-m-d\TH:i')]]);
        $this->get("/forms/{$future->slug}")->assertOk()->assertSee('not open for responses yet')->assertDontSee('name="full_name"', false);
        $this->post("/forms/{$future->slug}", $this->validAnswers());
        $this->assertSame(0, $future->submissions()->count());

        $closed = $this->registrationForm(['name' => 'Closed', 'settings' => ['closes_at' => Carbon::now()->subHour()->format('Y-m-d\TH:i')]]);
        $this->get("/forms/{$closed->slug}")->assertOk()->assertSee('is closed');
    }

    public function test_redirect_url_after_submission(): void
    {
        $form = $this->registrationForm(['settings' => ['redirect_url' => 'https://ieee.iubat.edu/events']]);

        $this->post("/forms/{$form->slug}", $this->validAnswers())->assertRedirect('https://ieee.iubat.edu/events');
    }

    public function test_javascript_redirect_urls_are_rejected_at_save(): void
    {
        $manager = User::factory()->create();
        $form = $this->createFormVia($manager);

        $this->saveDefinition($manager, $form, [], ['settings' => ['redirect_url' => 'javascript:alert(1)']])
            ->assertStatus(422)->assertJsonValidationErrors(['settings.redirect_url']);
    }

    public function test_old_submission_survives_field_rename_option_edit_and_archive(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [
            $this->fieldDef('text', 'full_name', 'Full name', ['required' => true]),
            $this->fieldDef('select', 'department', 'Department', ['settings' => ['options' => $this->departmentOptions()]]),
            $this->fieldDef('text', 'nickname', 'Nickname'),
        ]);
        $this->post("/forms/{$form->slug}", ['full_name' => 'Old Answer', 'department' => 'eee', 'nickname' => 'Oldie'])->assertSessionHasNoErrors();

        // Rename a label, relabel an option, and remove a field that has submissions.
        $payload = $this->fieldsAsPayload($form);
        $payload[0]['label'] = 'Your name';
        $payload[1]['settings']['options'][1]['label'] = 'EEE (renamed)';
        unset($payload[2]);
        $this->saveDefinition($manager, $form, array_values($payload))->assertOk();

        $nickname = FormField::query()->where('key', 'nickname')->sole();
        $this->assertFalse($nickname->is_active, 'A field with submissions is archived, not deleted.');

        // Its key stays reserved.
        $payload = $this->fieldsAsPayload($form);
        $payload = array_values(array_filter($payload, fn ($f) => $f['is_active']));
        $payload[] = $this->fieldDef('text', 'nickname', 'New nickname');
        $this->saveDefinition($manager, $form, $payload)->assertStatus(422);

        // The key of a field with submissions can't be changed.
        $payload = $this->fieldsAsPayload($form);
        $payload[0]['key'] = 'name_changed';
        $this->saveDefinition($manager, $form, $payload)->assertStatus(422)->assertJsonValidationErrors(['fields.0.key']);

        $values = FormSubmission::sole()->values->keyBy('field_key');
        $this->assertSame('Full name', $values['full_name']->field_label_snapshot);
        $this->assertSame('Electrical', $values['department']->display_value);
        $this->assertSame('Oldie', $values['nickname']->display_value);

        $submission = FormSubmission::sole();
        $this->actingAs($manager)->get("/admin/forms/{$form->id}/submissions/{$submission->id}")->assertOk()
            ->assertSee('Full name')
            ->assertSee('Now labelled')
            ->assertSee('Your name')
            ->assertSee('Electrical')
            ->assertSee('Oldie')
            ->assertSee('field archived');

        // The public form no longer shows the archived field.
        auth()->logout();
        $this->get("/forms/{$form->slug}")->assertDontSee('name="nickname"', false);
    }

    public function test_submissions_admin_pages_and_cross_form_idor(): void
    {
        $manager = User::factory()->create();
        $formA = $this->publishedForm($manager, [$this->fieldDef('text', 'name', 'Name', ['settings' => ['show_in_list' => true]])], ['name' => 'Form A']);
        $formB = $this->publishedForm($manager, [$this->fieldDef('text', 'name', 'Name')], ['name' => 'Form B']);
        $this->post("/forms/{$formA->slug}", ['name' => 'Alice'])->assertSessionHasNoErrors();
        $this->post("/forms/{$formB->slug}", ['name' => 'Bob'])->assertSessionHasNoErrors();
        $subB = $formB->submissions()->sole();

        $this->actingAs($manager)->get("/admin/forms/{$formA->id}/submissions")->assertOk()->assertSee('Alice')->assertDontSee('Bob');
        $this->actingAs($manager)->get("/admin/forms/{$formA->id}/submissions/{$subB->id}")->assertNotFound();
        $this->actingAs($manager)->get("/admin/forms/{$formB->id}/submissions/{$subB->id}")->assertOk()->assertSee('Bob');
    }

    public function test_submitted_values_are_escaped_in_admin_views(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [$this->fieldDef('text', 'name', 'Name', ['settings' => ['show_in_list' => true]])]);
        $this->post("/forms/{$form->slug}", ['name' => '<script>alert(1)</script>'])->assertSessionHasNoErrors();
        $submission = FormSubmission::sole();

        $this->actingAs($manager)->get("/admin/forms/{$form->id}/submissions")->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
        $this->actingAs($manager)->get("/admin/forms/{$form->id}/submissions/{$submission->id}")->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_form_title_is_escaped_in_the_public_page_head(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [$this->fieldDef('text', 'name', 'Name')], ['settings' => ['title' => '</title><script>alert(1)</script>']]);

        $this->get("/forms/{$form->slug}")->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }
}
