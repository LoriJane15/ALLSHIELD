<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rcsp\UpdateRcspTimelineRequest;
use App\Models\RcspBarangay;
use App\Services\RcspTimelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RcspTimelineController extends Controller
{
    public function show(Request $request, RcspBarangay $rcspBarangay, RcspTimelineService $timelineService): View
    {
        Gate::authorize('view', $rcspBarangay);

        $role = $request->user()->role;
        abort_unless(in_array($role, ['lgu', 'admin', 'afp'], true), 403);

        $timelineData = $timelineService->build($rcspBarangay);
        $canEdit = $request->user()->can('update', $rcspBarangay);
        $requestedPhase = old('phase_number');
        $editPhase = null;
        if ($canEdit && filter_var($requestedPhase, FILTER_VALIDATE_INT) !== false) {
            $phaseNumber = (int) $requestedPhase;
            if ($phaseNumber >= 0 && $phaseNumber <= 5) {
                $editPhase = $timelineData['rows']->firstWhere('number', $phaseNumber);
            }
        }

        return view('rcsp.timeline.show', [
            'rcspBarangay' => $rcspBarangay,
            'timelineData' => $timelineData,
            'canEdit' => $canEdit,
            'editPhase' => $editPhase,
            'layout' => $role === 'lgu' ? 'layouts.skydash-v' : 'layouts.skydash-h',
            'backRoute' => match ($role) {
                'lgu' => route('lgu.monitoring.show', $rcspBarangay),
                'admin' => route('admin.rcsp.show', $rcspBarangay),
                default => route('afp.rcsp.index'),
            },
            'updateRoute' => $role === 'lgu' ? route('lgu.rcsp.timeline.update', $rcspBarangay) : null,
        ]);
    }

    public function update(
        UpdateRcspTimelineRequest $request,
        RcspBarangay $rcspBarangay,
        RcspTimelineService $timelineService
    ): RedirectResponse {
        $timelineService->save($rcspBarangay, $request->user(), $request->validated());

        return redirect()->route('lgu.rcsp.timeline.show', $rcspBarangay)
            ->with('success', 'RCSP implementation timeline saved.');
    }
}
