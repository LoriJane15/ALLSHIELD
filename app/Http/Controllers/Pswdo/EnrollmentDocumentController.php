<?php

namespace App\Http\Controllers\Pswdo;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pswdo\UploadFinalEnrollmentDocumentRequest;
use App\Models\PswdoEnrollment;
use App\Models\PswdoEnrollmentDocument;
use App\Services\PswdoEnrollmentDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentDocumentController extends Controller
{
    public function store(UploadFinalEnrollmentDocumentRequest $request, PswdoEnrollment $pswdoEnrollment, PswdoEnrollmentDocumentType $documentType, PswdoEnrollmentDocumentService $documents): RedirectResponse
    {
        $documents->uploadFinal(
            $pswdoEnrollment,
            $documentType,
            $request->file('document'),
            (int) $request->validated('lock_version'),
            (bool) $request->validated('correct_document_type_confirmed'),
            (bool) $request->validated('belongs_to_fr_confirmed'),
            (bool) $request->validated('final_signed_confirmed'),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('pswdo.enrollments.workspace', $pswdoEnrollment)
            ->with('status', $documentType->label().' was stored securely and marked Completed.');
    }

    public function preview(Request $request, PswdoEnrollment $pswdoEnrollment, PswdoEnrollmentDocument $document, PswdoEnrollmentDocumentService $documents): StreamedResponse
    {
        return $documents->preview($pswdoEnrollment, $document, $request->user(), $request->ip(), $request->userAgent());
    }

    public function download(Request $request, PswdoEnrollment $pswdoEnrollment, PswdoEnrollmentDocument $document, PswdoEnrollmentDocumentService $documents): StreamedResponse
    {
        return $documents->download($pswdoEnrollment, $document, $request->user(), $request->ip(), $request->userAgent());
    }
}
