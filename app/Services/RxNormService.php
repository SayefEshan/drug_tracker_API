<?php

namespace App\Services;

use App\Clients\RxNormClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RxNormService
{
    private const CACHE_TTL = 86400; // 24 hours

    protected RxNormClient $client;

    public function __construct(RxNormClient $client)
    {
        $this->client = $client;
    }

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
                $data = $this->client->getDrugs($drugName);

                if (!$data) {
                    return [];
                }
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
                $data = $this->client->getRxcuiHistoryStatus($rxcui);

                if (!$data) {
                    return [
                        'base_names' => [],
                        'dose_form_group_names' => [],
                    ];
                }
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
                $data = $this->client->getRxcuiProperties($rxcui);

                if (!$data) {
                    return false;
                }

                $properties = $data['properties'] ?? null;
                
                if (!$properties) {
                    return false;
                }

                // Check if the drug is not suppressed (suppress=N means active)
                $suppress = $properties['suppress'] ?? 'Y';
                
                return $suppress === 'N';

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
            $data = $this->client->getRxcuiProperties($rxcui);
            
            if (!$data) {
                return null;
            }

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
