<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OpportunityController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\OrganizationDecisionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController;
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

        Route::controller(UserController::class)->prefix('users')->name('users.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{user}', 'show')->name('show');
            Route::post('{user}/deactivate', 'deactivate')->name('deactivate');
            Route::post('{user}/reactivate', 'reactivate')->name('reactivate');
        });

        Route::controller(OpportunityController::class)->prefix('opportunities')->name('opportunities.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{opportunity}', 'show')->name('show');
            Route::post('{opportunity}/suspend', 'suspend')->name('suspend');
            Route::post('{opportunity}/reinstate', 'reinstate')->name('reinstate');
        });

        Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');

        Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{report}', 'show')->name('show');
            Route::post('{report}/decision', 'decide')->name('decide');
        });
    });
