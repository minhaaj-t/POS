<?php

use App\Http\Controllers\DeviceRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DeviceRegistrationController::class, 'stageOne'])->name('registration.stage1');
Route::post('/stage-1', [DeviceRegistrationController::class, 'storeStageOne'])->name('registration.stage1.store');

Route::get('/stage-2', [DeviceRegistrationController::class, 'stageTwo'])->name('registration.stage2');
Route::post('/stage-2', [DeviceRegistrationController::class, 'storeStageTwo'])->name('registration.stage2.store');

Route::get('/stage-3', [DeviceRegistrationController::class, 'stageThree'])->name('registration.stage3');
Route::post('/stage-3', [DeviceRegistrationController::class, 'storeStageThree'])->name('registration.stage3.store');
Route::get('/api/employee/{employeeId}', [DeviceRegistrationController::class, 'getEmployeeById'])->name('registration.employee.get');

Route::get('/waiting-for-approval', [DeviceRegistrationController::class, 'waiting'])->name('registration.waiting');
Route::get('/home', [DeviceRegistrationController::class, 'home'])->name('home');
