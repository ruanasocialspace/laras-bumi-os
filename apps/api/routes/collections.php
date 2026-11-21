<?php

use App\Modules\Collections\CollectionController;
use Illuminate\Support\Facades\Route;

Route::get('pickup-tasks', [CollectionController::class, 'tasks'])->middleware('can:collections.view');
Route::post('pickup-tasks', [CollectionController::class, 'createTask'])->middleware('can:collections.dispatch');
Route::post('pickup-tasks/generate', [CollectionController::class, 'generate'])->middleware('can:collections.dispatch');
Route::get('collection-drivers', [CollectionController::class, 'drivers'])->middleware('can:collections.dispatch');
Route::get('vehicles', [CollectionController::class, 'vehicles'])->middleware('can:collections.read');
Route::post('vehicles', [CollectionController::class, 'createVehicle'])->middleware('can:collections.dispatch');
Route::patch('vehicles/{vehicle}', [CollectionController::class, 'updateVehicle'])->whereUuid('vehicle')->middleware('can:collections.dispatch');
Route::get('customers/{customer}/pickup-history', [CollectionController::class, 'customerHistory'])->whereUuid('customer')
    ->middleware(['can:customers.read', 'can:collections.read']);

Route::prefix('pickup-tasks/{task}')->whereUuid('task')->group(function (): void {
    Route::get('/', [CollectionController::class, 'show'])->middleware('can:collections.view');
    Route::post('assignments', [CollectionController::class, 'assign'])->middleware('can:collections.dispatch');
    Route::post('cancel', [CollectionController::class, 'cancel'])->middleware('can:collections.dispatch');
    Route::get('events', [CollectionController::class, 'events'])->middleware('can:collections.view');
    Route::post('events', [CollectionController::class, 'addEvent'])->middleware('can:collections.drive');
    Route::post('evidence', [CollectionController::class, 'upload'])->middleware('can:collections.drive');
    Route::get('evidence/{file}', [CollectionController::class, 'evidence'])->whereUuid('file')->middleware('can:collections.view');
    Route::get('evidence/{file}/download', [CollectionController::class, 'download'])->whereUuid('file')->middleware('can:collections.view');
});
