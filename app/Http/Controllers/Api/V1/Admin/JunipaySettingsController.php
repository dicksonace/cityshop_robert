<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\JunipaySettingsController as WebJunipaySettings;
use App\Http\Controllers\Controller;
use App\Services\JunipayService;
use App\Services\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JunipaySettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => PlatformSettings::junipayStatus()]);
    }

    public function update(Request $request): JsonResponse
    {
        PlatformSettings::saveJunipaySettings(WebJunipaySettings::validatedSettings($request));

        return response()->json(['message' => 'Junipay settings saved.', 'data' => PlatformSettings::junipayStatus()]);
    }

    public function updateKeys(Request $request): JsonResponse
    {
        [$ok, $message] = WebJunipaySettings::saveKeys($request);

        return response()->json(['message' => $message, 'ok' => $ok, 'data' => PlatformSettings::junipayStatus()]);
    }

    public function verifyKeys(): JsonResponse
    {
        $probe = app(JunipayService::class)->probeCredentials();

        return response()->json(['message' => $probe['message'], 'ok' => $probe['ok'], 'data' => PlatformSettings::junipayStatus()]);
    }

    public function clearKeys(): JsonResponse
    {
        PlatformSettings::clearJunipayKeys();

        return response()->json(['message' => 'Admin Junipay keys cleared.', 'data' => PlatformSettings::junipayStatus()]);
    }
}
