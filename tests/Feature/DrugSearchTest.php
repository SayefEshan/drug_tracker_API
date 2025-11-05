<?php

namespace Tests\Feature;

use App\Clients\RxNormClient;
use App\Services\RxNormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DrugSearchTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test drug search with valid drug name
     */
    public function test_can_search_drugs_with_valid_name(): void
    {
        // Mock HTTP response
        Http::fake([
            'rxnav.nlm.nih.gov/REST/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [
                        [
                            'tty' => 'SBD',
                            'conceptProperties' => [
                                [
                                    'rxcui' => '123456',
                                    'name' => 'Test Drug 10 MG Oral Tablet',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/*/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'attributes' => [
                        'ingredientAndStrength' => [
                            ['baseName' => 'Test Ingredient'],
                        ],
                        'doseFormGroupConcept' => [
                            ['doseFormGroupName' => 'Oral Tablet'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/drugs/search?drug_name=aspirin');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'rxcui',
                        'name',
                        'base_names',
                        'dose_form_group_names',
                    ],
                ],
            ]);
    }

    /**
     * Test search fails without drug_name parameter
     */
    public function test_search_fails_without_drug_name(): void
    {
        $response = $this->getJson('/api/drugs/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['drug_name']);
    }

    /**
     * Test search fails with too short drug name
     */
    public function test_search_fails_with_short_drug_name(): void
    {
        $response = $this->getJson('/api/drugs/search?drug_name=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['drug_name']);
    }

    /**
     * Test search returns empty array when no drugs found
     */
    public function test_search_returns_empty_when_no_drugs_found(): void
    {
        Http::fake([
            'rxnav.nlm.nih.gov/REST/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/drugs/search?drug_name=nonexistentdrug');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'No drugs found matching your search',
                'data' => [],
            ]);
    }

    /**
     * Test rate limiting on search endpoint
     */
    public function test_search_endpoint_has_rate_limiting(): void
    {
        Http::fake([
            'rxnav.nlm.nih.gov/*' => Http::response([], 200),
        ]);

        // Make 61 requests (rate limit is 60 per minute)
        for ($i = 0; $i < 61; $i++) {
            $response = $this->getJson('/api/drugs/search?drug_name=test');
            
            if ($i < 60) {
                // First 60 should succeed
                $this->assertContains($response->status(), [200, 422]);
            }
        }

        // 61st request should be rate limited
        $response = $this->getJson('/api/drugs/search?drug_name=test');
        $response->assertStatus(429);
    }

    /**
     * Test search results are cached
     */
    public function test_search_results_are_cached(): void
    {
        Cache::flush();

        Http::fake([
            'rxnav.nlm.nih.gov/REST/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [
                        [
                            'tty' => 'SBD',
                            'conceptProperties' => [
                                ['rxcui' => '123456', 'name' => 'Test Drug'],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/*/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => ['attributes' => []],
            ], 200),
        ]);

        $rxNormClient = new RxNormClient();
        $rxNormService = new RxNormService($rxNormClient);
        
        // First call should hit the API
        $result1 = $rxNormService->searchDrugs('aspirin');
        
        // Second call should use cache
        $result2 = $rxNormService->searchDrugs('aspirin');
        
        $this->assertEquals($result1, $result2);
        
        // Verify HTTP was only called once per endpoint
        Http::assertSentCount(2); // One for drugs.json, one for historystatus.json
    }
}
