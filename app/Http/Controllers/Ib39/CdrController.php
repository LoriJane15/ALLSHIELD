<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39CdrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\FinalizeCdrRequest;
use App\Http\Requests\Ib39\SaveCdrDraftRequest;
use App\Http\Requests\Ib39\StartCdrProcessingRequest;
use App\Http\Requests\Ib39\ViewCdrVersionHistoryRequest;
use App\Models\Ib39CdrPhotoVersion;
use App\Models\Ib39CdrProcessing;
use App\Services\Ib39CdrDraftService;
use App\Services\Ib39CdrFinalizationService;
use App\Services\Ib39CdrStatusService;
use App\Support\Ib39CdrFormSchema;
use App\Support\Ib39CdrMissingFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CdrController extends Controller
{
    public function show(ViewCdrVersionHistoryRequest $request, Ib39CdrProcessing $cdr): Response
    {
        return $this->privateView('ib39.cdr.show', ['cdr' => $this->loadWorkspace($cdr)]);
    }

    public function start(
        StartCdrProcessingRequest $request,
        Ib39CdrProcessing $cdr,
        Ib39CdrStatusService $statuses,
    ): RedirectResponse {
        $statuses->start($cdr, $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('ib39.cdr.edit', $cdr)->with('status', 'CDR processing started.');
    }

    public function edit(Ib39CdrProcessing $cdr): Response
    {
        Gate::authorize('updateDraft', $cdr);

        return $this->privateView('ib39.cdr.edit', [
            'cdr' => $this->loadWorkspace($cdr),
            'sections' => Ib39CdrFormSchema::sections(),
            'repeatableSections' => Ib39CdrFormSchema::repeatableSections(),
            'orderedBlocks' => Ib39CdrFormSchema::orderedBlocks(),
            'defaultContent' => Ib39CdrFormSchema::defaultContent(),
        ]);
    }

    public function update(
        SaveCdrDraftRequest $request,
        Ib39CdrProcessing $cdr,
        Ib39CdrDraftService $drafts,
    ): RedirectResponse|JsonResponse {
        $drafts->save($cdr, $request->validatedContent(), $request->user(), $request->ip(), $request->userAgent());

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'saved' => true,
                'message' => 'CDR draft saved automatically.',
                'saved_at' => now()->toIso8601String(),
                'formatted_time' => now()->format('h:i:s A'),
            ]);
        }

        return redirect()->route('ib39.cdr.edit', $cdr)->with('status', 'CDR draft saved.');
    }

    public function preview(Ib39CdrProcessing $cdr): Response
    {
        Gate::authorize('previewDraft', $cdr);

        return $this->document($cdr, false);
    }

    public function print(Ib39CdrProcessing $cdr): Response
    {
        Gate::authorize('printDraft', $cdr);

        return $this->document($cdr, true);
    }

    public function finalizationReview(
        Ib39CdrProcessing $cdr,
        Ib39CdrMissingFields $missingFields,
        Ib39CdrFinalizationService $finalization,
    ): Response {
        Gate::authorize('finalize', $cdr);
        $cdr = $this->loadWorkspace($cdr);

        return $this->privateView('ib39.cdr.finalization-review', [
            'cdr' => $cdr,
            'missingFields' => $missingFields->analyze($cdr->form?->content ?? [], $cdr->photos->contains(fn ($photo) => $photo->currentVersion !== null)),
            'draftFingerprint' => $finalization->fingerprint($cdr),
        ]);
    }

    public function finalize(
        FinalizeCdrRequest $request,
        Ib39CdrProcessing $cdr,
        Ib39CdrFinalizationService $finalization,
    ): RedirectResponse {
        $finalization->finalize($cdr, $request->validated('draft_fingerprint'), $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('ib39.cdr.show', $cdr)->with('status', 'CDR finalized as an immutable system-authored document.');
    }

    private function document(Ib39CdrProcessing $cdr, bool $autoPrint): Response
    {
        $cdr = $this->loadWorkspace($cdr);
        $isFinal = $cdr->status === Ib39CdrStatus::Completed;
        $photoVersion = null;
        if ($isFinal) {
            abort_unless($cdr->currentFinalVersion !== null, 409, 'The completed CDR has no final document version.');
            $snapshot = $cdr->currentFinalVersion->content_snapshot ?? [];
            $content = $snapshot['content'] ?? [];
            $photoVersionId = $snapshot['fr_photo_version_id'] ?? null;
            if ($photoVersionId) {
                $photoVersion = Ib39CdrPhotoVersion::query()
                    ->whereHas('photo', fn ($query) => $query->where('cdr_processing_id', $cdr->id))
                    ->findOrFail($photoVersionId);
            }
        } else {
            $content = $cdr->form?->content ?? [];
            $photoVersion = $cdr->photos->first(fn ($photo) => $photo->currentVersion !== null)?->currentVersion;
        }

        return $this->privateView('ib39.cdr.document', [
            'cdr' => $cdr,
            'content' => array_replace(Ib39CdrFormSchema::defaultContent(), $content),
            'sections' => Ib39CdrFormSchema::sections(),
            'repeatableSections' => Ib39CdrFormSchema::repeatableSections(),
            'orderedBlocks' => Ib39CdrFormSchema::orderedBlocks(),
            'autoPrint' => $autoPrint,
            'isFinal' => $isFinal,
            'photoVersion' => $photoVersion,
        ]);
    }

    private function loadWorkspace(Ib39CdrProcessing $cdr): Ib39CdrProcessing
    {
        return $cdr->load([
            'surfacedFormerRebel',
            'form.lastEditor',
            'currentFinalVersion',
            'documentVersions' => fn ($query) => $query->with(['creator', 'replacesVersion'])->orderByDesc('version_number'),
            'photos.currentVersion',
            'statusHistories' => fn ($query) => $query->latest(),
        ]);
    }

    private function privateView(string $view, array $data): Response
    {
        return response()->view($view, $data)->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
