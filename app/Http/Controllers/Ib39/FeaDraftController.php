<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39FeaDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\SaveFeaDraftRequest;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\Ib39FeaProcessing;
use App\Services\Ib39FeaDocumentWorkflowService;
use App\Services\Ib39FeaDraftSchema;
use App\Support\OfficialDocumentPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FeaDraftController extends Controller
{
    public function edit(Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaDraftSchema $schema): View
    {
        Gate::authorize('editDraft', [$document, $fea]);
        $fea->load('surfacedFormerRebel');
        $document->load([
            'draftSaver:id,name',
            'draftHistories' => fn ($query) => $query->with('actor:id,name')->latest('revision'),
            'versions.uploader:id,name',
            'currentSurrenderedPhotoVersion.uploader:id,name',
            'currentSupportingPhotoVersion.uploader:id,name',
        ]);
        $fields = $schema->fields($document->document_type);
        $draft = $document->draft_data ?? $schema->initial($document->document_type, $fea->surfacedFormerRebel->display_name);

        return view('ib39.fea.draft', compact('fea', 'document', 'fields', 'draft'));
    }

    public function update(
        SaveFeaDraftRequest $request,
        Ib39FeaProcessing $fea,
        Ib39FeaDocument $document,
        Ib39FeaDocumentWorkflowService $workflow,
    ): RedirectResponse {
        $saved = $workflow->saveDraft($fea, $document, $request->draftData(), (int) $request->validated('revision'), $request->user());

        $workspaceUrl = route('ib39.fea.show', $fea);
        $referrer = (string) $request->headers->get('referer');
        if (parse_url($referrer, PHP_URL_HOST) === parse_url($workspaceUrl, PHP_URL_HOST)
            && parse_url($referrer, PHP_URL_PATH) === parse_url($workspaceUrl, PHP_URL_PATH)) {
            return redirect()->route('ib39.fea.show', ['fea' => $fea, 'document' => $document->id])
                ->with('status', 'Draft revision '.$saved->draft_revision.' saved securely.');
        }

        return redirect()->route('ib39.fea.documents.draft.edit', [$fea, $document])
            ->with('status', 'Draft revision '.$saved->draft_revision.' saved securely.');
    }

    public function preview(Ib39FeaProcessing $fea, Ib39FeaDocument $document): Response|RedirectResponse
    {
        Gate::authorize('viewUploads', [$document, $fea]);
        if (! request()->user()->hasRole('39th_ib') && $document->status !== Ib39FeaDocumentStatus::Completed) {
            $fea->loadMissing('surfacedFormerRebel.pswdoEnrollment', 'surfacedFormerRebel.japicCertificationProcessing');
            $record = $fea->surfacedFormerRebel;
            $destination = request()->user()->hasRole('pswdo')
                ? route('pswdo.enrollments.records.fea', $record->pswdoEnrollment)
                : route('japic.certifications.records.fea', $record->japicCertificationProcessing);

            return redirect($destination)->with('error', 'Document is not available yet.');
        }

        return $this->renderDocument($fea, $document, false);
    }

    public function print(Ib39FeaProcessing $fea, Ib39FeaDocument $document): Response
    {
        return $this->renderDocument($fea, $document, true);
    }

    public function download(Ib39FeaProcessing $fea, Ib39FeaDocument $document, OfficialDocumentPdf $pdf): Response
    {
        Gate::authorize('downloadGeneratedDraft', [$document, $fea]);
        $data = $this->documentViewData($fea, $document);
        abort_if($data['draft'] === null, 404, 'Save a draft before downloading its official-format PDF.');

        $data['pdfMode'] = true;
        $data['surrenderedPhotoSource'] = $this->photoSource($data['surrenderedPhoto']);
        $data['comparisonPhotoSource'] = $this->photoSource($data['comparisonPhoto']);
        $filename = Str::slug($fea->surfacedFormerRebel->reference_number, '_').'_'.Str::slug($document->document_type->label(), '_').'.pdf';

        return response($pdf->render(view('ib39.fea.preview', $data)->render()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    private function renderDocument(Ib39FeaProcessing $fea, Ib39FeaDocument $document, bool $printMode): Response
    {
        Gate::authorize('viewDraft', [$document, $fea]);

        return response()->view('ib39.fea.preview', [
            ...$this->documentViewData($fea, $document),
            'printMode' => $printMode,
            'pdfMode' => false,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    private function documentViewData(Ib39FeaProcessing $fea, Ib39FeaDocument $document): array
    {
        $fea->loadMissing('surfacedFormerRebel');
        $document->loadMissing(['currentSurrenderedPhotoVersion', 'currentSupportingPhotoVersion']);

        return [
            'fea' => $fea,
            'document' => $document,
            'draft' => $document->draft_data,
            'printMode' => false,
            'surrenderedPhoto' => $document->currentSurrenderedPhotoVersion,
            'comparisonPhoto' => $document->currentSupportingPhotoVersion,
        ];
    }

    private function photoSource(?Ib39FeaDocumentVersion $version): ?string
    {
        if ($version === null) {
            return null;
        }

        abort_unless(in_array($version->mime_type, ['image/jpeg', 'image/png'], true), 422, 'The saved photograph has an unsupported format.');
        $disk = Storage::disk('local');
        abort_unless($disk->exists($version->storage_path), 404, 'The saved photograph is unavailable.');

        return 'data:'.$version->mime_type.';base64,'.base64_encode($disk->get($version->storage_path));
    }
}
