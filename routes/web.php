<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Files\ApplicationDocumentController;
use App\Http\Controllers\Files\OrganizationDocumentController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\OpportunityController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');
Route::get('opportunities/{opportunity:slug}', [OpportunityController::class, 'show'])->name('opportunities.show');

Route::get('privacy', function () {
    return Inertia::render('privacy', [
        'version' => config('jobauko.privacy_notice_version'),
        'contactEmail' => config('jobauko.privacy_contact_email'),
    ]);
})->name('privacy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::get('organization-documents/{document}', [OrganizationDocumentController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('organization-documents.show');

Route::get('application-documents/{document}', [ApplicationDocumentController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('application-documents.show');

require __DIR__.'/seeker.php';
require __DIR__.'/organization.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
