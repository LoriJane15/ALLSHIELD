<?php

namespace App\Http\Controllers;

use App\Models\Ib39FeaProcessing;
use App\Models\JapicCertificationProcessing;
use App\Models\PswdoEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class ProcessingCommentController extends Controller
{
    public function japic(Request $request, JapicCertificationProcessing $japicCertificationProcessing): RedirectResponse
    {
        Gate::authorize('comment', $japicCertificationProcessing);
        $japicCertificationProcessing->comments()->create($this->attributes($request));

        return back()->with('status', 'Comment posted.');
    }

    public function fea(Request $request, Ib39FeaProcessing $fea): RedirectResponse
    {
        Gate::authorize('comment', $fea);
        $fea->comments()->create($this->attributes($request));

        return back()->with('status', 'Comment posted.');
    }

    public function pswdo(Request $request, PswdoEnrollment $pswdoEnrollment): RedirectResponse
    {
        Gate::authorize('comment', $pswdoEnrollment);
        $pswdoEnrollment->comments()->create($this->attributes($request));

        return back()->with('status', 'Comment posted.');
    }

    private function attributes(Request $request): array
    {
        $text = $request->input('text');
        $validated = Validator::make([
            'text' => is_string($text) ? trim($text) : $text,
        ], ['text' => ['required', 'string', 'max:2000']])->validate();

        return [
            'user_id' => $request->user()->id,
            'author_role' => $request->user()->role,
            'text' => $validated['text'],
        ];
    }
}
