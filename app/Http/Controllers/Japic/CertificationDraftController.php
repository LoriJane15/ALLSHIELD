<?php

namespace App\Http\Controllers\Japic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Japic\SaveCertificationDraftRequest;
use App\Models\JapicCertificationProcessing;
use App\Services\JapicCertificationDraftService;
use App\Support\JapicCertificationDraftSchema;
use App\Support\ProcessingWorkspaceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CertificationDraftController extends Controller
{
    public function edit(JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationDraftSchema $schema): View
    {
        Gate::authorize('editDraft', $japicCertificationProcessing);
        $japicCertificationProcessing->loadMissing([
            'draft.lastSavedBy', 'draftHistories.savedBy', 'currentPhotoVersion', 'surfacedFormerRebel.cancellation',
            'histories' => fn ($query) => $query->with(['actor:id,name,role', 'documentVersion:id,processing_id,version_number,original_filename'])->oldest('occurred_at')->oldest('id'),
            'comments' => fn ($query) => $query->with('user:id,name,role,logo,gov_agency_id', 'user.govAgency:id,profile')->oldest()->oldest('id'),
        ]);

        $payload = $japicCertificationProcessing->draft
            ? $schema->forReading($japicCertificationProcessing->draft->payload, $japicCertificationProcessing->control_number)
            : $schema->initial($schema->sourceSnapshot($japicCertificationProcessing), $japicCertificationProcessing->control_number, null);

        return view('japic.certifications.edit', [
            'processing' => $japicCertificationProcessing,
            'workspaceEvents' => ProcessingWorkspaceHistory::japic($japicCertificationProcessing),
            'payload' => $payload,
            'wording' => $schema->wording($payload),
            'affiliationPeriod' => data_get($payload, 'source_snapshot.affiliation_period'),
            'maxPersonnelRows' => JapicCertificationDraftSchema::MAX_PERSONNEL_ROWS,
        ]);
    }

    public function update(SaveCertificationDraftRequest $request, JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationDraftService $drafts): RedirectResponse|JsonResponse
    {
        $draft = $drafts->save($japicCertificationProcessing, $request->manualPayload(), $request->validated('control_number'),
            (int) $request->validated('revision'), (int) $request->validated('lock_version'), $request->validated('delay_reason'), $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'revision' => $draft->revision,
                'lock_version' => $japicCertificationProcessing->fresh()->lock_version,
                'message' => 'All changes saved',
            ]);
        }

        return redirect()->route('japic.certifications.workspace', $japicCertificationProcessing)->with('status', 'Draft revision '.$draft->revision.' saved securely.');
    }
}
