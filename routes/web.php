<?php

use App\Http\Controllers\DeviceRegistrationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/', [DeviceRegistrationController::class, 'stageOne'])->name('registration.stage1');
Route::post('/stage-1', [DeviceRegistrationController::class, 'storeStageOne'])->name('registration.stage1.store');

Route::get('/stage-2', [DeviceRegistrationController::class, 'stageTwo'])->name('registration.stage2');
Route::post('/stage-2', [DeviceRegistrationController::class, 'storeStageTwo'])->name('registration.stage2.store');

Route::get('/stage-3', [DeviceRegistrationController::class, 'stageThree'])->name('registration.stage3');
Route::post('/stage-3', [DeviceRegistrationController::class, 'storeStageThree'])->name('registration.stage3.store');
Route::get('/api/employee/{employeeId}', [DeviceRegistrationController::class, 'getEmployeeById'])->name('registration.employee.get')->where('employeeId', '[0-9]+');
Route::options('/api/employee/{employeeId}', function () {
    return response()->json([])
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
});

Route::get('/waiting-for-approval', [DeviceRegistrationController::class, 'waiting'])->name('registration.waiting');

// Test route for API connection (remove in production)
Route::get('/test-api/employee/{employeeId}', function ($employeeId) {
    // Use config for API URL
    $baseUrl = config('api.production.url');
    $endpoint = str_replace('{id}', $employeeId, config('api.endpoints.employee'));
    $apiUrl = $baseUrl . $endpoint;
    
    try {
        $timeout = config('api.production.timeout', 10);
        $request = Http::timeout($timeout);
        
        if (!config('api.production.verify_ssl', false)) {
            $request = $request->withoutVerifying();
        }
        
        $response = $request->get($apiUrl);
        
        return response()->json([
            'url' => $apiUrl,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'data' => $response->successful() ? $response->json() : $response->body(),
            'headers' => $response->headers(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
})->where('employeeId', '[0-9]+');
