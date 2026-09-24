<?php

namespace App\Http\Controllers\Ib39;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\StartFeaDocumentRequest;
use App\Http\Requests\Ib39\UpdateFeaDocumentRequest;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaProcessing;
use App\Services\Ib39FeaDocumentWorkflowService;
use Illuminate\Http\RedirectResponse;

class FeaDocumentController extends Controller
{
    public function start(
        StartFeaDocumentRequest $request,
        Ib39FeaProcessing $fea,
        Ib39FeaDocument $document,
        Ib39FeaDocumentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->start($fea, $document, $request->user());

        return redirect()->route('ib39.fea.show', $fea)
            ->with('status', 'Preliminary document work started.');
    }

    public function update(
        UpdateFeaDocumentRequest $request,
        Ib39FeaProcessing $fea,
        Ib39FeaDocument $document,
        Ib39FeaDocumentWorkflowService $workflow,
    ): RedirectResponse {
        $workflow->update($fea, $document, $request->documentData(), $request->user());

        return redirect()->route('ib39.fea.show', $fea)
            ->with('status', 'Preliminary document metadata updated.');
    }
}
