<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Afp;
use App\Http\Controllers\ChatConversationController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\GovAgency;
use App\Http\Controllers\Ib39;
use App\Http\Controllers\Japic;
use App\Http\Controllers\Lgu;
use App\Http\Controllers\Mblrc;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Pswdo;
use App\Http\Controllers\SuperAdmin;
use Illuminate\Support\Facades\Route;

// Public landing page — original static site copied to public/landing/.
Route::get('/', fn () => response()->file(public_path('landing/index.html')))->name('landing');

// Shared authenticated routes.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/rcsp-form/{form}/evidence', [Lgu\MonitoringController::class, 'evidence'])->name('rcsp.evidence');

    Route::get('/chat', [ChatConversationController::class, 'index'])->name('chat.index');
    Route::get('/chat/unread', [ChatConversationController::class, 'unread'])
        ->middleware('throttle:30,1')->name('chat.unread');
    Route::post('/chat/conversations', [ChatConversationController::class, 'store'])
        ->middleware('throttle:20,1')->name('chat.conversations.store');
    Route::get('/chat/conversations/{chatConversation}', [ChatConversationController::class, 'show'])
        ->name('chat.conversations.show');
    Route::get('/chat/conversations/{chatConversation}/messages', [ChatMessageController::class, 'index'])
        ->middleware('throttle:30,1')->name('chat.messages.index');
    Route::post('/chat/conversations/{chatConversation}/read', [ChatConversationController::class, 'markRead'])
        ->middleware('throttle:60,1')->name('chat.conversations.read');
    Route::post('/chat/conversations/{chatConversation}/messages', [ChatMessageController::class, 'store'])
        ->middleware('throttle:60,1')->name('chat.messages.store');
});

/*
| Role areas. Each group mirrors one legacy accounts/<role>/ folder and is
| gated by the `role` middleware. Feature routes are added per role during
| the phased migration; for now each exposes a dashboard.
*/

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super_admin.')->group(function () {
    Route::get('/', [SuperAdmin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [SuperAdmin\UserController::class, 'index'])->name('users.index');
    Route::post('/users', [SuperAdmin\UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [SuperAdmin\UserController::class, 'update'])->name('users.update');

    Route::get('/agencies', [SuperAdmin\AgencyController::class, 'index'])->name('agencies.index');
    Route::post('/agencies', [SuperAdmin\AgencyController::class, 'store'])->name('agencies.store');
    Route::put('/agencies/{agency}', [SuperAdmin\AgencyController::class, 'update'])->name('agencies.update');
    Route::delete('/agencies/{agency}', [SuperAdmin\AgencyController::class, 'destroy'])->name('agencies.destroy');
});

Route::middleware(['auth', 'role:admin'])->prefix('katuparan')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // RCSP form review
    Route::get('/rcsp', [Admin\RcspReviewController::class, 'index'])->name('rcsp.index');
    Route::get('/rcsp/{rcspBarangay}', [Admin\RcspReviewController::class, 'show'])->name('rcsp.show');
    Route::post('/rcsp/{rcspBarangay}/review', [Admin\RcspReviewController::class, 'updateStatus'])->name('rcsp.review');
    Route::get('/rcsp-form/{form}/file', [Admin\RcspReviewController::class, 'file'])->name('rcsp.file');
    Route::post('/rcsp-form/{form}/comment', [Admin\RcspReviewController::class, 'storeComment'])->name('rcsp.comment');

    // IMPLAN verify + reassign
    Route::get('/implan', [Admin\ImplanController::class, 'index'])->name('implan.index');
    Route::get('/implan/{implan}', [Admin\ImplanController::class, 'show'])->name('implan.show');
    Route::post('/implan/{implan}/verify', [Admin\ImplanController::class, 'verify'])->name('implan.verify');
    Route::post('/implan/{implan}/reassign', [Admin\ImplanController::class, 'reassign'])->name('implan.reassign');

    // Overviews
    Route::get('/agencies', [Admin\OverviewController::class, 'agencies'])->name('agencies.index');
    Route::get('/locations', [Admin\OverviewController::class, 'locations'])->name('locations.index');
    Route::get('/clusters', [Admin\OverviewController::class, 'clusters'])->name('clusters.index');
    Route::get('/clusters/{slug}', [Admin\OverviewController::class, 'clusterProfile'])->name('clusters.show');
    Route::get('/users', [Admin\OverviewController::class, 'users'])->name('users.index');

    // Barangay infestation colours for the dashboard hero map.
    Route::get('/area-data', [Admin\DashboardController::class, 'areaData'])->name('area.data');
});

Route::middleware(['auth', 'role:lgu'])->prefix('lgu')->name('lgu.')->group(function () {
    Route::get('/', [Lgu\DashboardController::class, 'index'])->name('dashboard');

    // RCSP barangays
    Route::get('/rcsp', [Lgu\RcspBarangayController::class, 'index'])->name('rcsp.index');
    Route::post('/rcsp', [Lgu\RcspBarangayController::class, 'store'])->name('rcsp.store');
    Route::delete('/rcsp/{rcspBarangay}', [Lgu\RcspBarangayController::class, 'destroy'])->name('rcsp.destroy');

    // Monitoring form (per barangay)
    Route::get('/rcsp/{rcspBarangay}/monitoring', [Lgu\MonitoringController::class, 'show'])->name('monitoring.show');
    Route::post('/rcsp/{rcspBarangay}/activities', [Lgu\MonitoringController::class, 'storeActivity'])->name('activities.store');
    Route::patch('/rcsp/{rcspBarangay}/activities/{activity}', [Lgu\MonitoringController::class, 'updateActivity'])->name('activities.update');
    Route::delete('/rcsp/{rcspBarangay}/activities/{activity}', [Lgu\MonitoringController::class, 'destroyActivity'])->name('activities.destroy');
    Route::post('/rcsp/{rcspBarangay}/activities/{activity}/submit', [Lgu\MonitoringController::class, 'submit'])->name('monitoring.submit');
    Route::post('/rcsp/{rcspBarangay}/proceed', [Lgu\MonitoringController::class, 'proceed'])->name('monitoring.proceed');
    Route::get('/rcsp-form/{form}/file', [Lgu\MonitoringController::class, 'file'])->name('monitoring.file');
    Route::post('/rcsp-form/{form}/comment', [Lgu\MonitoringController::class, 'storeComment'])->name('monitoring.comment');

    // Legacy page_rscp_eval.php lived at its own URL; the list at /lgu/rcsp is that page.
    Route::redirect('/evaluation', '/lgu/rcsp')->name('evaluation.index');

    // IMPLAN
    Route::get('/implan', [Lgu\ImplanController::class, 'index'])->name('implan.index');
    Route::post('/implan', [Lgu\ImplanController::class, 'store'])->name('implan.store');
    Route::get('/implan/{implan}', [Lgu\ImplanController::class, 'show'])->name('implan.show');
    Route::put('/implan/{implan}', [Lgu\ImplanController::class, 'update'])->name('implan.update');
    Route::put('/implan/{implan}/implementation', [Lgu\ImplanController::class, 'updateImplementation'])->name('implan.implementation');
    Route::post('/implan/{implan}/agenda', [Lgu\ImplanController::class, 'uploadAgenda'])->name('implan.agenda');
    Route::post('/implan/{implan}/verify', [Lgu\ImplanController::class, 'verify'])->name('implan.verify');
    Route::delete('/implan/{implan}', [Lgu\ImplanController::class, 'destroy'])->name('implan.destroy');
});

Route::middleware(['auth', 'role:gov_agency'])->prefix('agency')->name('gov_agency.')->group(function () {
    Route::get('/', [GovAgency\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/implan', [GovAgency\ImplanController::class, 'index'])->name('implan.index');
    Route::get('/implan/{implan}', [GovAgency\ImplanController::class, 'show'])->name('implan.show');
    Route::post('/implan/{implan}/respond', [GovAgency\ImplanController::class, 'respond'])->name('implan.respond');
    Route::put('/implan/{implan}', [GovAgency\ImplanController::class, 'update'])->name('implan.update');
    Route::post('/implan/{implan}/agenda', [GovAgency\ImplanController::class, 'uploadAgenda'])->name('implan.agenda');
    Route::post('/implan/{implan}/photos', [GovAgency\ImplanController::class, 'uploadPhoto'])->name('implan.photos');
});

Route::middleware(['auth', 'role:mblrc'])->prefix('mblrc')->name('mblrc.')->group(function () {
    Route::get('/', [Mblrc\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [Mblrc\DashboardController::class, 'analytics'])->name('analytics');
    Route::get('/statistics', [Mblrc\DashboardController::class, 'statistics'])->name('statistics');
    Route::get('/fr-locations', [Mblrc\FormerRebelController::class, 'locations'])->name('fr.locations');
    Route::get('/barangays', [Mblrc\FormerRebelController::class, 'barangays'])->name('barangays');
    Route::get('/skills-suggestions', [Mblrc\ProfileActionController::class, 'skillSuggestions'])->name('fr.skills.suggestions');

    Route::get('/former-rebels', [Mblrc\FormerRebelController::class, 'index'])->name('fr.index');
    Route::get('/former-rebels/create', [Mblrc\FormerRebelController::class, 'create'])->name('fr.create');
    Route::post('/former-rebels', [Mblrc\FormerRebelController::class, 'store'])->name('fr.store');
    Route::get('/former-rebels/{formerRebel}', [Mblrc\FormerRebelController::class, 'show'])->name('fr.show');
    Route::get('/former-rebels/{formerRebel}/edit', [Mblrc\FormerRebelController::class, 'edit'])->name('fr.edit');
    Route::put('/former-rebels/{formerRebel}', [Mblrc\FormerRebelController::class, 'update'])->name('fr.update');
    Route::delete('/former-rebels/{formerRebel}', [Mblrc\FormerRebelController::class, 'destroy'])->name('fr.destroy');

    // Profile widget actions
    Route::put('/former-rebels/{formerRebel}/program-status', [Mblrc\ProfileActionController::class, 'updateProgramStatus'])->name('fr.program-status.update');
    Route::post('/former-rebels/{formerRebel}/location', [Mblrc\ProfileActionController::class, 'saveLocation'])->name('fr.location.save');
    Route::get('/former-rebels/{formerRebel}/location-history', [Mblrc\ProfileActionController::class, 'locationHistory'])->name('fr.location.history');
    Route::post('/former-rebels/{formerRebel}/skills', [Mblrc\ProfileActionController::class, 'storeSkill'])->name('fr.skills.store');
    Route::delete('/skills/{skill}', [Mblrc\ProfileActionController::class, 'destroySkill'])->name('fr.skills.destroy');
    Route::post('/former-rebels/{formerRebel}/assistance', [Mblrc\ProfileActionController::class, 'storeAssistance'])->name('fr.assistance.store');
    Route::delete('/assistance/{assistance}', [Mblrc\ProfileActionController::class, 'destroyAssistance'])->name('fr.assistance.destroy');
    Route::post('/former-rebels/{formerRebel}/education-work', [Mblrc\ProfileActionController::class, 'updateEducationWork'])->name('fr.education.update');
});

Route::middleware(['auth', 'role:39th_ib'])->prefix('39th-ib')->name('ib39.')->group(function () {
    Route::get('/', [Ib39\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/fr-profiles', [Ib39\SurfacedFormerRebelController::class, 'index'])->name('fr-profiles.index');
    Route::get('/fr-profiles/create', [Ib39\SurfacedFormerRebelController::class, 'create'])->name('fr-profiles.create');
    Route::post('/fr-profiles', [Ib39\SurfacedFormerRebelController::class, 'store'])->name('fr-profiles.store');
    Route::get('/fr-profiles/{ib39SurfacedFormerRebel}', [Ib39\SurfacedFormerRebelController::class, 'show'])->name('fr-profiles.show');
    Route::get('/fr-profiles/{ib39SurfacedFormerRebel}/documents/cdr', [Ib39\SurfacedFormerRebelController::class, 'cdr'])->name('fr-profiles.records.cdr');
    Route::get('/fr-profiles/{ib39SurfacedFormerRebel}/documents/pswdo-enrollment', [Ib39\SurfacedFormerRebelController::class, 'pswdo'])->name('fr-profiles.records.pswdo');
    Route::get('/fr-profiles/{ib39SurfacedFormerRebel}/documents/fea', [Ib39\SurfacedFormerRebelController::class, 'fea'])->name('fr-profiles.records.fea');
    Route::get('/fr-profiles/{ib39SurfacedFormerRebel}/assistance', [Ib39\SurfacedFormerRebelController::class, 'assistance'])->name('fr-profiles.records.assistance');
    Route::get('/fr-profiles/{ib39SurfacedFormerRebel}/documents/japic-certification', [Ib39\SurfacedFormerRebelController::class, 'certification'])->name('fr-profiles.records.certification');
    Route::post('/fr-profiles/{ib39SurfacedFormerRebel}/cancel', Ib39\SurfacedFormerRebelCancellationController::class)->name('fr-profiles.cancel');

    Route::get('/cdr/{cdr}', [Ib39\CdrController::class, 'show'])->name('cdr.show');
    Route::post('/cdr/{cdr}/start', [Ib39\CdrController::class, 'start'])->name('cdr.start');
    Route::get('/cdr/{cdr}/edit', [Ib39\CdrController::class, 'edit'])->name('cdr.edit');
    Route::put('/cdr/{cdr}/draft', [Ib39\CdrController::class, 'update'])->name('cdr.update');
    Route::get('/cdr/{cdr}/preview', [Ib39\CdrController::class, 'preview'])->name('cdr.preview');
    Route::get('/cdr/{cdr}/print', [Ib39\CdrController::class, 'print'])->name('cdr.print');
    Route::get('/cdr/{cdr}/finalization-review', [Ib39\CdrController::class, 'finalizationReview'])->name('cdr.finalization.review');
    Route::post('/cdr/{cdr}/finalize', [Ib39\CdrController::class, 'finalize'])->name('cdr.finalize');
    Route::post('/cdr/{cdr}/final-document', [Ib39\CdrDocumentController::class, 'upload'])->name('cdr.documents.upload');
    Route::post('/cdr/{cdr}/final-document/replacement', [Ib39\CdrDocumentController::class, 'replace'])->name('cdr.documents.replace');
    Route::get('/cdr/{cdr}/document-versions/{version}/preview', [Ib39\CdrDocumentController::class, 'preview'])->name('cdr.documents.preview');
    Route::get('/cdr/{cdr}/document-versions/{version}/download', [Ib39\CdrDocumentController::class, 'download'])->name('cdr.documents.download');
    Route::get('/cdr/{cdr}/document-versions/{version}/print', [Ib39\CdrDocumentController::class, 'print'])->name('cdr.documents.print');
    Route::post('/cdr/{cdr}/photos', [Ib39\CdrPhotoController::class, 'store'])->name('cdr.photos.store');
    Route::get('/cdr-photo-versions/{photoVersion}/preview', [Ib39\CdrPhotoController::class, 'show'])->name('cdr.photos.show');

    Route::get('/fea', [Ib39\FeaProcessingController::class, 'index'])->name('fea.index');
    Route::get('/fea/{fea}', [Ib39\FeaProcessingController::class, 'show'])->name('fea.show');
    Route::post('/fea/{fea}/documents/{document}/start', [Ib39\FeaDocumentController::class, 'start'])->name('fea.documents.start');
    Route::patch('/fea/{fea}/documents/{document}', [Ib39\FeaDocumentController::class, 'update'])->name('fea.documents.update');
    Route::get('/fea/{fea}/documents/{document}/draft', [Ib39\FeaDraftController::class, 'edit'])->name('fea.documents.draft.edit');
    Route::put('/fea/{fea}/documents/{document}/draft', [Ib39\FeaDraftController::class, 'update'])->name('fea.documents.draft.update');
    Route::get('/fea/{fea}/documents/{document}/draft/preview', [Ib39\FeaDraftController::class, 'preview'])->name('fea.documents.draft.preview');
    Route::get('/fea/{fea}/documents/{document}/draft/print', [Ib39\FeaDraftController::class, 'print'])->name('fea.documents.draft.print');
    Route::post('/fea/{fea}/documents/{document}/draft-versions', [Ib39\FeaUploadController::class, 'store'])->name('fea.documents.versions.store');
    Route::post('/fea/{fea}/documents/{document}/final-versions', [Ib39\FeaUploadController::class, 'storeFinal'])->name('fea.documents.final-versions.store');
    Route::post('/fea/{fea}/documents/{document}/comparison-photo-versions', [Ib39\FeaUploadController::class, 'storeComparison'])->name('fea.documents.comparison-versions.store');
    Route::post('/fea/{fea}/documents/{document}/surrendered-photo-versions', [Ib39\FeaUploadController::class, 'storeSurrendered'])->name('fea.documents.surrendered-versions.store');
    Route::get('/fea/{fea}/documents/{document}/draft-versions/{version}/preview', [Ib39\FeaUploadController::class, 'preview'])->name('fea.documents.versions.preview');
    Route::get('/fea/{fea}/documents/{document}/draft-versions/{version}/download', [Ib39\FeaUploadController::class, 'download'])->name('fea.documents.versions.download');
    Route::get('/japic-certifications/{japicCertificationProcessing}/document-versions/{version}/preview', [Japic\CertificationDocumentController::class, 'previewFinal'])->name('japic-certifications.document-versions.preview');
    Route::get('/japic-certifications/{japicCertificationProcessing}/document-versions/{version}/download', [Japic\CertificationDocumentController::class, 'downloadFinal'])->name('japic-certifications.document-versions.download');
    Route::get('/pswdo-enrollments/{pswdoEnrollment}/documents/{document}/preview', [Pswdo\EnrollmentDocumentController::class, 'preview'])->name('pswdo-enrollment-documents.preview');
    Route::get('/pswdo-enrollments/{pswdoEnrollment}/documents/{document}/download', [Pswdo\EnrollmentDocumentController::class, 'download'])->name('pswdo-enrollment-documents.download');

    Route::get('/areas', [Ib39\AreaController::class, 'index'])->name('areas.index');
    Route::post('/areas', [Ib39\AreaController::class, 'store'])->name('areas.store');
    Route::put('/areas/{area}', [Ib39\AreaController::class, 'update'])->name('areas.update');
    Route::delete('/areas/{area}', [Ib39\AreaController::class, 'destroy'])->name('areas.destroy');
    Route::get('/barangays', [Ib39\AreaController::class, 'barangays'])->name('barangays');
    Route::get('/map', [Ib39\AreaController::class, 'map'])->name('map');
    Route::get('/area-data', [Ib39\AreaController::class, 'areaData'])->name('area.data');
    Route::get('/barangay-data', [Ib39\AreaController::class, 'barangayData'])->name('barangay.data');
});

Route::middleware(['auth', 'role:japic'])->prefix('japic')->name('japic.')->group(function () {
    Route::get('/', [Japic\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/certifications', [Japic\CertificationController::class, 'index'])->name('certifications.index');
    Route::get('/certifications/{japicCertificationProcessing}', [Japic\CertificationController::class, 'show'])->name('certifications.show');
    Route::get('/certifications/{japicCertificationProcessing}/documents/cdr', [Japic\CertificationController::class, 'cdr'])->name('certifications.records.cdr');
    Route::get('/certifications/{japicCertificationProcessing}/documents/pswdo-enrollment', [Japic\CertificationController::class, 'pswdo'])->name('certifications.records.pswdo');
    Route::get('/certifications/{japicCertificationProcessing}/documents/fea', [Japic\CertificationController::class, 'fea'])->name('certifications.records.fea');
    Route::get('/certifications/{japicCertificationProcessing}/assistance', [Japic\CertificationController::class, 'assistance'])->name('certifications.records.assistance');
    Route::get('/certifications/{japicCertificationProcessing}/documents/certification', [Japic\CertificationController::class, 'certification'])->name('certifications.records.certification');
    Route::get('/certifications/{japicCertificationProcessing}/history/{revision?}', [Japic\CertificationController::class, 'history'])->whereNumber('revision')->name('certifications.history');
    Route::get('/certifications/{japicCertificationProcessing}/draft', [Japic\CertificationDraftController::class, 'edit'])->name('certifications.draft.edit');
    Route::put('/certifications/{japicCertificationProcessing}/draft', [Japic\CertificationDraftController::class, 'update'])->name('certifications.draft.update');
    Route::post('/certifications/{processing}/photo-versions', [Japic\CertificationPhotoController::class, 'store'])->name('certifications.photos.store');
    Route::get('/certifications/{processing}/photo-versions/{photoVersion}', [Japic\CertificationPhotoController::class, 'show'])->name('certifications.photos.show');
    Route::get('/certifications/{japicCertificationProcessing}/preview', [Japic\CertificationDocumentController::class, 'preview'])->name('certifications.preview');
    Route::get('/certifications/{japicCertificationProcessing}/print', [Japic\CertificationDocumentController::class, 'print'])->name('certifications.print');
    Route::post('/certifications/{japicCertificationProcessing}/final-document', [Japic\CertificationDocumentController::class, 'uploadFinal'])->name('certifications.final-document.upload');
    Route::get('/certifications/{japicCertificationProcessing}/document-versions/{version}/preview', [Japic\CertificationDocumentController::class, 'previewFinal'])->name('certifications.document-versions.preview');
    Route::get('/certifications/{japicCertificationProcessing}/document-versions/{version}/download', [Japic\CertificationDocumentController::class, 'downloadFinal'])->name('certifications.document-versions.download');
    Route::post('/certifications/{japicCertificationProcessing}/submit-for-signing', [Japic\CertificationWorkflowController::class, 'submitForSigning'])->name('certifications.submit-for-signing');
    Route::post('/certifications/{japicCertificationProcessing}/signing-complete', [Japic\CertificationWorkflowController::class, 'signingComplete'])->name('certifications.signing-complete');

    Route::get('/cdr/{cdr}/document-versions/{version}/preview', [Ib39\CdrDocumentController::class, 'preview'])->name('cdr.documents.preview');
    Route::get('/cdr/{cdr}/document-versions/{version}/download', [Ib39\CdrDocumentController::class, 'download'])->name('cdr.documents.download');
    Route::get('/fea/{fea}/documents/{document}/versions/{version}/preview', [Ib39\FeaUploadController::class, 'preview'])->name('fea.documents.versions.preview');
    Route::get('/fea/{fea}/documents/{document}/versions/{version}/download', [Ib39\FeaUploadController::class, 'download'])->name('fea.documents.versions.download');
    Route::get('/pswdo-enrollments/{pswdoEnrollment}/documents/{document}/preview', [Pswdo\EnrollmentDocumentController::class, 'preview'])->name('pswdo-enrollment-documents.preview');
    Route::get('/pswdo-enrollments/{pswdoEnrollment}/documents/{document}/download', [Pswdo\EnrollmentDocumentController::class, 'download'])->name('pswdo-enrollment-documents.download');
});

Route::middleware(['auth', 'role:pswdo'])->prefix('pswdo')->name('pswdo.')->group(function () {
    Route::get('/', [Pswdo\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/enrollments', [Pswdo\EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('/enrollments/{pswdoEnrollment}', [Pswdo\EnrollmentController::class, 'show'])->name('enrollments.show');
    Route::get('/enrollments/{pswdoEnrollment}/workspace', [Pswdo\EnrollmentController::class, 'workspace'])->name('enrollments.workspace');
    Route::get('/enrollments/{pswdoEnrollment}/documents/cdr', [Pswdo\EnrollmentController::class, 'cdr'])->name('enrollments.records.cdr');
    Route::get('/enrollments/{pswdoEnrollment}/documents/japic-certification', [Pswdo\EnrollmentController::class, 'certification'])->name('enrollments.records.certification');
    Route::get('/enrollments/{pswdoEnrollment}/documents/pswdo-enrollment', [Pswdo\EnrollmentController::class, 'pswdo'])->name('enrollments.records.pswdo');
    Route::get('/enrollments/{pswdoEnrollment}/documents/fea', [Pswdo\EnrollmentController::class, 'fea'])->name('enrollments.records.fea');
    Route::get('/enrollments/{pswdoEnrollment}/assistance', [Pswdo\EnrollmentController::class, 'assistance'])->name('enrollments.records.assistance');
    Route::post('/enrollments/{pswdoEnrollment}/documents/{documentType}/final-document', [Pswdo\EnrollmentDocumentController::class, 'store'])->name('enrollments.documents.store');
    Route::get('/enrollments/{pswdoEnrollment}/documents/{document}/preview', [Pswdo\EnrollmentDocumentController::class, 'preview'])->name('enrollments.documents.preview');
    Route::get('/enrollments/{pswdoEnrollment}/documents/{document}/download', [Pswdo\EnrollmentDocumentController::class, 'download'])->name('enrollments.documents.download');

    Route::get('/cdr/{cdr}/document-versions/{version}/preview', [Ib39\CdrDocumentController::class, 'preview'])->name('cdr.documents.preview');
    Route::get('/cdr/{cdr}/document-versions/{version}/download', [Ib39\CdrDocumentController::class, 'download'])->name('cdr.documents.download');
    Route::get('/japic-certifications/{japicCertificationProcessing}/document-versions/{version}/preview', [Japic\CertificationDocumentController::class, 'previewFinal'])->name('japic.document-versions.preview');
    Route::get('/japic-certifications/{japicCertificationProcessing}/document-versions/{version}/download', [Japic\CertificationDocumentController::class, 'downloadFinal'])->name('japic.document-versions.download');
    Route::get('/fea/{fea}/documents/{document}/versions/{version}/preview', [Ib39\FeaUploadController::class, 'preview'])->name('fea.documents.versions.preview');
    Route::get('/fea/{fea}/documents/{document}/versions/{version}/download', [Ib39\FeaUploadController::class, 'download'])->name('fea.documents.versions.download');
});

Route::middleware(['auth', 'role:afp'])->prefix('afp')->name('afp.')->group(function () {
    Route::get('/', [Afp\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/rcsp', [Afp\RcspController::class, 'index'])->name('rcsp.index');
});

require __DIR__.'/auth.php';
