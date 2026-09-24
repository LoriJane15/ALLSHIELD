<?php

namespace App\Http\Controllers\Japic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Japic\ConfirmCertificationSigningRequest;
use App\Http\Requests\Japic\SubmitCertificationForSigningRequest;
use App\Models\JapicCertificationProcessing;
use App\Services\JapicCertificationWorkflowService;
use Illuminate\Http\RedirectResponse;

class CertificationWorkflowController extends Controller
{
    public function submitForSigning(SubmitCertificationForSigningRequest $request, JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationWorkflowService $workflow): RedirectResponse
    {
        $workflow->submitForSigning($japicCertificationProcessing, (int) $request->validated('revision'), (int) $request->validated('lock_version'), $request->validated('delay_reason'), $request->user());

        return redirect()->route('japic.certifications.show', $japicCertificationProcessing)->with('status', 'The certification revision is frozen for physical signing.');
    }

    public function signingComplete(ConfirmCertificationSigningRequest $request, JapicCertificationProcessing $japicCertificationProcessing, JapicCertificationWorkflowService $workflow): RedirectResponse
    {
        $workflow->confirmSigningComplete($japicCertificationProcessing, (int) $request->validated('revision'), (int) $request->validated('lock_version'), $request->validated('delay_reason'), $request->user());

        return redirect()->route('japic.certifications.show', $japicCertificationProcessing)->with('status', 'Signing completion confirmed. The signed final PDF is now awaiting upload.');
    }
}
