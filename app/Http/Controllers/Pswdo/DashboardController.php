<?php

namespace App\Http\Controllers\Pswdo;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Http\Controllers\Controller;
use App\Models\PswdoEnrollment;
use App\Services\PswdoEligibilityService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(PswdoEligibilityService $eligibility): View
    {
        Gate::authorize('viewAny', PswdoEnrollment::class);
        $eligible = PswdoEnrollment::query()->whereHas('surfacedFormerRebel', fn ($query) => $eligibility->apply($query));
        $total = (clone $eligible)->count();
        $completed = (clone $eligible)->whereHas('documents', fn ($query) => $query
            ->selectRaw('1')->groupBy('pswdo_enrollment_id')->havingRaw('COUNT(DISTINCT document_type) = ?', [count(PswdoEnrollmentDocumentType::cases())]))->count();

        return view('pswdo.dashboard', [
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
        ]);
    }
}
