<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SimulationController;
use App\Http\Controllers\ZoneController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth & Identity
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

    // Organizer Settings & Preferences
    Route::get('/settings', [SettingController::class, 'getSettings']);
    Route::put('/settings', [SettingController::class, 'updateSettings']);
    Route::post('/settings', [SettingController::class, 'updateSettings']);

    // Events CRUD
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    Route::put('/events/{id}', [EventController::class, 'update']);
    Route::delete('/events/{id}', [EventController::class, 'destroy']);

    // Event Specific Resources
    Route::get('/events/{eventId}/alerts/active', [EventController::class, 'activeAlerts']);
    Route::get('/events/{eventId}/alerts', [EventController::class, 'alerts']);
    Route::post('/events/{eventId}/alerts', [AlertController::class, 'store']);
    Route::get('/events/{eventId}/zones', [ZoneController::class, 'index']);
    Route::post('/events/{eventId}/zones', [ZoneController::class, 'store']);
    Route::get('/events/{eventId}/zones/{zoneId}/history', [ZoneController::class, 'history']);

    // Zones CRUD & Sensor Telemetry
    Route::get('/zones/{id}', [ZoneController::class, 'show']);
    Route::post('/zones', [ZoneController::class, 'store']);
    Route::put('/zones/{id}', [ZoneController::class, 'update']);
    Route::delete('/zones/{id}', [ZoneController::class, 'destroy']);
    Route::post('/zones/update', [ZoneController::class, 'updateOccupancy']);
    Route::get('/zones/{id}/history', [ZoneController::class, 'history']);

    // Alerts Management
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::post('/alerts', [AlertController::class, 'store']);
    Route::get('/alerts/{id}', [AlertController::class, 'show']);
    Route::post('/alerts/{id}/resolve', [AlertController::class, 'resolve']);
    Route::put('/alerts/{id}/resolve', [AlertController::class, 'resolve']);

    // Crowd Simulation
    Route::get('/simulations/context', [SimulationController::class, 'context']);
    Route::get('/events/{eventId}/simulations/context', [SimulationController::class, 'context']);
    Route::post('/simulations/run', [SimulationController::class, 'run']);
    Route::post('/simulations', [SimulationController::class, 'run']);
    Route::get('/simulations', [SimulationController::class, 'index']);
    Route::get('/simulations/{id}', [SimulationController::class, 'show']);

    // Event Crowd Reports, CSV & PDF Export
    Route::get('/events/{eventId}/reports', [ReportController::class, 'show']);
    Route::get('/events/{eventId}/reports/export', [ReportController::class, 'exportCsv']);
    Route::get('/events/{eventId}/reports/pdf', [ReportController::class, 'exportPdf']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/v1/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
