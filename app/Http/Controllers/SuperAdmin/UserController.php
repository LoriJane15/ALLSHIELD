<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreUserRequest;
use App\Http\Requests\SuperAdmin\UpdateUserRequest;
use App\Models\GovAgency;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['municipality', 'govAgency'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('username', 'like', "%{$s}%"))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('role')->orderBy('name')
            ->paginate(15)->withQueryString();

        return view('super_admin.users.index', [
            'users' => $users,
            'roles' => config('shield.roles'),
            'municipalities' => Municipality::orderBy('name')->get(),
            'agencies' => GovAgency::orderBy('acronym')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data = $this->scopeRoleFields($data);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }

        User::create($data); // password auto-hashed via model cast

        return back()->with('success', "User {$data['username']} created.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $data = $this->scopeRoleFields($data);

        if (empty($data['password'])) {
            unset($data['password']);   // keep existing
        }
        if ($request->hasFile('logo')) {
            if ($user->logo) {
                Storage::disk('public')->delete($user->logo);
            }
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        } else {
            unset($data['logo']);
        }

        $user->update($data);

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot delete your own account.');
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    /** Null out role-scoped FKs that don't apply to the chosen role. */
    private function scopeRoleFields(array $data): array
    {
        if (($data['role'] ?? null) !== 'lgu') {
            $data['municipality_id'] = null;
        }
        if (($data['role'] ?? null) !== 'gov_agency') {
            $data['gov_agency_id'] = null;
        }

        return $data;
    }
}
