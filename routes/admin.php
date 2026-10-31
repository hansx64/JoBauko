<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\OrganizationDecisionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'account:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');

        Route::controller(OrganizationDecisionController::class)
            ->prefix('organizations/{organization}')
            ->name('organizations.')
            ->group(function () {
                Route::post('approve', 'approve')->name('approve');
                Route::post('reject', 'reject')->name('reject');
                Route::post('suspend', 'suspend')->name('suspend');
                Route::post('reinstate', 'reinstate')->name('reinstate');
            });
    });
