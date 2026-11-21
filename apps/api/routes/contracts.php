<?php

use App\Modules\Contracts\ContractController;
use Illuminate\Support\Facades\Route;

Route::get('contracts', [ContractController::class, 'index'])->middleware('can:contracts.read');
Route::post('contracts', [ContractController::class, 'store'])->middleware('can:contracts.write');

Route::prefix('contracts/{contract}')->whereUuid('contract')->group(function (): void {
    Route::get('/', [ContractController::class, 'show'])->middleware('can:contracts.read');
    Route::patch('/', [ContractController::class, 'update'])->middleware('can:contracts.write');
    Route::get('history', [ContractController::class, 'history'])->middleware('can:contracts.read');
    Route::post('transitions', [ContractController::class, 'transition'])->middleware('can:contracts.activate');
    Route::get('services', [ContractController::class, 'services'])->middleware('can:contracts.read');
    Route::post('services', [ContractController::class, 'createService'])->middleware('can:contracts.write');
    Route::get('pickup-schedules', [ContractController::class, 'schedules'])->middleware('can:contracts.read');
    Route::post('pickup-schedules', [ContractController::class, 'createSchedule'])->middleware('can:contracts.write');
    Route::patch('pickup-schedules/{schedule}', [ContractController::class, 'updateSchedule'])->whereUuid('schedule')->middleware('can:contracts.write');

    Route::prefix('services/{service}')->whereUuid('service')->group(function (): void {
        Route::patch('/', [ContractController::class, 'updateService'])->middleware('can:contracts.write');
        Route::get('rates', [ContractController::class, 'rates'])->middleware('can:contracts.read');
        Route::post('rates', [ContractController::class, 'addRate'])->middleware('can:contracts.pricing.write');
        Route::patch('rates/{rate}', [ContractController::class, 'updateRate'])->whereUuid('rate')->middleware('can:contracts.pricing.write');
    });
});
