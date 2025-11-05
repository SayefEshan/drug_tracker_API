<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMedication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserMedicationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test authenticated user can view their medications
     */
    public function test_authenticated_user_can_view_medications(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create some medications for the user
        UserMedication::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/medications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'rxcui',
                        'drug_name',
                        'base_names',
                        'dose_form_group_names',
                        'added_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    /**
     * Test unauthenticated user cannot view medications
     */
    public function test_unauthenticated_user_cannot_view_medications(): void
    {
        $response = $this->getJson('/api/medications');

        $response->assertStatus(401);
    }

    /**
     * Test authenticated user can add medication with valid rxcui
     */
    public function test_authenticated_user_can_add_medication(): void
    {
        Http::fake([
            'rxnav.nlm.nih.gov/REST/rxcui/*/status.json' => Http::response([
                'rxcuiStatus' => ['status' => 'Active'],
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/*/properties.json' => Http::response([
                'properties' => ['name' => 'Aspirin 81 MG Oral Tablet'],
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/*/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'attributes' => [
                        'ingredientAndStrength' => [
                            ['baseName' => 'Aspirin'],
                        ],
                        'doseFormGroupConcept' => [
                            ['doseFormGroupName' => 'Oral Tablet'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/medications', [
            'rxcui' => '123456',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'rxcui',
                    'drug_name',
                    'base_names',
                    'dose_form_group_names',
                    'added_at',
                ],
            ]);

        $this->assertDatabaseHas('user_medications', [
            'user_id' => $user->id,
            'rxcui' => '123456',
        ]);
    }

    /**
     * Test adding medication fails with invalid rxcui
     */
    public function test_adding_medication_fails_with_invalid_rxcui(): void
    {
        Http::fake([
            'rxnav.nlm.nih.gov/REST/rxcui/*/status.json' => Http::response([
                'rxcuiStatus' => ['status' => 'Retired'],
            ], 200),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/medications', [
            'rxcui' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid RXCUI. The drug does not exist or is not active.',
            ]);
    }

    /**
     * Test adding duplicate medication returns conflict
     */
    public function test_adding_duplicate_medication_returns_conflict(): void
    {
        Http::fake([
            'rxnav.nlm.nih.gov/REST/rxcui/*/status.json' => Http::response([
                'rxcuiStatus' => ['status' => 'Active'],
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/*/properties.json' => Http::response([
                'properties' => ['name' => 'Test Drug'],
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/*/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => ['attributes' => []],
            ], 200),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Add medication first time
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/medications', ['rxcui' => '123456']);

        // Try to add same medication again
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/medications', ['rxcui' => '123456']);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'This medication is already in your list',
            ]);
    }

    /**
     * Test authenticated user can delete their medication
     */
    public function test_authenticated_user_can_delete_medication(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $medication = UserMedication::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/medications/' . $medication->id);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Medication deleted successfully',
            ]);

        $this->assertDatabaseMissing('user_medications', [
            'id' => $medication->id,
        ]);
    }

    /**
     * Test user cannot delete another user's medication
     */
    public function test_user_cannot_delete_another_users_medication(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $token = $user1->createToken('test')->plainTextToken;

        $medication = UserMedication::factory()->create(['user_id' => $user2->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->deleteJson('/api/medications/' . $medication->id);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Medication not found in your list',
            ]);

        // Verify medication still exists
        $this->assertDatabaseHas('user_medications', [
            'id' => $medication->id,
        ]);
    }

    /**
     * Test unauthenticated user cannot add medication
     */
    public function test_unauthenticated_user_cannot_add_medication(): void
    {
        $response = $this->postJson('/api/medications', [
            'rxcui' => '123456',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test unauthenticated user cannot delete medication
     */
    public function test_unauthenticated_user_cannot_delete_medication(): void
    {
        $user = User::factory()->create();
        $medication = UserMedication::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson('/api/medications/' . $medication->id);

        $response->assertStatus(401);
    }
}
