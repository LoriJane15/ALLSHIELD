<?php

namespace App\Http\Controllers\Japic;

use App\Enums\JapicCertificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Japic\UploadFinalCertificationRequest;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Services\JapicCertificationDocumentService;
use App\Support\OfficialDocumentPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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

        return redirect()->route('japic.certifications.workspace', $japicCertificationProcessing)
            ->with('status', 'The final signed certification was stored securely and the certification is complete.');
    }

    public function previewFinal(
        Request $request,
        JapicCertificationProcessing $japicCertificationProcessing,
        JapicCertificationDocumentVersion $version,
        JapicCertificationDocumentService $documents,
    ): Response|StreamedResponse {
        abort_unless($version->processing_id === $japicCertificationProcessing->id, 404);
        $version->setRelation('processing', $japicCertificationProcessing);
        Gate::authorize('preview', $version);

        if ($request->boolean('file')) {
            return $documents->previewFinal(
                $japicCertificationProcessing,
                $version,
                $request->user(),
                $request->ip(),
                $request->userAgent(),
            );
        }

        abort_unless($japicCertificationProcessing->status === JapicCertificationStatus::Completed
            && $japicCertificationProcessing->current_final_version_id === $version->id, 404);
        $role = $request->user()->role;
        $backUrl = route('japic.certifications.workspace', $japicCertificationProcessing);
        $downloadUrl = match ($role) {
            'pswdo' => route('pswdo.japic.document-versions.download', [$japicCertificationProcessing, $version]),
            '39th_ib' => route('ib39.japic-certifications.document-versions.download', [$japicCertificationProcessing, $version]),
            default => route('japic.certifications.document-versions.download', [$japicCertificationProcessing, $version]),
        };

        return $this->privateView([
            'uploadedVersion' => $version,
            'backUrl' => $backUrl,
            'previewFileUrl' => route('japic.certifications.document-versions.preview', [$japicCertificationProcessing, $version, 'file' => 1]),
            'downloadUrl' => $downloadUrl,
        ]);
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

    public function preview(Request $request, JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationDocumentService $documents, OfficialDocumentPdf $pdf): Response|RedirectResponse
    {
        Gate::authorize('viewDocument', $japicCertificationProcessing);
        if (! Gate::allows('view', $japicCertificationProcessing)
            && $japicCertificationProcessing->status !== JapicCertificationStatus::Completed) {
            $japicCertificationProcessing->loadMissing('surfacedFormerRebel.pswdoEnrollment');
            $record = $japicCertificationProcessing->surfacedFormerRebel;
            $destination = $request->user()->hasRole('pswdo')
                ? route('pswdo.enrollments.show', $record->pswdoEnrollment)
                : route('ib39.fr-profiles.show', $record);

            return redirect($destination)->with('error', 'Document is not available yet.');
        }
        Gate::authorize('previewDraft', $japicCertificationProcessing);

        $data = $documents->data($japicCertificationProcessing);
        if ($request->boolean('download')) {
            $filename = Str::slug($japicCertificationProcessing->surfacedFormerRebel->reference_number, '_').'_JAPIC_Certification_Draft.pdf';

            return response($pdf->render(view('japic.certifications.document', [
                ...$data,
                'printMode' => false,
                'embedded' => true,
            ])->render()), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
        }

        return $this->render($data, false, $request->boolean('embedded'));
    }

    public function print(JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationDocumentService $documents): Response
    {
        Gate::authorize('printDraft', $japicCertificationProcessing);

        return $this->render($documents->data($japicCertificationProcessing), true);
    }

    private function render(array $data, bool $printMode, bool $embedded = false): Response
    {
        return $this->privateView([...$data, 'printMode' => $printMode, 'embedded' => $embedded]);
    }

    private function privateView(array $data): Response
    {
        return response()->view('japic.certifications.document', $data)->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
