<?php

namespace App\Http\Controllers\Japic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Japic\UploadCertificationPhotoRequest;
use App\Models\JapicCertificationPhotoVersion;
use App\Models\JapicCertificationProcessing;
use App\Services\JapicCertificationPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificationPhotoController extends Controller
{
    public function store(
        UploadCertificationPhotoRequest $request,
        JapicCertificationProcessing $processing,
        JapicCertificationPhotoService $photos,
    ): RedirectResponse {
        $photos->store(
            $processing,
            $request->file('photo'),
            (int) $request->validated('revision'),
            (int) $request->validated('lock_version'),
            $request->user(),
        );

        return redirect()->route('japic.certifications.draft.edit', $processing)
            ->with('status', 'Certification photograph saved as an immutable draft revision.');
    }

    public function show(
        Request $request,
        JapicCertificationProcessing $processing,
        JapicCertificationPhotoVersion $photoVersion,
        JapicCertificationPhotoService $photos,
    ): StreamedResponse {
        abort_unless($photoVersion->processing_id === $processing->id, 404);
        $photoVersion->setRelation('processing', $processing);
        Gate::authorize('view', $photoVersion);

        return $photos->response($photoVersion);
    }
}
