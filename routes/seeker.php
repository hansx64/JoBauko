<?php

use App\Http\Controllers\Seeker\ApplicationController;
use App\Http\Controllers\Seeker\ApplicationDocumentController;
use App\Http\Controllers\Seeker\CertificationController;
use App\Http\Controllers\Seeker\EducationController;
use App\Http\Controllers\Seeker\ExperienceController;
use App\Http\Controllers\Seeker\InterestController;
use App\Http\Controllers\Seeker\ProfileController;
use App\Http\Controllers\Seeker\ResumeBuilderController;
use App\Http\Controllers\Seeker\ResumeController;
use App\Http\Controllers\Seeker\ResumeReviewController;
use App\Http\Controllers\Seeker\SavedOpportunityController;
use App\Http\Controllers\Seeker\SkillController;
use App\Http\Controllers\Seeker\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'account:seeker', 'matches.invalidate'])
    ->name('seeker.')
    ->group(function () {
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::prefix('profile')->name('profile.')->group(function () {
            Route::resource('education', EducationController::class)->only(['store', 'update', 'destroy']);
            Route::resource('experience', ExperienceController::class)->only(['store', 'update', 'destroy']);
            Route::resource('certifications', CertificationController::class)->only(['store', 'update', 'destroy']);
            Route::resource('trainings', TrainingController::class)->only(['store', 'update', 'destroy']);
            Route::put('skills', [SkillController::class, 'update'])->name('skills.update');
            Route::put('interests', [InterestController::class, 'update'])->name('interests.update');
        });

        Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::post('applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])->name('applications.withdraw');
        Route::post('applications/{application}/documents', [ApplicationDocumentController::class, 'store'])
            ->middleware('throttle:20,60')
            ->name('applications.documents.store');
        Route::get('opportunities/{opportunity:slug}/apply', [ApplicationController::class, 'create'])->name('applications.create');
        Route::post('opportunities/{opportunity:slug}/apply', [ApplicationController::class, 'store'])
            ->middleware('throttle:10,60')
            ->name('applications.store');

        Route::get('saved', [SavedOpportunityController::class, 'index'])->name('saved.index');
        Route::post('opportunities/{opportunity}/save', [SavedOpportunityController::class, 'store'])->name('saved.store');
        Route::delete('opportunities/{opportunity}/save', [SavedOpportunityController::class, 'destroy'])->name('saved.destroy');

        Route::post('resume', [ResumeController::class, 'store'])->middleware('throttle:10,60')->name('resume.store');
        Route::get('resume/{resume}/file', [ResumeController::class, 'show'])->name('resume.file');
        Route::post('resume/{resume}/extract', [ResumeController::class, 'extract'])->middleware('throttle:10,60')->name('resume.extract');
        Route::delete('resume/{resume}', [ResumeController::class, 'destroy'])->name('resume.destroy');
        Route::get('resume-builder', [ResumeBuilderController::class, 'edit'])->name('resume-builder.edit');
        Route::put('resume-builder', [ResumeBuilderController::class, 'update'])->name('resume-builder.update');
        Route::get('resume-builder/pdf', [ResumeBuilderController::class, 'pdf'])->middleware('throttle:30,1')->name('resume-builder.pdf');
        Route::post('resume-builder/save', [ResumeBuilderController::class, 'store'])->middleware('throttle:10,60')->name('resume-builder.store');

        Route::get('resume/{resume}/review', [ResumeReviewController::class, 'show'])->name('resume.review');
        Route::post('resume/{resume}/review', [ResumeReviewController::class, 'store'])->name('resume.review.store');
    });
