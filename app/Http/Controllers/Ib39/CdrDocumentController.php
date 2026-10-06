<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\DownloadCdrDocumentRequest;
use App\Http\Requests\Ib39\PreviewCdrDocumentRequest;
use App\Http\Requests\Ib39\PrintCdrDocumentRequest;
use App\Http\Requests\Ib39\UploadFinalCdrRequest;
use App\Models\Ib39CdrFinalDocument;
use App\Models\Ib39CdrPhotoVersion;
use App\Models\Ib39CdrProcessing;
use App\Services\Ib39CdrDocumentService;
use App\Support\Ib39CdrFormSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CdrDocumentController extends Controller
{
    public function upload(UploadFinalCdrRequest $request, Ib39CdrProcessing $cdr, Ib39CdrDocumentService $documents): RedirectResponse
    {
        $documents->uploadFinal($cdr, $request->file('document'), $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('ib39.cdr.show', $cdr)->with('status', 'The completed CDR was uploaded and locked as the final document.');
    }

    public function preview(PreviewCdrDocumentRequest $request, Ib39CdrProcessing $cdr, Ib39CdrDocumentService $documents): Response|StreamedResponse|RedirectResponse
    {
        if (! $request->user()->hasRole('39th_ib') && $cdr->status !== Ib39CdrStatus::Completed) {
            $cdr->loadMissing('surfacedFormerRebel.pswdoEnrollment', 'surfacedFormerRebel.japicCertificationProcessing');
            $record = $cdr->surfacedFormerRebel;
            $destination = $request->user()->hasRole('pswdo')
                ? route('pswdo.enrollments.show', $record->pswdoEnrollment)
                : route('japic.certifications.show', $record->japicCertificationProcessing);

            return redirect($destination)->with('error', 'Document is not available yet.');
        }
        $document = $cdr->finalDocument()->firstOrFail();
        abort_unless($request->user()->can('preview', $document), 403);
        if ($document->source_type === Ib39CdrDocumentSource::Uploaded) {
            if (! $request->user()->hasRole('39th_ib') || $request->boolean('file')) {
                return $documents->preview($document, $request->user(), $request->ip(), $request->userAgent());
            }

            $documents->ensureUploadedFileIsAvailable($document);

            return response()->view('ib39.cdr.document', [
                'uploadedDocument' => $document,
                'uploadedFileUrl' => route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]),
                'uploadedDownloadUrl' => $request->user()->can('download', $document)
                    ? route('ib39.cdr.documents.download', $cdr)
                    : null,
                'backUrl' => $request->user()->hasRole('39th_ib')
                    ? route('ib39.cdr.show', $cdr)
                    : route('cdr.workspace', $cdr),
                'canPrintUploaded' => $request->user()->hasRole('39th_ib'),
            ])->withHeaders([
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        $documents->recordGeneratedAccess($document, $request->user(), 'preview', $request->ip(), $request->userAgent());

        return $this->generated($document, false);
    }

    public function download(DownloadCdrDocumentRequest $request, Ib39CdrProcessing $cdr, Ib39CdrDocumentService $documents): Response|StreamedResponse
    {
        $document = $cdr->finalDocument()->firstOrFail();
        if ($document->source_type === Ib39CdrDocumentSource::Uploaded) {
            return $documents->download($document, $request->user(), $request->ip(), $request->userAgent());
        }
        $documents->recordGeneratedAccess($document, $request->user(), 'download', $request->ip(), $request->userAgent());

        return $this->generated($document, false, true);
    }

    public function print(PrintCdrDocumentRequest $request, Ib39CdrProcessing $cdr): Response
    {
        return $this->generated($cdr->finalDocument()->firstOrFail(), true);
    }

    private function generated(Ib39CdrFinalDocument $document, bool $autoPrint, bool $attachment = false): Response
    {
        abort_unless($document->source_type === Ib39CdrDocumentSource::Generated, 404);
        $document->load('processing.surfacedFormerRebel');
        $snapshot = $document->content_snapshot ?? [];
        $photoVersion = null;
        if ($photoVersionId = $snapshot['fr_photo_version_id'] ?? null) {
            $photoVersion = Ib39CdrPhotoVersion::query()
                ->whereHas('photo', fn ($query) => $query->where('cdr_processing_id', $document->cdr_processing_id))
                ->findOrFail($photoVersionId);
        }

        return response()->view('ib39.cdr.document', [
            'cdr' => $document->processing,
            'content' => array_replace(Ib39CdrFormSchema::defaultContent(), $snapshot['content'] ?? []),
            'sections' => Ib39CdrFormSchema::sections(),
            'repeatableSections' => Ib39CdrFormSchema::repeatableSections(),
            'orderedBlocks' => Ib39CdrFormSchema::orderedBlocks(),
            'autoPrint' => $autoPrint,
            'canPrint' => auth()->user()?->can('print', $document) ?? false,
            'embedded' => request()->routeIs('cdr.documents.preview'),
            'pdfMode' => false,
            'isFinal' => true,
            'photoVersion' => $photoVersion,
            'photoSource' => null,
            'backUrl' => auth()->user()?->hasRole('39th_ib')
                ? route('ib39.cdr.show', $document->processing)
                : route('cdr.workspace', $document->processing),
            'printUrl' => auth()->user()?->can('print', $document)
                ? route('ib39.cdr.documents.print', $document->processing)
                : null,
            'downloadUrl' => auth()->user()?->can('download', $document)
                ? route('ib39.cdr.documents.download', $document->processing)
                : null,
        ])->withHeaders(array_filter([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => $attachment ? 'attachment; filename="'.$document->original_filename.'"' : null,
        ]));
    }
}
