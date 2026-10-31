<?php

use App\Http\Controllers\Organization\DashboardController;
use App\Http\Controllers\Organization\OpportunityController;
use App\Http\Controllers\Organization\OpportunityStatusController;
use App\Http\Controllers\Organization\OrganizationProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'account:organization', 'org.member'])
    ->prefix('org')
    ->name('org.')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('profile', [OrganizationProfileController::class, 'edit'])->name('profile.edit');
        Route::post('profile', [OrganizationProfileController::class, 'update'])->name('profile.update');

        Route::resource('opportunities', OpportunityController::class)->except(['show', 'destroy']);

        Route::controller(OpportunityStatusController::class)
            ->prefix('opportunities/{opportunity}')
            ->name('opportunities.')
            ->group(function () {
                Route::post('publish', 'publish')->name('publish');
                Route::post('unpublish', 'unpublish')->name('unpublish');
                Route::post('close', 'close')->name('close');
                Route::post('reopen', 'reopen')->name('reopen');
                Route::post('extend', 'extend')->name('extend');
                Route::post('archive', 'archive')->name('archive');
            });
    });
