<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39CdrDocumentSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\DownloadCdrDocumentRequest;
use App\Http\Requests\Ib39\PreviewCdrDocumentRequest;
use App\Http\Requests\Ib39\PrintCdrDocumentRequest;
use App\Http\Requests\Ib39\ReplaceFinalCdrRequest;
use App\Http\Requests\Ib39\UploadFinalCdrRequest;
use App\Models\Ib39CdrDocumentVersion;
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

    public function replace(ReplaceFinalCdrRequest $request, Ib39CdrProcessing $cdr, Ib39CdrDocumentService $documents): RedirectResponse
    {
        $documents->replaceFinal($cdr, $request->file('document'), $request->validated('replacement_reason'), $request->user(), $request->ip(), $request->userAgent());

        return redirect()->route('ib39.cdr.show', $cdr)->with('status', 'The replacement final CDR was stored as a new immutable version.');
    }

    public function preview(PreviewCdrDocumentRequest $request, Ib39CdrProcessing $cdr, Ib39CdrDocumentVersion $version, Ib39CdrDocumentService $documents): Response|StreamedResponse
    {
        if ($version->source_type === Ib39CdrDocumentSource::Uploaded) {
            return $documents->preview($version, $request->user(), $request->ip(), $request->userAgent());
        }
        $documents->recordGeneratedPreview($version, $request->user(), $request->ip(), $request->userAgent());

        return $this->generated($version, false);
    }

    public function download(DownloadCdrDocumentRequest $request, Ib39CdrProcessing $cdr, Ib39CdrDocumentVersion $version, Ib39CdrDocumentService $documents): StreamedResponse
    {
        return $documents->download($version, $request->user(), $request->ip(), $request->userAgent());
    }

    public function print(PrintCdrDocumentRequest $request, Ib39CdrProcessing $cdr, Ib39CdrDocumentVersion $version): Response
    {
        return $this->generated($version, true);
    }

    private function generated(Ib39CdrDocumentVersion $version, bool $autoPrint): Response
    {
        abort_unless($version->source_type === Ib39CdrDocumentSource::Generated, 404);
        $version->load('processing.surfacedFormerRebel');
        $snapshot = $version->content_snapshot ?? [];
        $photoVersion = null;
        if ($photoVersionId = $snapshot['fr_photo_version_id'] ?? null) {
            $photoVersion = Ib39CdrPhotoVersion::query()
                ->whereHas('photo', fn ($query) => $query->where('cdr_processing_id', $version->cdr_processing_id))
                ->findOrFail($photoVersionId);
        }

        return response()->view('ib39.cdr.document', [
            'cdr' => $version->processing,
            'content' => array_replace(Ib39CdrFormSchema::defaultContent(), $snapshot['content'] ?? []),
            'sections' => Ib39CdrFormSchema::sections(),
            'repeatableSections' => Ib39CdrFormSchema::repeatableSections(),
            'orderedBlocks' => Ib39CdrFormSchema::orderedBlocks(),
            'autoPrint' => $autoPrint,
            'isFinal' => true,
            'photoVersion' => $photoVersion,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
