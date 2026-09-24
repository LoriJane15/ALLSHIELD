<?php

namespace App\Http\Controllers\Admin;

use App\Events\RcspCommentPosted;
use App\Http\Controllers\Controller;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspFileComment;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

/**
 * Katuparan Center review of LGU-submitted RCSP monitoring forms:
 * approve / disapprove each activity, leave remarks and threaded comments.
 */
class RcspReviewController extends Controller
{
    public function index(): View
    {
        $barangays = RcspBarangay::with(['barangay', 'municipality'])
            ->whereHas('forms')
            ->get();

        // classify each barangay by its form statuses
        $formStatuses = RcspForm::select('rcsp_barangay_id', 'status')
            ->get()
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
        $rcspBarangay->load('barangay', 'municipality');
        $phases = RcspPhase::orderBy('number')->get();

        $phaseNumber = $request->integer('phase', $rcspBarangay->current_phase);
        $currentPhase = $phases->firstWhere('number', $phaseNumber) ?? $phases->first();

        $activities = RcspActivity::where('rcsp_phase_id', $currentPhase->id)->orderBy('id')->get();

        $forms = RcspForm::where('rcsp_barangay_id', $rcspBarangay->id)
            ->where('rcsp_phase_id', $currentPhase->id)
            ->get()->groupBy('rcsp_activity_id')
            ->map(fn ($g) => $g->sortByDesc('id')->first());

        $comments = RcspFileComment::with('user')
            ->whereIn('rcsp_activity_id', $activities->pluck('id'))
            ->where('rcsp_phase_id', $currentPhase->id)
            ->orderBy('id')
            ->get()->groupBy('rcsp_activity_id');

        return view('admin.rcsp.show', compact(
            'rcspBarangay', 'phases', 'currentPhase', 'activities', 'forms', 'comments'
        ));
    }

    /** Full-page file viewer with side-by-side comment thread. */
    public function file(RcspForm $form): View
    {
        $form->load([
            'rcspBarangay.barangay', 'rcspBarangay.municipality',
            'phase', 'activity', 'lguUser', 'fileComments.user',
        ]);

        return view('admin.rcsp.file', compact('form'));
    }

    public function updateStatus(Request $request, RcspBarangay $rcspBarangay): RedirectResponse
    {
        $data = $request->validate([
            'phase_id' => ['required', 'exists:rcsp_phases,id'],
            'statuses' => ['required', 'array'],
            'statuses.*' => [Rule::in(['approved', 'disapproved', 'to be complied', 'to be conducted'])],
            'remarks' => ['array'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['statuses'] as $formId => $status) {
                $form = RcspForm::find($formId);
                if ($form) {
                    $form->update([
                        'status' => $status,
                        'remarks' => $data['remarks'][$formId] ?? $form->remarks,
                    ]);
                }
            }
        });

        return back()->with('success', 'Review saved.');
    }

    public function storeComment(Request $request, RcspForm $form): JsonResponse
    {
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
                'at' => $comment->created_at->diffForHumans(),
            ],
        ]);
    }
}
