<?php

namespace App\Http\Controllers;

use App\Services\RxNormService;
use Illuminate\Http\Request;

class DrugSearchController extends Controller
{
    protected $rxNormService;

    public function __construct(RxNormService $rxNormService)
    {
        $this->rxNormService = $rxNormService;
    }

    /**
     * Search for drugs (Public endpoint)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $request->validate([
            'drug_name' => 'required|string|min:2|max:255',
        ]);

        $drugName = $request->input('drug_name');
        $results = $this->rxNormService->searchDrugs($drugName);

        if (empty($results)) {
            return response()->json([
                'message' => 'No drugs found matching your search',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Drugs retrieved successfully',
            'data' => $results,
        ], 200);
    }
}
