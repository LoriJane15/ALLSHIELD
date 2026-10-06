<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39CdrPhotoType;
use App\Enums\Ib39CdrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\UploadCdrPhotoRequest;
use App\Models\Ib39CdrPhotoVersion;
use App\Models\Ib39CdrProcessing;
use App\Services\Ib39CdrPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CdrPhotoController extends Controller
{
    public function store(
        UploadCdrPhotoRequest $request,
        Ib39CdrProcessing $cdr,
        Ib39CdrPhotoService $photos,
    ): RedirectResponse {
        $photos->store(
            $cdr,
            Ib39CdrPhotoType::from($request->validated('photo_type')),
            $request->file('photo'),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('ib39.cdr.edit', $cdr)->with('status', 'CDR photograph saved.');
    }

    public function show(
        Request $request,
        Ib39CdrPhotoVersion $photoVersion,
        Ib39CdrPhotoService $photos,
    ): StreamedResponse|RedirectResponse {
        $photoVersion->load('photo.processing.finalDocument');
        $processing = $photoVersion->photo->processing;
        Gate::authorize('view', $processing);

        if (! $request->user()->hasRole('39th_ib') && $processing->status !== Ib39CdrStatus::Completed) {
            $processing->loadMissing('surfacedFormerRebel.pswdoEnrollment', 'surfacedFormerRebel.japicCertificationProcessing');
            $record = $processing->surfacedFormerRebel;
            $destination = $request->user()->hasRole('pswdo')
                ? route('pswdo.enrollments.show', $record->pswdoEnrollment)
                : route('japic.certifications.show', $record->japicCertificationProcessing);

            return redirect($destination)->with('error', 'Document is not available yet.');
        }

        Gate::authorize('viewPhoto', $processing);
        if (! $request->user()->hasRole('39th_ib')) {
            $snapshotPhotoId = data_get($processing->finalDocument?->content_snapshot, 'fr_photo_version_id');
            $isOfficialPhoto = (int) $snapshotPhotoId === $photoVersion->id
                || ($processing->finalDocument === null && $photoVersion->photo->current_version_id === $photoVersion->id);
            abort_unless($isOfficialPhoto, 404);
        }

        return $photos->preview($photoVersion);
    }
}
