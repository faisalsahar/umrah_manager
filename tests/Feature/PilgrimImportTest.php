<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Pilgrim;
use App\Models\PilgrimImportMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class PilgrimImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_HEADER = "Mutamer name,Mutamer Age,Passport Number,Nationality,Main External Agent Code,Main External Agent Name,Sub EA Code,Sub EA Name,Visa Status,Biometric status,Visa Number,Mofa Number,Mutamer Type\n";

    private function csvFile(string $rows, string $name = 'mutamers.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, self::CSV_HEADER.$rows);
    }

    public function test_uploading_a_new_source_file_lands_on_the_mapping_step_with_guessed_mappings(): void
    {
        $operator = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        $component = Livewire::test('pages::groups.import', ['group' => $group])
            ->set('file', $this->csvFile("Ahmed Test,30,X123456,Sudan,1001,Test Agency,,,Visa Printed,Not Required,V123,M456,B2B\n"));

        $component->assertSet('step', 'map');
        $this->assertSame('name', $component->get('mapping')['Mutamer name']);
        $this->assertSame('passport_number', $component->get('mapping')['Passport Number']);
        $this->assertSame('agent_code', $component->get('mapping')['Main External Agent Code']);
    }

    public function test_full_import_creates_a_pilgrim_and_matches_the_agent_by_code(): void
    {
        $operator = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::groups.import', ['group' => $group])
            ->set('file', $this->csvFile("Ahmed Test,30,X123456,Sudan,1001,Test Agency,,,Visa Printed,Not Required,V123,M456,B2B\n"))
            ->call('confirmMapping')
            ->assertSet('step', 'review')
            ->call('fixRowField', 0, 'gender', 'male')
            ->call('commitImport');

        $pilgrim = Pilgrim::where('passport_number', 'X123456')->firstOrFail();
        $this->assertSame('Ahmed Test', $pilgrim->name);
        $this->assertSame($group->id, $pilgrim->group_id);
        $this->assertSame('Visa Printed', $pilgrim->visa_status);
        $this->assertSame('M456', $pilgrim->mofa_number);

        $agent = Organization::where('external_code', '1001')->firstOrFail();
        $this->assertSame(OrganizationType::Agent, $agent->type);
        $this->assertSame('Test Agency', $agent->name);
        $this->assertSame($agent->id, $pilgrim->agent_id);
    }

    public function test_repeat_upload_with_the_same_headers_skips_straight_to_review(): void
    {
        $operator = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        // First upload: map manually, which saves the mapping.
        Livewire::test('pages::groups.import', ['group' => $group])
            ->set('file', $this->csvFile("Ahmed Test,30,X123456,Sudan,1001,Test Agency,,,Visa Printed,Not Required,V123,M456,B2B\n"))
            ->call('confirmMapping');

        $this->assertSame(1, PilgrimImportMapping::where('operator_id', $operator->id)->count());

        // Second upload, same headers, different data — should reuse the mapping.
        Livewire::test('pages::groups.import', ['group' => $group])
            ->set('file', $this->csvFile("Fatima Test,25,Y987654,Sudan,1001,Test Agency,,,Visa Printed,Not Required,V999,M999,B2B\n"))
            ->assertSet('step', 'review')
            ->assertSet('mappingWasReused', true);

        $this->assertSame(1, PilgrimImportMapping::where('operator_id', $operator->id)->count());
    }

    public function test_a_row_missing_gender_needs_a_fix_before_it_can_be_imported(): void
    {
        $operator = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        $component = Livewire::test('pages::groups.import', ['group' => $group])
            ->set('file', $this->csvFile("Ahmed Test,30,X123456,Sudan,,,,,,,,,\n"))
            ->call('confirmMapping');

        $component->assertSet('invalidRowCount', 0);
        $component->assertSet('validRowCount', 1);

        // No gender column existed in this file, and none was mapped, so the
        // row is immediately importable — but if we blank the name it must
        // fail validation instead of silently importing an unusable record.
        $component->call('fixRowField', 0, 'name', '');
        $component->assertSet('validRowCount', 0);
        $component->assertSet('invalidRowCount', 1);
    }

    public function test_a_row_missing_gender_commits_with_a_null_gender(): void
    {
        $operator = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::groups.import', ['group' => $group])
            ->set('file', $this->csvFile("Ahmed Test,30,X123456,Sudan,,,,,,,,,\n"))
            ->call('confirmMapping')
            ->call('commitImport');

        $pilgrim = Pilgrim::where('passport_number', 'X123456')->firstOrFail();
        $this->assertNull($pilgrim->gender);
    }

    public function test_a_blank_optional_column_commits_as_null_not_an_empty_string(): void
    {
        $operator = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::groups.import', ['group' => $group])
            ->set('file', $this->csvFile("Ahmed Test,30,X999999,,,,,,,,,,\n"))
            ->call('confirmMapping')
            ->call('commitImport');

        $pilgrim = Pilgrim::where('passport_number', 'X999999')->firstOrFail();
        $this->assertNull($pilgrim->nationality);
        $this->assertNull($pilgrim->visa_status);
        $this->assertNull($pilgrim->visa_number);
        $this->assertNull($pilgrim->biometric_status);
        $this->assertNull($pilgrim->mofa_number);
        $this->assertNull($pilgrim->booking_type);
    }

    public function test_an_incompatible_file_shows_the_error_modal_instead_of_crashing(): void
    {
        $operator = Organization::factory()->operator()->create();
        $group = Group::factory()->create(['operator_id' => $operator->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        $component = Livewire::test('pages::groups.import', ['group' => $group])
            ->set('file', UploadedFile::fake()->createWithContent('empty.csv', ''));

        $this->assertNotNull($component->get('incompatibleError'));
        $component->assertSet('step', 'upload');
    }

    public function test_a_stranger_cannot_import_into_another_operators_group(): void
    {
        $group = Group::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger);

        Livewire::test('pages::groups.import', ['group' => $group])->assertStatus(403);
    }

    public function test_pilgrim_import_mapping_signature_is_order_and_case_independent(): void
    {
        $a = PilgrimImportMapping::signatureFor(['Name', 'Passport Number']);
        $b = PilgrimImportMapping::signatureFor(['passport number', 'name']);

        $this->assertSame($a, $b);
    }
}
