<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthApiController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/summary', [HealthApiController::class, 'summary']);
        Route::get('/households', [HealthApiController::class, 'households']);
        Route::post('/households', [HealthApiController::class, 'createHousehold']);
        Route::get('/icd10', [HealthApiController::class, 'icd10']);
        Route::get('/patients', [HealthApiController::class, 'patients']);
        Route::post('/patients', [HealthApiController::class, 'createPatient']);
        Route::put('/patients/{patient}', [HealthApiController::class, 'updatePatient']);
        Route::delete('/patients/{patient}', [HealthApiController::class, 'deletePatient']);
        Route::get('/encounters', [HealthApiController::class, 'encounters']);
        Route::post('/encounters', [HealthApiController::class, 'createEncounter']);
        Route::get('/immunizations', [HealthApiController::class, 'immunizations']);
        Route::get('/vaccine-schedules', [HealthApiController::class, 'vaccineSchedules']);
        Route::post('/immunizations', [HealthApiController::class, 'createImmunization']);
        Route::post('/sync', [HealthApiController::class, 'sync'])->middleware('throttle:30,1');
        Route::get('/reports/fhsis/diagnoses', [HealthApiController::class, 'diagnosisReport']);
        Route::middleware(EnsureSuperAdmin::class)->group(function () {
            Route::get('/users', [HealthApiController::class, 'users']);
            Route::post('/users', [HealthApiController::class, 'createUser']);
            Route::put('/users/{user}', [HealthApiController::class, 'updateUser']);
        });
    });
});
