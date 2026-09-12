<?php

namespace App\Http\Controllers\Ib39;

use App\Contracts\Ib39FeaReadiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\IndexFeaProcessingRequest;
use App\Models\Ib39FeaProcessing;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FeaProcessingController extends Controller
{
    public function index(IndexFeaProcessingRequest $request, Ib39FeaReadiness $readiness): View
    {
        $processings = Ib39FeaProcessing::query()
            ->whereHas('surfacedFormerRebel')
            ->with([
                'surfacedFormerRebel.municipality',
                'surfacedFormerRebel.barangay',
                'documents',
            ])
            ->latest('id')
            ->paginate(20);

        $readinessByProcessing = $processings->getCollection()->mapWithKeys(fn (Ib39FeaProcessing $processing): array => [
            $processing->id => $readiness->isReady($processing->surfacedFormerRebel),
        ]);

        return view('ib39.fea.index', compact('processings', 'readinessByProcessing', 'readiness'));
    }

    public function show(Ib39FeaProcessing $fea, Ib39FeaReadiness $readiness): View
    {
        Gate::authorize('view', $fea);

        $fea->load([
            'surfacedFormerRebel.municipality',
            'surfacedFormerRebel.barangay',
            'documents' => fn ($query) => $query->with([
                'preparer:id,name',
                'lastUpdater:id,name',
                'histories' => fn ($history) => $history->with('actor:id,name')->oldest('created_at')->oldest('id'),
                'currentDraftVersion.uploader:id,name',
                'currentSupportingPhotoVersion.uploader:id,name',
                'versions' => fn ($version) => $version->with('uploader:id,name')->latest('version_number'),
                'uploadHistories' => fn ($history) => $history->with('actor:id,name')->latest('created_at'),
            ])->orderBy('id'),
        ]);

        $isReady = $readiness->isReady($fea->surfacedFormerRebel);

        return view('ib39.fea.show', compact('fea', 'isReady', 'readiness'));
    }
}
