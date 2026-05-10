<?php

use Illuminate\Support\Facades\Route;
use Whilesmart\Integrations\Http\Controllers\IntegrationController;

/*
|--------------------------------------------------------------------------
| Integrations API Routes
|--------------------------------------------------------------------------
|
| Here are the API routes for third-party integrations. These routes are
| automatically registered by the IntegrationsServiceProvider.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {

    // Available providers
    Route::get('/integrations/providers', [IntegrationController::class, 'providers']);

    // Workspace-scoped integration routes
    Route::prefix('workspaces/{workspaceId}/integrations')->group(function () {
        Route::get('/', [IntegrationController::class, 'index']);
        Route::get('/authorize/{provider}', [IntegrationController::class, 'authorize']);
        Route::post('/nango/session', [IntegrationController::class, 'nangoConnectSession']);
        Route::get('/{integrationId}', [IntegrationController::class, 'show']);
        Route::patch('/{integrationId}', [IntegrationController::class, 'update']);
        Route::delete('/{integrationId}', [IntegrationController::class, 'destroy']);
    });

    // Global integration routes (not workspace-scoped)
    Route::prefix('integrations')->group(function () {
        Route::get('/', [IntegrationController::class, 'index']);
        Route::get('/authorize/{provider}', [IntegrationController::class, 'authorize']);
        Route::post('/nango/session', [IntegrationController::class, 'nangoConnectSession']);
        Route::get('/{integrationId}', [IntegrationController::class, 'show']);
        Route::patch('/{integrationId}', [IntegrationController::class, 'update']);
        Route::delete('/{integrationId}', [IntegrationController::class, 'destroy']);
    });

    // OAuth callback (no auth required)
    Route::get('/integrations/oauth/callback', [IntegrationController::class, 'callback'])
        ->withoutMiddleware(['auth:sanctum']);
});

Route::post('/integrations/nango/webhook', [IntegrationController::class, 'nangoWebhook']);
