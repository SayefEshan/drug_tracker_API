<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RxNormClient
{
    private const BASE_URL = 'https://rxnav.nlm.nih.gov/REST';
    private const TIMEOUT = 10;

    /**
     * Search for drugs by name
     * 
     * @param string $drugName
     * @return array|null
     */
    public function getDrugs(string $drugName): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::BASE_URL . '/drugs.json', [
                'name' => $drugName,
            ]);

            if (!$response->successful()) {
                Log::error('RxNorm API getDrugs failed', [
                    'drug_name' => $drugName,
                    'status' => $response->status()
                ]);
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Exception in RxNormClient::getDrugs', [
                'message' => $e->getMessage(),
                'drug_name' => $drugName
            ]);
            return null;
        }
    }

    /**
     * Get RxCUI history status
     * 
     * @param string $rxcui
     * @return array|null
     */
    public function getRxcuiHistoryStatus(string $rxcui): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::BASE_URL . "/rxcui/{$rxcui}/historystatus.json");

            if (!$response->successful()) {
                Log::error('RxNorm API getRxcuiHistoryStatus failed', [
                    'rxcui' => $rxcui,
                    'status' => $response->status()
                ]);
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Exception in RxNormClient::getRxcuiHistoryStatus', [
                'message' => $e->getMessage(),
                'rxcui' => $rxcui
            ]);
            return null;
        }
    }

    /**
     * Get RxCUI status
     * 
     * @param string $rxcui
     * @return array|null
     */
    public function getRxcuiStatus(string $rxcui): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::BASE_URL . "/rxcui/{$rxcui}/status.json");

            if (!$response->successful()) {
                Log::error('RxNorm API getRxcuiStatus failed', [
                    'rxcui' => $rxcui,
                    'status' => $response->status()
                ]);
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Exception in RxNormClient::getRxcuiStatus', [
                'message' => $e->getMessage(),
                'rxcui' => $rxcui
            ]);
            return null;
        }
    }

    /**
     * Get RxCUI properties
     * 
     * @param string $rxcui
     * @return array|null
     */
    public function getRxcuiProperties(string $rxcui): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::BASE_URL . "/rxcui/{$rxcui}/properties.json");

            if (!$response->successful()) {
                Log::error('RxNorm API getRxcuiProperties failed', [
                    'rxcui' => $rxcui,
                    'status' => $response->status()
                ]);
                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Exception in RxNormClient::getRxcuiProperties', [
                'message' => $e->getMessage(),
                'rxcui' => $rxcui
            ]);
            return null;
        }
    }
}
