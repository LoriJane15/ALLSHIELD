<?php

namespace App\Http\Controllers\Ib39;

use App\Contracts\Ib39FeaReadiness;
use App\Enums\Ib39FeaDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\IndexFeaProcessingRequest;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaProcessing;
use App\Services\Ib39FeaDraftSchema;
use App\Support\ProcessingWorkspaceHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FeaProcessingController extends Controller
{
    public function index(IndexFeaProcessingRequest $request, Ib39FeaReadiness $readiness): View
    {
        $processings = Ib39FeaProcessing::query()
            ->whereHas('surfacedFormerRebel')
            ->with([
                'surfacedFormerRebel.municipality',
                'surfacedFormerRebel.barangay',
                'documents',
            ])
            ->latest('id')
            ->paginate(20);

        $readinessByProcessing = $processings->getCollection()->mapWithKeys(fn (Ib39FeaProcessing $processing): array => [
            $processing->id => $readiness->isReady($processing->surfacedFormerRebel),
        ]);

        return view('ib39.fea.index', compact('processings', 'readinessByProcessing', 'readiness'));
    }

    public function show(Ib39FeaProcessing $fea, Ib39FeaReadiness $readiness, Ib39FeaDraftSchema $schema): View|RedirectResponse
    {
        $readOnlyViewer = ! request()->user()->hasRole('39th_ib');
        $selectedDocument = null;
        if ($readOnlyViewer) {
            $selectedDocument = $fea->documents()->findOrFail(request()->integer('document'));
            Gate::authorize('viewUploads', [$selectedDocument, $fea]);
            if ($selectedDocument->status !== Ib39FeaDocumentStatus::Completed
                || ($selectedDocument->current_final_version_id === null
                    && (! $selectedDocument->document_type->hasDraftEditor() || $selectedDocument->draft_data === null))) {
                return redirect($this->externalProfileUrl($fea))->with('error', 'Document is not available yet.');
            }
        } else {
            Gate::authorize('view', $fea);
        }

        $fea->loadMissing([
            'surfacedFormerRebel.municipality',
            'surfacedFormerRebel.barangay',
            'surfacedFormerRebel.cancellation',
            'histories' => fn ($history) => $history->with('actor:id,name,role')->oldest()->oldest('id'),
            'comments' => fn ($query) => $query->with('user:id,name,role,logo,gov_agency_id', 'user.govAgency:id,profile')->oldest()->oldest('id'),
        ]);
        $documentRelations = [
            'histories' => fn ($history) => $history->with('actor:id,name,role')->oldest('created_at')->oldest('id'),
            'draftHistories' => fn ($history) => $history->with('actor:id,name,role')->oldest('created_at')->oldest('id'),
            'currentDraftVersion.uploader:id,name',
            'currentFinalVersion.uploader:id,name',
            'currentSupportingPhotoVersion.uploader:id,name',
            'currentSurrenderedPhotoVersion.uploader:id,name',
            'uploadHistories' => fn ($history) => $history->with('actor:id,name,role')->oldest('created_at')->oldest('id'),
        ];
        if ($selectedDocument) {
            $selectedDocument->loadMissing($documentRelations);
            $fea->setRelation('documents', collect([$selectedDocument]));
        } else {
            $fea->loadMissing(['documents' => fn ($query) => $query->with($documentRelations)->orderBy('id')]);
        }

        $isReady = $readOnlyViewer || $readiness->isReady($fea->surfacedFormerRebel);

        $draftEditors = [];
        $canProcess = [];
        $documentPreviewUrls = [];
        foreach ($fea->documents as $document) {
            $canProcess[$document->id] = ! $readOnlyViewer && Gate::allows('start', [$document, $fea]);
            if ($readOnlyViewer) {
                $final = $document->currentFinalVersion;
                $documentPreviewUrls[$document->id] = $final
                    ? [
                        'url' => route(request()->user()->hasRole('pswdo') ? 'pswdo.fea.documents.versions.preview' : 'japic.fea.documents.versions.preview', [$fea, $document, $final]),
                        'image' => str_starts_with($final->mime_type, 'image/'),
                    ]
                    : ['url' => route('fea.documents.draft.preview', [$fea, $document]), 'image' => false];
            } elseif ($document->document_type->hasDraftEditor()) {
                $draftEditors[$document->id] = [
                    'fields' => $schema->fields($document->document_type),
                    'draft' => $document->draft_data ?? $schema->initial($document->document_type, $fea->surfacedFormerRebel->display_name),
                    'editable' => Gate::allows('editDraft', [$document, $fea]),
                ];
            }
        }

        $workspaceEvents = ProcessingWorkspaceHistory::fea($fea);
        $canComment = Gate::allows('comment', $fea);

        return view('ib39.fea.show', compact('fea', 'isReady', 'readiness', 'workspaceEvents', 'draftEditors', 'canProcess', 'readOnlyViewer', 'documentPreviewUrls', 'canComment'));
    }

    public function document(
        Ib39FeaProcessing $fea,
        Ib39FeaDocument $document,
        Ib39FeaReadiness $readiness,
        Ib39FeaDraftSchema $schema,
    ): View|RedirectResponse {
        abort_unless($document->fea_processing_id === $fea->id, 404);
        if (request()->user()->hasRole('39th_ib')) {
            Gate::authorize('viewUploads', [$document, $fea]);

            return redirect()->route('ib39.fea.show', ['fea' => $fea, 'document' => $document->id]);
        }

        request()->query->set('document', (string) $document->id);

        return $this->show($fea, $readiness, $schema);
    }

    private function externalProfileUrl(Ib39FeaProcessing $fea): string
    {
        $fea->loadMissing('surfacedFormerRebel.pswdoEnrollment', 'surfacedFormerRebel.japicCertificationProcessing');
        $record = $fea->surfacedFormerRebel;

        return request()->user()->hasRole('pswdo')
            ? route('pswdo.enrollments.show', $record->pswdoEnrollment)
            : route('japic.certifications.show', $record->japicCertificationProcessing);
    }
}
