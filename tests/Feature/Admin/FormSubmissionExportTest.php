<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\BuildsForms;
use Tests\TestCase;

/**
 * Excel export: one row per submission, one column per (active or
 * archived) field, stable across renames, formula-injection guarded.
 * See docs/FORM_BUILDER.md §Excel export.
 */
class FormSubmissionExportTest extends TestCase
{
    use BuildsForms, RefreshDatabase;

    /** @return list<list<string|null>> */
    private function downloadRows(User $user, int $formId): array
    {
        $response = $this->actingAs($user)->get("/admin/forms/{$formId}/submissions/export.xlsx");
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'form-export-test').'.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $path);
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, false, false);
        @unlink($path);

        return $rows;
    }

    public function test_export_has_one_row_per_submission_and_readable_headings(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [
            $this->fieldDef('heading', 'title', 'Title', ['settings' => ['content' => 'Hello']]),
            $this->fieldDef('text', 'full_name', 'Full name'),
            $this->fieldDef('select', 'department', 'Department', ['settings' => ['options' => [['label' => 'Computer Science', 'value' => 'cse']]]]),
            $this->fieldDef('checkbox_group', 'interests', 'Interests', ['settings' => ['options' => [['label' => 'Robotics', 'value' => 'r'], ['label' => 'AI', 'value' => 'a']]]]),
            $this->fieldDef('phone', 'phone', 'Phone'),
        ]);
        $this->post("/forms/{$form->slug}", ['full_name' => 'Ayesha', 'department' => 'cse', 'interests' => ['r', 'a'], 'phone' => '01712345678']);
        $this->post("/forms/{$form->slug}", ['full_name' => 'Rahim']);

        $rows = $this->downloadRows($manager, $form->id);

        $this->assertSame(['Submission ID', 'Submitted At', 'Submitted By', 'Full name', 'Department', 'Interests', 'Phone'], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame(['Ayesha', 'Computer Science', 'Robotics, AI', '01712345678'], array_slice($rows[1], 3));
        $this->assertSame('01712345678', $rows[1][6], 'Leading zero kept -- cells are written as strings.');
        $this->assertSame(['Rahim', '', '', ''], array_map(fn ($v) => (string) $v, array_slice($rows[2], 3)));
    }

    public function test_export_mapping_is_stable_after_rename_and_archive(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [
            $this->fieldDef('text', 'full_name', 'Full name'),
            $this->fieldDef('text', 'nickname', 'Nickname'),
        ]);
        $this->post("/forms/{$form->slug}", ['full_name' => 'Old', 'nickname' => 'Oldie']);

        $payload = $this->fieldsAsPayload($form);
        $payload[0]['label'] = 'Your name';
        unset($payload[1]);
        $payload[] = $this->fieldDef('email', 'email', 'Email');
        $this->saveDefinition($manager, $form, array_values($payload))->assertOk();
        auth()->logout();
        $this->post("/forms/{$form->slug}", ['full_name' => 'New', 'email' => 'new@example.com'])->assertSessionHasNoErrors();

        $rows = $this->downloadRows($manager, $form->id);

        $this->assertSame(['Your name', 'Nickname (archived)', 'Email'], array_slice($rows[0], 3));
        $this->assertSame(['Old', 'Oldie', ''], array_map('strval', array_slice($rows[1], 3)));
        $this->assertSame(['New', '', 'new@example.com'], array_map('strval', array_slice($rows[2], 3)));
    }

    public function test_export_guards_against_formula_injection(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [
            $this->fieldDef('text', 'a', 'A'),
            $this->fieldDef('text', 'b', 'B'),
            $this->fieldDef('text', 'c', 'C'),
            $this->fieldDef('text', 'd', '=Label formula'),
        ]);
        $this->post("/forms/{$form->slug}", ['a' => '=HYPERLINK("http://evil.test","click")', 'b' => '+1+1', 'c' => '@SUM(A1)', 'd' => '-2+3'])
            ->assertSessionHasNoErrors();

        $rows = $this->downloadRows($manager, $form->id);

        $this->assertSame("'=Label formula", $rows[0][6]);
        $this->assertSame(["'=HYPERLINK(\"http://evil.test\",\"click\")", "'+1+1", "'@SUM(A1)", "'-2+3"], array_slice($rows[1], 3));
    }

    public function test_export_requires_authentication(): void
    {
        $manager = User::factory()->create();
        $form = $this->publishedForm($manager, [$this->fieldDef('text', 'a', 'A')]);

        $this->get("/admin/forms/{$form->id}/submissions/export.xlsx")->assertRedirect('/admin/login');
    }
}
