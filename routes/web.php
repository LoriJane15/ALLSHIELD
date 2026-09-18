<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Afp;
use App\Http\Controllers\GovAgency;
use App\Http\Controllers\Ib39;
use App\Http\Controllers\Lgu;
use App\Http\Controllers\Mblrc;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin;
use Illuminate\Support\Facades\Route;

// Public landing page — original static site copied to public/landing/.
Route::get('/', fn () => response()->file(public_path('landing/index.html')))->name('landing');

// Shared authenticated routes.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
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
    Route::delete('/users/{user}', [SuperAdmin\UserController::class, 'destroy'])->name('users.destroy');

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
    Route::get('/boundaries', [Admin\DashboardController::class, 'boundaries'])->name('boundaries');
});

Route::middleware(['auth', 'role:lgu'])->prefix('lgu')->name('lgu.')->group(function () {
    Route::get('/', [Lgu\DashboardController::class, 'index'])->name('dashboard');

    // RCSP barangays
    Route::get('/rcsp', [Lgu\RcspBarangayController::class, 'index'])->name('rcsp.index');
    Route::post('/rcsp', [Lgu\RcspBarangayController::class, 'store'])->name('rcsp.store');
    Route::delete('/rcsp/{rcspBarangay}', [Lgu\RcspBarangayController::class, 'destroy'])->name('rcsp.destroy');

    // Monitoring form (per barangay)
    Route::get('/rcsp/{rcspBarangay}/monitoring', [Lgu\MonitoringController::class, 'show'])->name('monitoring.show');
    Route::post('/rcsp/{rcspBarangay}/monitoring', [Lgu\MonitoringController::class, 'submit'])->name('monitoring.submit');
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
    Route::get('/areas', [Ib39\AreaController::class, 'index'])->name('areas.index');
    Route::post('/areas', [Ib39\AreaController::class, 'store'])->name('areas.store');
    Route::put('/areas/{area}', [Ib39\AreaController::class, 'update'])->name('areas.update');
    Route::delete('/areas/{area}', [Ib39\AreaController::class, 'destroy'])->name('areas.destroy');
    Route::get('/barangays', [Ib39\AreaController::class, 'barangays'])->name('barangays');
    Route::get('/map', [Ib39\AreaController::class, 'map'])->name('map');
    Route::get('/map-data', [Ib39\AreaController::class, 'mapData'])->name('map.data');
    Route::get('/area-data', [Ib39\AreaController::class, 'areaData'])->name('area.data');
    Route::get('/barangay-data', [Ib39\AreaController::class, 'barangayData'])->name('barangay.data');
    // Barangay polygon geometry, served from the DB (editable) instead of the file.
    Route::get('/boundaries', [Ib39\AreaController::class, 'boundaries'])->name('boundaries');
    Route::get('/boundary-editor', [Ib39\AreaController::class, 'editor'])->name('boundaries.editor');
    Route::post('/boundaries', [Ib39\AreaController::class, 'storeBoundary'])->name('boundaries.store');
    Route::put('/boundaries/{area}', [Ib39\AreaController::class, 'updateBoundary'])->name('boundaries.update');
    Route::delete('/boundaries/{area}', [Ib39\AreaController::class, 'destroyBoundary'])->name('boundaries.destroy');
    Route::get('/boundaries-export', [Ib39\AreaController::class, 'exportBoundaries'])->name('boundaries.export');
    Route::post('/boundaries-import', [Ib39\AreaController::class, 'importBoundaries'])->name('boundaries.import');
});

Route::middleware(['auth', 'role:afp'])->prefix('afp')->name('afp.')->group(function () {
    Route::get('/', [Afp\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/rcsp', [Afp\RcspController::class, 'index'])->name('rcsp.index');
});

require __DIR__.'/auth.php';
