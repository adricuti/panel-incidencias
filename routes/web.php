<?php

use App\Http\Controllers\IncidentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/incidents');

Route::controller(IncidentController::class)->prefix('incidents')->name('incidents.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/{incident}', 'show')->name('show');
    Route::patch('/{incident}/status', 'changeStatus')->name('status');
    Route::post('/{incident}/comments', 'storeComment')->name('comments.store');
});
