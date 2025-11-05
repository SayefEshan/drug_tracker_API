<?php

namespace App\Http\Controllers;

use App\Models\UserMedication;
use App\Services\RxNormService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserMedicationController extends Controller
{
    protected $rxNormService;

    public function __construct(RxNormService $rxNormService)
    {
        $this->rxNormService = $rxNormService;
    }

    /**
     * Get all medications for the authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $medications = $request->user()->medications()->get();

        return response()->json([
            'message' => 'Medications retrieved successfully',
            'data' => $medications->map(function ($medication) {
                return [
                    'id' => $medication->id,
                    'rxcui' => $medication->rxcui,
                    'drug_name' => $medication->drug_name,
                    'base_names' => $medication->base_names,
                    'dose_form_group_names' => $medication->dose_form_group_names,
                    'added_at' => $medication->created_at->toDateTimeString(),
                ];
            }),
        ], 200);
    }

    /**
     * Add a drug to user's medication list
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'rxcui' => 'required|string',
        ]);

        $rxcui = $request->input('rxcui');

        // Validate RXCUI with RxNorm API
        if (!$this->rxNormService->validateRxcui($rxcui)) {
            return response()->json([
                'message' => 'Invalid RXCUI. The drug does not exist or is not active.',
                'errors' => [
                    'rxcui' => ['The provided RXCUI is invalid or inactive.']
                ]
            ], 422);
        }

        // Check if user already has this medication
        $existingMedication = $request->user()->medications()
            ->where('rxcui', $rxcui)
            ->first();

        if ($existingMedication) {
            return response()->json([
                'message' => 'This medication is already in your list',
                'data' => $existingMedication,
            ], 409);
        }

        // Get drug details from RxNorm API
        $drugDetails = $this->rxNormService->getDrugDetails($rxcui);

        if (!$drugDetails) {
            return response()->json([
                'message' => 'Unable to retrieve drug details from RxNorm',
            ], 500);
        }

        // Add medication to user's list
        $medication = UserMedication::create([
            'user_id' => $request->user()->id,
            'rxcui' => $drugDetails['rxcui'],
            'drug_name' => $drugDetails['name'],
            'base_names' => $drugDetails['base_names'],
            'dose_form_group_names' => $drugDetails['dose_form_group_names'],
        ]);

        return response()->json([
            'message' => 'Medication added successfully',
            'data' => [
                'id' => $medication->id,
                'rxcui' => $medication->rxcui,
                'drug_name' => $medication->drug_name,
                'base_names' => $medication->base_names,
                'dose_form_group_names' => $medication->dose_form_group_names,
                'added_at' => $medication->created_at->toDateTimeString(),
            ],
        ], 201);
    }

    /**
     * Delete a drug from user's medication list
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        $medication = $request->user()->medications()->find($id);

        if (!$medication) {
            return response()->json([
                'message' => 'Medication not found in your list',
            ], 404);
        }

        $medication->delete();

        return response()->json([
            'message' => 'Medication deleted successfully',
        ], 200);
    }
}
