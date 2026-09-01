<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\GhanaLocations;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function ghana(): JsonResponse
    {
        return response()->json([
            'regions' => GhanaLocations::regions(),
            'cities_by_region' => GhanaLocations::citiesByRegion(),
        ]);
    }
}
