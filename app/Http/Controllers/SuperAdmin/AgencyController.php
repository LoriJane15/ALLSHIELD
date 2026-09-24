<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\GovAgency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(Request $request): View
    {
        return view('super_admin.agencies.index', [
            'agencies' => GovAgency::withCount('users')
                ->when($request->search, fn ($q, $s) => $q->where('acronym', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))
                ->orderBy('acronym')->paginate(15)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['required', 'string', 'max:50'],
            'profile' => ['nullable', 'string', 'max:255'],
        ]);

        GovAgency::create($data);

        return back()->with('success', "Agency {$data['acronym']} added.");
    }

    public function update(Request $request, GovAgency $agency): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['required', 'string', 'max:50'],
            'profile' => ['nullable', 'string', 'max:255'],
        ]);

        $agency->update($data);

        return back()->with('success', 'Agency updated.');
    }

    public function destroy(GovAgency $agency): RedirectResponse
    {
        abort_if($agency->users()->exists(), 422, 'Cannot delete an agency that still has user accounts.');
        $agency->delete();

        return back()->with('success', 'Agency deleted.');
    }
}
