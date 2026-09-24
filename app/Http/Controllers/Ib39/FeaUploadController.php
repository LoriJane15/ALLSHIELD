<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39FeaUploadSlot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\UploadFeaDraftFileRequest;
use App\Http\Requests\Ib39\UploadFeaFinalFileRequest;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\Ib39FeaProcessing;
use App\Services\Ib39FeaUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeaUploadController extends Controller
{
    public function store(UploadFeaDraftFileRequest $request, Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaUploadService $uploads): RedirectResponse
    {
        return $this->save($request, $fea, $document, Ib39FeaUploadSlot::Primary, $uploads);
    }

    public function storeFinal(UploadFeaFinalFileRequest $request, Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaUploadService $uploads): RedirectResponse
    {
        $uploads->storeFinal($fea, $document, $request->file('file'), $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('ib39.fea.show', $fea)->with('status', 'Final FEA file uploaded and document completed.');
    }

    public function storeComparison(UploadFeaDraftFileRequest $request, Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaUploadService $uploads): RedirectResponse
    {
        return $this->save($request, $fea, $document, Ib39FeaUploadSlot::JustificationComparison, $uploads);
    }

    public function storeSurrendered(UploadFeaDraftFileRequest $request, Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaUploadService $uploads): RedirectResponse
    {
        return $this->save($request, $fea, $document, Ib39FeaUploadSlot::JustificationSurrendered, $uploads);
    }

    public function preview(Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaDocumentVersion $version, Ib39FeaUploadService $uploads): StreamedResponse
    {
        $this->authorizeVersion($fea, $document, $version, 'preview');

        return $uploads->preview($version, request()->user(), request()->ip(), request()->userAgent());
    }

    public function download(Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaDocumentVersion $version, Ib39FeaUploadService $uploads): StreamedResponse
    {
        $this->authorizeVersion($fea, $document, $version, 'download');

        return $uploads->download($version, request()->user(), request()->ip(), request()->userAgent());
    }

    private function save(UploadFeaDraftFileRequest $request, Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaUploadSlot $slot, Ib39FeaUploadService $uploads): RedirectResponse
    {
        $version = $uploads->store($fea, $document, $slot, $request->file('file'), $request->validated('expected_current_version_id'), $request->validated('replacement_reason'), $request->user(), $request->ip(), $request->userAgent());

        $destination = $slot === Ib39FeaUploadSlot::Primary
            ? route('ib39.fea.show', $fea)
            : route('ib39.fea.documents.draft.edit', [$fea, $document]);

        return redirect($destination)->with('status', $slot->label().' version '.$version->version_number.' saved as DRAFT — NOT FINAL.');
    }

    private function authorizeVersion(Ib39FeaProcessing $fea, Ib39FeaDocument $document, Ib39FeaDocumentVersion $version, string $ability): void
    {
        Gate::authorize('viewUploads', [$document, $fea]);
        abort_unless($version->fea_processing_id === $fea->id && $version->fea_document_id === $document->id, 404);
        Gate::authorize($ability, $version);
    }
}
