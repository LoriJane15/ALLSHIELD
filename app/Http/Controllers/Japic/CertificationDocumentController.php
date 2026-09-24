<?php

namespace App\Http\Controllers\Japic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Japic\UploadFinalCertificationRequest;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Services\JapicCertificationDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificationDocumentController extends Controller
{
    public function uploadFinal(
        UploadFinalCertificationRequest $request,
        JapicCertificationProcessing $japicCertificationProcessing,
        JapicCertificationDocumentService $documents,
    ): RedirectResponse {
        $documents->uploadFinal(
            $japicCertificationProcessing,
            $request->file('document'),
            (int) $request->validated('revision'),
            (int) $request->validated('lock_version'),
            (bool) $request->validated('all_signatories_confirmed'),
            (bool) $request->validated('correct_final_confirmed'),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('japic.certifications.show', $japicCertificationProcessing)
            ->with('status', 'The final signed certification was stored securely and the certification is complete.');
    }

    public function previewFinal(
        Request $request,
        JapicCertificationProcessing $japicCertificationProcessing,
        JapicCertificationDocumentVersion $version,
        JapicCertificationDocumentService $documents,
    ): StreamedResponse {
        abort_unless($version->processing_id === $japicCertificationProcessing->id, 404);
        $version->setRelation('processing', $japicCertificationProcessing);
        Gate::authorize('preview', $version);

        return $documents->previewFinal(
            $japicCertificationProcessing,
            $version,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );
    }

    public function downloadFinal(
        Request $request,
        JapicCertificationProcessing $japicCertificationProcessing,
        JapicCertificationDocumentVersion $version,
        JapicCertificationDocumentService $documents,
    ): StreamedResponse {
        abort_unless($version->processing_id === $japicCertificationProcessing->id, 404);
        $version->setRelation('processing', $japicCertificationProcessing);
        Gate::authorize('download', $version);

        return $documents->downloadFinal(
            $japicCertificationProcessing,
            $version,
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );
    }

    public function preview(JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationDocumentService $documents): Response
    {
        Gate::authorize('previewDraft', $japicCertificationProcessing);

        return $this->render($documents->data($japicCertificationProcessing), false);
    }

    public function print(JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationDocumentService $documents): Response
    {
        Gate::authorize('printDraft', $japicCertificationProcessing);

        return $this->render($documents->data($japicCertificationProcessing), true);
    }

    private function render(array $data, bool $printMode): Response
    {
        return response()->view('japic.certifications.document', [...$data, 'printMode' => $printMode])->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
