<?php

use App\Http\Controllers\Organization\ApplicationController;
use App\Http\Controllers\Organization\ApplicationReviewController;
use App\Http\Controllers\Organization\DashboardController;
use App\Http\Controllers\Organization\OpportunityController;
use App\Http\Controllers\Organization\OpportunityStatusController;
use App\Http\Controllers\Organization\OrganizationProfileController;
use App\Http\Controllers\Organization\PersonnelController;
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

        Route::middleware('org.can:view_applications')->group(function () {
            Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
            Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
            Route::get('applications/{application}/resume', [ApplicationController::class, 'resume'])->name('applications.resume');
            Route::post('applications/{application}/status', [ApplicationReviewController::class, 'status'])->name('applications.status');
            Route::post('applications/{application}/remarks', [ApplicationReviewController::class, 'remark'])->name('applications.remarks.store');
        });

        Route::middleware('org.can:manage_personnel')->group(function () {
            Route::get('personnel', [PersonnelController::class, 'index'])->name('personnel.index');
            Route::post('personnel', [PersonnelController::class, 'store'])->middleware('throttle:20,60')->name('personnel.store');
            Route::patch('personnel/{member}', [PersonnelController::class, 'update'])->name('personnel.update');
            Route::post('personnel/{member}/resend', [PersonnelController::class, 'resend'])->middleware('throttle:10,60')->name('personnel.resend');
        });
    });
