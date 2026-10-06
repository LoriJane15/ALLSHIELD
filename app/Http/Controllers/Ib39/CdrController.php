<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39CdrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\SaveCdrDraftRequest;
use App\Http\Requests\Ib39\StartCdrProcessingRequest;
use App\Models\Ib39CdrProcessing;
use App\Services\Ib39CdrDraftService;
use App\Services\Ib39CdrStatusService;
use App\Support\Ib39CdrFormSchema;
use App\Support\OfficialDocumentPdf;
use App\Support\ProcessingWorkspaceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CdrController extends Controller
{
    public function show(Ib39CdrProcessing $cdr): Response|RedirectResponse
    {
        if (in_array($cdr->status, [Ib39CdrStatus::Pending, Ib39CdrStatus::Ongoing], true)) {
            if (Gate::allows('updateDraft', $cdr)) {
                return redirect()->route('ib39.cdr.edit', $cdr);
            }
        }

        return $this->workspace($cdr);
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

        return $this->draftingWorkspace($cdr, false);
    }

    public function workspace(Ib39CdrProcessing $cdr): Response|RedirectResponse
    {
        Gate::authorize('view', $cdr);

        if ($cdr->status !== Ib39CdrStatus::Completed) {
            if (Gate::allows('updateDraft', $cdr)) {
                return redirect()->route('ib39.cdr.edit', $cdr);
            }
            abort_if(request()->user()->hasRole('39th_ib'), 403);

            $cdr->loadMissing('surfacedFormerRebel.pswdoEnrollment', 'surfacedFormerRebel.japicCertificationProcessing');
            $record = $cdr->surfacedFormerRebel;
            $destination = request()->user()->hasRole('pswdo')
                ? route('pswdo.enrollments.show', $record->pswdoEnrollment)
                : route('japic.certifications.show', $record->japicCertificationProcessing);

            return redirect($destination)->with('error', 'Document is not available yet.');
        }

        return $this->draftingWorkspace($cdr, true, ! request()->user()->hasRole('39th_ib'));
    }

    private function draftingWorkspace(Ib39CdrProcessing $cdr, bool $readOnly, bool $viewerPreview = false): Response
    {
        $cdr = $this->loadWorkspace($cdr);
        $uploadedFinalPreviewUrl = $viewerPreview
            ? ($cdr->finalDocument ? route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1])
                : ($cdr->form ? route('cdr.draft.preview', $cdr) : null))
            : null;

        return $this->privateView('ib39.cdr.edit', [
            'cdr' => $cdr,
            'sections' => Ib39CdrFormSchema::sections(),
            'repeatableSections' => Ib39CdrFormSchema::repeatableSections(),
            'orderedBlocks' => Ib39CdrFormSchema::orderedBlocks(),
            'defaultContent' => Ib39CdrFormSchema::defaultContent(),
            'draft' => $viewerPreview
                ? []
                : old('content', array_replace(Ib39CdrFormSchema::defaultContent(), $cdr->form?->content ?? [])),
            'readOnly' => $readOnly,
            'viewerPreview' => $viewerPreview,
            'uploadedFinalPreviewUrl' => $uploadedFinalPreviewUrl,
            'workspaceEvents' => ProcessingWorkspaceHistory::cdr($cdr),
        ]);
    }

    public function update(
        SaveCdrDraftRequest $request,
        Ib39CdrProcessing $cdr,
        Ib39CdrDraftService $drafts,
    ): RedirectResponse|JsonResponse {
        $drafts->save(
            $cdr,
            $request->validatedContent(),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
            $request->isManualSave(),
        );

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

    public function preview(Ib39CdrProcessing $cdr): Response|RedirectResponse
    {
        Gate::authorize('view', $cdr);
        if (! request()->user()->hasRole('39th_ib') && $cdr->status !== Ib39CdrStatus::Completed) {
            $cdr->loadMissing('surfacedFormerRebel.pswdoEnrollment', 'surfacedFormerRebel.japicCertificationProcessing');
            $record = $cdr->surfacedFormerRebel;
            $destination = request()->user()->hasRole('pswdo')
                ? route('pswdo.enrollments.show', $record->pswdoEnrollment)
                : route('japic.certifications.show', $record->japicCertificationProcessing);

            return redirect($destination)->with('error', 'Document is not available yet.');
        }
        Gate::authorize('previewDraft', $cdr);

        return $this->document($cdr, false);
    }

    public function print(Ib39CdrProcessing $cdr): Response
    {
        Gate::authorize('printDraft', $cdr);

        return $this->document($cdr, true);
    }

    public function downloadDraft(Ib39CdrProcessing $cdr, OfficialDocumentPdf $pdf): Response
    {
        Gate::authorize('downloadDraft', $cdr);
        $data = $this->draftDocumentData($cdr);
        $photoVersion = $data['photoVersion'];
        $photoSource = null;

        if ($photoVersion !== null) {
            abort_unless(in_array($photoVersion->mime_type, ['image/jpeg', 'image/png'], true), 422, 'The saved FR photo has an unsupported format.');
            abort_unless(Storage::disk('local')->exists($photoVersion->storage_path), 404, 'The saved FR photo is unavailable.');
            $photoSource = 'data:'.$photoVersion->mime_type.';base64,'.base64_encode(Storage::disk('local')->get($photoVersion->storage_path));
        }

        $filename = Str::slug($cdr->surfacedFormerRebel->reference_number, '_').'_CDR_Draft.pdf';

        return response($pdf->render(view('ib39.cdr.document', [
            ...$data,
            'autoPrint' => false,
            'canPrint' => false,
            'embedded' => false,
            'pdfMode' => true,
            'isFinal' => false,
            'photoSource' => $photoSource,
        ])->render()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    private function document(Ib39CdrProcessing $cdr, bool $autoPrint): Response
    {
        $data = $this->draftDocumentData($cdr);

        return $this->privateView('ib39.cdr.document', [
            ...$data,
            'autoPrint' => $autoPrint,
            'canPrint' => Gate::allows('printDraft', $cdr),
            'embedded' => request()->routeIs('cdr.draft.preview'),
            'pdfMode' => false,
            'isFinal' => false,
            'backUrl' => route('ib39.cdr.edit', $cdr),
            'printUrl' => route('ib39.cdr.print', $cdr),
            'downloadUrl' => route('ib39.cdr.download', $cdr),
        ]);
    }

    private function draftDocumentData(Ib39CdrProcessing $cdr): array
    {
        $cdr->loadMissing(['surfacedFormerRebel', 'form', 'photos.currentVersion']);

        return [
            'cdr' => $cdr,
            'content' => array_replace(Ib39CdrFormSchema::defaultContent(), $cdr->form?->content ?? []),
            'sections' => Ib39CdrFormSchema::sections(),
            'repeatableSections' => Ib39CdrFormSchema::repeatableSections(),
            'orderedBlocks' => Ib39CdrFormSchema::orderedBlocks(),
            'photoVersion' => $cdr->photos->first(fn ($photo) => $photo->currentVersion !== null)?->currentVersion,
        ];
    }

    private function loadWorkspace(Ib39CdrProcessing $cdr): Ib39CdrProcessing
    {
        return $cdr->loadMissing([
            'surfacedFormerRebel',
            'form.lastEditor',
            'finalDocument.creator:id,name,role',
            'photos.currentVersion',
            'statusHistories' => fn ($query) => $query
                ->with('user:id,name,role')
                ->oldest()
                ->orderBy('id'),
            'comments' => fn ($query) => $query
                ->with('user:id,name,role,logo,gov_agency_id', 'user.govAgency:id,profile')
                ->oldest()
                ->orderBy('id'),
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
