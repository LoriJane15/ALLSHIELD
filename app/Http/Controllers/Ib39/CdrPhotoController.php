<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39CdrPhotoType;
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
    ): StreamedResponse {
        $photoVersion->load('photo.processing');
        Gate::authorize('viewPhoto', $photoVersion->photo->processing);

        return $photos->preview($photoVersion);
    }
}
