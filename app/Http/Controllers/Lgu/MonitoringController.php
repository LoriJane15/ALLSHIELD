<?php

namespace App\Http\Controllers\Lgu;

use App\Events\RcspCommentPosted;
use App\Http\Controllers\Controller;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * RCSP phased monitoring form. A barangay progresses through 6 phases (0-5);
 * each phase has activities the LGU reports on (conduct + evidence file),
 * which the Katuparan admin later approves/disapproves.
 */
class MonitoringController extends Controller
{
    public function show(RcspBarangay $rcspBarangay): View
    {
        $this->authorizeMunicipality($rcspBarangay);
        $rcspBarangay->load('barangay', 'municipality');

        $phases = RcspPhase::orderBy('number')->get();
        $currentPhase = $phases->firstWhere('number', $rcspBarangay->current_phase) ?? $phases->first();

        $activities = RcspActivity::where('rcsp_phase_id', $currentPhase->id)
            ->orderBy('id')->get();

        // latest form row per activity for this barangay + phase (+ its comment thread)
        $forms = RcspForm::where('rcsp_barangay_id', $rcspBarangay->id)
            ->where('rcsp_phase_id', $currentPhase->id)
            ->with('fileComments.user')
            ->get()
            ->groupBy('rcsp_activity_id')
            ->map(fn ($g) => $g->sortByDesc('id')->first());

        // approved phases (all activities approved) for the "View Phases" summary
        $approvedForms = RcspForm::where('rcsp_barangay_id', $rcspBarangay->id)
            ->where('status', 'approved')
            ->with('activity', 'phase')
            ->get()
            ->groupBy('rcsp_phase_id');

        return view('lgu.monitoring.show', compact(
            'rcspBarangay', 'phases', 'currentPhase', 'activities', 'forms', 'approvedForms'
        ));
    }

    public function submit(Request $request, RcspBarangay $rcspBarangay): RedirectResponse
    {
        $this->authorizeMunicipality($rcspBarangay);

        $phaseId = (int) $request->input('phase_id');
        $activities = RcspActivity::where('rcsp_phase_id', $phaseId)->pluck('id');

        abort_if($activities->isEmpty(), 422, 'No activities for this phase.');

        // One file input per activity; the legacy app checked MIME + size via
        // security_helpers::validateFileUpload(), so keep an equivalent rule here.
        $request->validate(
            $activities->mapWithKeys(fn ($id) => [
                "file_{$id}" => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:25600'],
            ])->all()
        );

        DB::transaction(function () use ($request, $rcspBarangay, $phaseId, $activities) {
            foreach ($activities as $activityId) {
                $existing = RcspForm::where('rcsp_barangay_id', $rcspBarangay->id)
                    ->where('rcsp_phase_id', $phaseId)
                    ->where('rcsp_activity_id', $activityId)
                    ->orderByDesc('id')->first();

                // Don't overwrite an already-approved activity.
                if ($existing && $existing->status === 'approved') {
                    continue;
                }

                $conduct = $request->input("conduct_{$activityId}");
                $filePath = $existing?->file;

                if ($request->hasFile("file_{$activityId}")) {
                    $filePath = $request->file("file_{$activityId}")
                        ->store("rcsp/{$rcspBarangay->id}", 'public');
                }

                $payload = [
                    'lgu_user_id' => $request->user()->id,
                    'conduct' => $conduct,
                    'file' => $filePath,
                    'status' => 'submitted',
                ];

                if ($existing) {
                    $existing->update($payload);
                } else {
                    RcspForm::create(array_merge($payload, [
                        'rcsp_barangay_id' => $rcspBarangay->id,
                        'rcsp_phase_id' => $phaseId,
                        'rcsp_activity_id' => $activityId,
                    ]));
                }
            }

            if ($rcspBarangay->status === 'Pending') {
                $rcspBarangay->update(['status' => 'Ongoing']);
            }
        });

        return redirect()->route('lgu.monitoring.show', $rcspBarangay)
            ->with('success', 'Phase submitted for review.');
    }

    public function proceed(Request $request, RcspBarangay $rcspBarangay): RedirectResponse
    {
        $this->authorizeMunicipality($rcspBarangay);

        $phase = $rcspBarangay->current_phase;
        $activityCount = RcspActivity::whereHas('phase', fn ($q) => $q->where('number', $phase))->count();
        $phaseId = RcspPhase::where('number', $phase)->value('id');

        $approvedCount = RcspForm::where('rcsp_barangay_id', $rcspBarangay->id)
            ->where('rcsp_phase_id', $phaseId)
            ->where('status', 'approved')
            ->distinct('rcsp_activity_id')->count('rcsp_activity_id');

        if ($approvedCount < $activityCount) {
            return back()->with('error', 'All activities must be approved before proceeding.');
        }

        // mark this phase complete
        $rcspBarangay->phaseStatus()->updateOrCreate([], ["phase{$phase}_completed" => true]);

        if ($phase >= 5) {
            $rcspBarangay->update(['status' => 'Completed']);
            return back()->with('success', 'RCSP monitoring completed for this barangay.');
        }

        $rcspBarangay->update(['current_phase' => $phase + 1]);

        return back()->with('success', "Advanced to phase ".($phase + 1).".");
    }

    /** Full-page file viewer with side-by-side comment thread. */
    public function file(RcspForm $form): View
    {
        $form->load(['rcspBarangay.barangay', 'rcspBarangay.municipality', 'phase', 'activity', 'lguUser', 'fileComments.user']);
        $this->authorizeMunicipality($form->rcspBarangay);

        return view('lgu.monitoring.file', compact('form'));
    }

    /** LGU posts a comment on a submitted form (two-way thread with the reviewer). */
    public function storeComment(Request $request, RcspForm $form): JsonResponse
    {
        $this->authorizeMunicipality($form->rcspBarangay);

        $data = $request->validate(['text' => ['required', 'string']]);

        $comment = $form->fileComments()->create([
            'rcsp_phase_id' => $form->rcsp_phase_id,
            'rcsp_activity_id' => $form->rcsp_activity_id,
            'user_id' => $request->user()->id,
            'text' => $data['text'],
        ]);

        broadcast(new RcspCommentPosted($comment))->toOthers();

        return response()->json([
            'success' => true,
            'comment' => [
                'text' => $comment->text,
                'user' => $request->user()->name,
                'role' => $request->user()->role,
                'at' => $comment->created_at->diffForHumans(),
            ],
        ]);
    }

    private function authorizeMunicipality(RcspBarangay $rcspBarangay): void
    {
        abort_unless(
            $rcspBarangay->municipality_id === auth()->user()->municipality_id,
            403,
            'This barangay is outside your municipality.'
        );
    }
}
