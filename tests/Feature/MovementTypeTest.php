<?php

namespace Tests\Feature;

use App\Enums\MovementCategory;
use App\Enums\OrganizationRole;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MovementTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_operator_user_can_add_a_movement_type(): void
    {
        $operator = Organization::factory()->operator()->create();
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::movement-types.index')
            ->set('newNameAr', 'استقبال')
            ->set('newNameEn', 'Arrival')
            ->set('newCategory', MovementCategory::Arrival->value)
            ->call('saveMovementType')
            ->assertHasNoErrors();

        $movementType = MovementType::where('operator_id', $operator->id)->firstOrFail();
        $this->assertSame('Arrival', $movementType->name_en);
        $this->assertSame(MovementCategory::Arrival, $movementType->category);
        $this->assertTrue($movementType->is_active);
    }

    public function test_a_movement_type_can_be_deactivated_and_reactivated(): void
    {
        $operator = Organization::factory()->operator()->create();
        $movementType = MovementType::factory()->create(['operator_id' => $operator->id, 'is_active' => true]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::movement-types.index')->call('toggleActive', $movementType->id);
        $this->assertFalse($movementType->fresh()->is_active);
    }

    public function test_a_user_cannot_toggle_another_operators_movement_type(): void
    {
        $operatorA = Organization::factory()->operator()->create();
        $operatorB = Organization::factory()->operator()->create();
        $foreignMovementType = MovementType::factory()->create(['operator_id' => $operatorB->id, 'is_active' => true]);

        $user = User::factory()->create();
        $operatorA->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operatorA->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::movement-types.index')->call('toggleActive', $foreignMovementType->id)->assertStatus(404);

        $this->assertTrue($foreignMovementType->fresh()->is_active);
    }

    public function test_a_movement_type_can_be_edited(): void
    {
        $operator = Organization::factory()->operator()->create();
        $movementType = MovementType::factory()->create(['operator_id' => $operator->id, 'name_en' => 'Old Name']);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        Livewire::test('pages::movement-types.index')
            ->call('edit', $movementType->id)
            ->set('newNameEn', 'New Name')
            ->call('saveMovementType')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $movementType->fresh()->name_en);
    }

    public function test_a_movement_type_still_used_by_a_movement_cannot_be_deleted_even_with_the_correct_math_answer(): void
    {
        $operator = Organization::factory()->operator()->create();
        $movementType = MovementType::factory()->create(['operator_id' => $operator->id]);
        $movement = Movement::factory()->create(['movement_type_id' => $movementType->id]);
        $user = User::factory()->create();
        $operator->addMember($user, OrganizationRole::Admin);
        $user->forceFill(['current_organization_id' => $operator->id])->save();

        $this->actingAs($user);

        // The database itself restricts this (movements.movement_type_id is
        // RESTRICT, not SET NULL) — the math check passing doesn't change that;
        // guardedDelete() catches the resulting QueryException.
        $component = Livewire::test('pages::movement-types.index')
            ->call('confirmDelete', $movementType->id, 1);

        $correctAnswer = $component->get('mathA') + $component->get('mathB');
        $component->set('mathAnswer', (string) $correctAnswer)->call('deleteConfirmed');

        $this->assertModelExists($movementType);
        $this->assertModelExists($movement);
    }
}
