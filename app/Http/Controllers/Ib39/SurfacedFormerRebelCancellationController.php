<?php

namespace App\Http\Controllers\Ib39;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\CancelSurfacedFormerRebelRequest;
use App\Models\Ib39SurfacedFormerRebel;
use App\Services\Ib39SurfacedFormerRebelCancellationService;
use Illuminate\Http\RedirectResponse;

class SurfacedFormerRebelCancellationController extends Controller
{
    public function __invoke(
        CancelSurfacedFormerRebelRequest $request,
        Ib39SurfacedFormerRebel $ib39SurfacedFormerRebel,
        Ib39SurfacedFormerRebelCancellationService $cancellations,
    ): RedirectResponse {
        $cancellations->cancel(
            $ib39SurfacedFormerRebel,
            $request->validatedReason(),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()
            ->route('ib39.fr-profiles.show', $ib39SurfacedFormerRebel)
            ->with('success', "Surfaced FR {$ib39SurfacedFormerRebel->reference_number} was cancelled.");
    }
}
