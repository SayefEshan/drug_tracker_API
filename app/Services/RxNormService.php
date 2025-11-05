<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RxNormService
{
    private const BASE_URL = 'https://rxnav.nlm.nih.gov/REST';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Search for drugs by name using getDrugs endpoint
     * 
     * @param string $drugName
     * @return array
     */
    public function searchDrugs(string $drugName): array
    {
        $cacheKey = "rxnorm_search_{$drugName}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($drugName) {
            try {
                $response = Http::timeout(10)->get(self::BASE_URL . '/drugs.json', [
                    'name' => $drugName,
                ]);

                if (!$response->successful()) {
                    Log::error('RxNorm API getDrugs failed', [
                        'drug_name' => $drugName,
                        'status' => $response->status()
                    ]);
                    return [];
                }

                $data = $response->json();
                $conceptGroup = $data['drugGroup']['conceptGroup'] ?? [];
                
                // Find SBD (Semantic Branded Drug) type
                $sbdGroup = collect($conceptGroup)->firstWhere('tty', 'SBD');
                
                if (!$sbdGroup || !isset($sbdGroup['conceptProperties'])) {
                    return [];
                }

                // Get top 5 results
                $drugs = array_slice($sbdGroup['conceptProperties'], 0, 5);
                $results = [];

                foreach ($drugs as $drug) {
                    $rxcui = $drug['rxcui'];
                    $historyStatus = $this->getRxcuiHistoryStatus($rxcui);
                    
                    $results[] = [
                        'rxcui' => $rxcui,
                        'name' => $drug['name'],
                        'base_names' => $historyStatus['base_names'] ?? [],
                        'dose_form_group_names' => $historyStatus['dose_form_group_names'] ?? [],
                    ];
                }

                return $results;

            } catch (\Exception $e) {
                Log::error('Exception in searchDrugs', [
                    'message' => $e->getMessage(),
                    'drug_name' => $drugName
                ]);
                return [];
            }
        });
    }

    /**
     * Get RxCUI history status including ingredients and dose forms
     * 
     * @param string $rxcui
     * @return array
     */
    public function getRxcuiHistoryStatus(string $rxcui): array
    {
        $cacheKey = "rxnorm_history_{$rxcui}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($rxcui) {
            try {
                $response = Http::timeout(10)->get(self::BASE_URL . "/rxcui/{$rxcui}/historystatus.json");

                if (!$response->successful()) {
                    Log::error('RxNorm API getRxcuiHistoryStatus failed', [
                        'rxcui' => $rxcui,
                        'status' => $response->status()
                    ]);
                    return [
                        'base_names' => [],
                        'dose_form_group_names' => [],
                    ];
                }

                $data = $response->json();
                $attributes = $data['rxcuiStatusHistory']['attributes'] ?? null;

                $baseNames = [];
                $doseFormGroupNames = [];

                if ($attributes) {
                    // Extract base names from ingredientAndStrength
                    if (isset($attributes['ingredientAndStrength'])) {
                        $ingredients = is_array($attributes['ingredientAndStrength']) 
                            ? $attributes['ingredientAndStrength'] 
                            : [$attributes['ingredientAndStrength']];
                        
                        foreach ($ingredients as $ingredient) {
                            if (isset($ingredient['baseName'])) {
                                $baseNames[] = $ingredient['baseName'];
                            }
                        }
                    }

                    // Extract dose form group names
                    if (isset($attributes['doseFormGroupConcept'])) {
                        $doseFormGroups = is_array($attributes['doseFormGroupConcept']) 
                            ? $attributes['doseFormGroupConcept'] 
                            : [$attributes['doseFormGroupConcept']];
                        
                        foreach ($doseFormGroups as $doseFormGroup) {
                            if (isset($doseFormGroup['doseFormGroupName'])) {
                                $doseFormGroupName = $doseFormGroup['doseFormGroupName'];
                                if (!in_array($doseFormGroupName, $doseFormGroupNames)) {
                                    $doseFormGroupNames[] = $doseFormGroupName;
                                }
                            }
                        }
                    }
                }

                return [
                    'base_names' => array_unique($baseNames),
                    'dose_form_group_names' => array_unique($doseFormGroupNames),
                ];

            } catch (\Exception $e) {
                Log::error('Exception in getRxcuiHistoryStatus', [
                    'message' => $e->getMessage(),
                    'rxcui' => $rxcui
                ]);
                return [
                    'base_names' => [],
                    'dose_form_group_names' => [],
                ];
            }
        });
    }

    /**
     * Validate if an RXCUI exists and is active
     * 
     * @param string $rxcui
     * @return bool
     */
    public function validateRxcui(string $rxcui): bool
    {
        $cacheKey = "rxnorm_validate_{$rxcui}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($rxcui) {
            try {
                $response = Http::timeout(10)->get(self::BASE_URL . "/rxcui/{$rxcui}/status.json");

                if (!$response->successful()) {
                    return false;
                }

                $data = $response->json();
                $status = $data['rxcuiStatus']['status'] ?? null;

                return $status === 'Active' || $status === 'Remapped';

            } catch (\Exception $e) {
                Log::error('Exception in validateRxcui', [
                    'message' => $e->getMessage(),
                    'rxcui' => $rxcui
                ]);
                return false;
            }
        });
    }

    /**
     * Get detailed drug information for a specific RXCUI
     * 
     * @param string $rxcui
     * @return array|null
     */
    public function getDrugDetails(string $rxcui): ?array
    {
        try {
            // Get basic drug info
            $response = Http::timeout(10)->get(self::BASE_URL . "/rxcui/{$rxcui}/properties.json");
            
            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $properties = $data['properties'] ?? null;

            if (!$properties) {
                return null;
            }

            // Get history status for additional details
            $historyStatus = $this->getRxcuiHistoryStatus($rxcui);

            return [
                'rxcui' => $rxcui,
                'name' => $properties['name'] ?? 'Unknown',
                'base_names' => $historyStatus['base_names'],
                'dose_form_group_names' => $historyStatus['dose_form_group_names'],
            ];

        } catch (\Exception $e) {
            Log::error('Exception in getDrugDetails', [
                'message' => $e->getMessage(),
                'rxcui' => $rxcui
            ]);
            return null;
        }
    }
}
