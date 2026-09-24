<?php

namespace App\Http\Controllers\Admin;

use App\Events\RcspCommentPosted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rcsp\ReviewRcspPhaseRequest;
use App\Http\Requests\Rcsp\StoreRcspCommentRequest;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use App\Services\RcspWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Katuparan Center review of LGU-submitted RCSP monitoring forms:
 * approve / disapprove each activity, leave remarks and threaded comments.
 */
class RcspReviewController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', RcspBarangay::class);
        $barangays = RcspBarangay::with(['barangay', 'municipality'])
            ->whereHas('forms')
            ->get();

        // Classify using only the latest immutable version of each activity.
        $formStatuses = RcspForm::select('id', 'rcsp_barangay_id', 'rcsp_activity_id', 'submission_version', 'status')
            ->orderByDesc('submission_version')->orderByDesc('id')->get()
            ->unique(fn (RcspForm $form) => $form->rcsp_barangay_id.'|'.$form->rcsp_activity_id)
            ->groupBy('rcsp_barangay_id')
            ->map(fn ($g) => $g->pluck('status')->unique());

        $tabs = ['pending' => collect(), 'returned' => collect(), 'approved' => collect(), 'completed' => collect()];
        foreach ($barangays as $b) {
            $statuses = $formStatuses[$b->id] ?? collect();
            if ($b->status === 'Completed') {
                $tabs['completed']->push($b);
            } elseif ($statuses->intersect(['disapproved', 'to be complied', 'to be conducted'])->isNotEmpty()) {
                $tabs['returned']->push($b);
            } elseif ($statuses->contains('submitted')) {
                $tabs['pending']->push($b);
            } else {
                $tabs['approved']->push($b);
            }
        }

        return view('admin.rcsp.index', compact('tabs'));
    }

    public function show(Request $request, RcspBarangay $rcspBarangay): View
    {
        Gate::authorize('view', $rcspBarangay);
        $rcspBarangay->load('barangay', 'municipality');
        $phases = RcspPhase::where('catalog_key', $rcspBarangay->catalog_key)->orderBy('number')->get();

        $phaseNumber = $request->integer('phase', $rcspBarangay->current_phase);
        $currentPhase = $phases->firstWhere('number', $phaseNumber) ?? $phases->first();
        abort_unless($currentPhase, 422, 'No phase catalog is available for this RCSP barangay.');

        $activities = RcspActivity::forBarangayPhase($rcspBarangay, $currentPhase)->orderBy('id')->get();

        $forms = RcspForm::where('rcsp_barangay_id', $rcspBarangay->id)
            ->where('rcsp_phase_id', $currentPhase->id)
            ->orderByDesc('submission_version')->orderByDesc('id')->get()->groupBy('rcsp_activity_id')
            ->map(fn ($group) => $group->first());

        return view('admin.rcsp.show', compact(
            'rcspBarangay', 'phases', 'currentPhase', 'activities', 'forms'
        ));
    }

    /** Full-page file viewer with side-by-side comment thread. */
    public function file(RcspForm $form): View
    {
        Gate::authorize('view', $form);
        $form->load([
            'rcspBarangay.barangay', 'rcspBarangay.municipality',
            'phase', 'activity', 'lguUser', 'fileComments.user',
        ]);

        return view('admin.rcsp.file', compact('form'));
    }

    public function updateStatus(ReviewRcspPhaseRequest $request, RcspBarangay $rcspBarangay, RcspWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validated();
        $workflow->reviewPhase($rcspBarangay, $request->user(), $data['statuses'], $data['remarks'] ?? []);

        return back()->with('success', 'Review saved.');
    }

    public function storeComment(StoreRcspCommentRequest $request, RcspForm $form): JsonResponse
    {
        $data = $request->validated();

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
                'id' => $comment->id,
                'user' => $request->user()->name,
                'role' => $request->user()->role,
                'user_logo' => $request->user()->logo
                    ? asset('assets/'.$request->user()->logo)
                    : asset('assets/img/kc-logo.svg'),
                'at' => $comment->created_at->diffForHumans(),
            ],
        ]);
    }
}
