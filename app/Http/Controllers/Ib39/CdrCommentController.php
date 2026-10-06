<?php

namespace App\Http\Controllers\Ib39;

use App\Enums\Ib39CdrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ib39\StoreCdrCommentRequest;
use App\Models\Ib39CdrProcessing;
use Illuminate\Http\RedirectResponse;

class CdrCommentController extends Controller
{
    public function __invoke(StoreCdrCommentRequest $request, Ib39CdrProcessing $cdr): RedirectResponse
    {
        $user = $request->user();

        $cdr->comments()->create([
            'user_id' => $user->id,
            'author_role' => $user->role,
            'text' => $request->validated('text'),
        ]);

        if ($user->hasRole('39th_ib') && $cdr->status !== Ib39CdrStatus::Completed) {
            return redirect()->to(route('ib39.cdr.edit', $cdr).'#cdr-comments')
                ->with('status', 'Comment posted.');
        }

        return back()->with('status', 'Comment posted.');
    }
}
