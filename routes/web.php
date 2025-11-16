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
Route::get('/api/employee/{employeeId}', [DeviceRegistrationController::class, 'getEmployeeById'])->name('registration.employee.get');

Route::get('/waiting-for-approval', [DeviceRegistrationController::class, 'waiting'])->name('registration.waiting');

// Test route for API connection (remove in production)
Route::get('/test-api/employee/{employeeId}', function ($employeeId) {
    $apiUrl = "https://vansale-app.loca.lt/api/user/{$employeeId}";
    
    try {
        $response = Http::timeout(10)
            ->withoutVerifying()
            ->get($apiUrl);
        
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
