<?php

use App\Modules\Customers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::get('customers', [CustomerController::class, 'index'])->middleware('can:customers.read');
Route::post('customers', [CustomerController::class, 'store'])->middleware('can:customers.write');
Route::get('customer-categories', [CustomerController::class, 'categories'])->middleware('can:customers.read');
Route::post('customer-categories', [CustomerController::class, 'createCategory'])->middleware('can:customers.categories.manage');

Route::prefix('customers/{customer}')->whereUuid('customer')->group(function (): void {
    Route::get('/', [CustomerController::class, 'show'])->middleware('can:customers.read');
    Route::patch('/', [CustomerController::class, 'update'])->middleware('can:customers.write');
    Route::get('contacts', [CustomerController::class, 'contacts'])->middleware('can:customers.read');
    Route::get('locations', [CustomerController::class, 'locations'])->middleware('can:customers.read');
    Route::post('contacts', [CustomerController::class, 'saveContact'])->middleware('can:customers.write');
    Route::post('locations', [CustomerController::class, 'saveLocation'])->middleware('can:customers.write');
    Route::patch('contacts/{record}', [CustomerController::class, 'updateContact'])->whereUuid('record')->middleware('can:customers.write');
    Route::patch('locations/{record}', [CustomerController::class, 'updateLocation'])->whereUuid('record')->middleware('can:customers.write');
    Route::get('billing-profile', [CustomerController::class, 'billing'])->middleware('can:finance.read');
    Route::put('billing-profile', [CustomerController::class, 'saveBilling'])->middleware('can:finance.bill');
});
