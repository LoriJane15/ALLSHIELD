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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['municipality', 'govAgency'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($search) => $search
                ->where('name', 'like', "%{$s}%")
                ->orWhere('username', 'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('role')->orderBy('name')
            ->paginate(15)->withQueryString();

        $editingUserId = $request->session()->get('super_admin_edit_user_id');
        $editingUser = $editingUserId ? User::query()->find($editingUserId) : null;

        $stats = [
            'total' => User::count(),
            'roles_count' => count(config('shield.roles')),
            'lgu_count' => User::where('role', 'lgu')->count(),
            'agency_count' => User::where('role', 'gov_agency')->count(),
            'core_admin_count' => User::whereIn('role', ['super_admin', 'admin'])->count(),
        ];

        return view('super_admin.users.index', [
            'users' => $users,
            'roles' => config('shield.roles'),
            'municipalities' => Municipality::query()
                ->whereIn('name', config('shield.jurisdiction.municipalities'))
                ->orderBy('name')
                ->get(),
            'agencies' => GovAgency::orderBy('acronym')->get(),
            'stats' => $stats,
            'editingUser' => $editingUser,
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
        $data['is_active'] = $request->boolean('is_active');

        if (empty($data['password'])) {
            unset($data['password']);   // keep existing
        }

        DB::transaction(function () use ($data, $request, $user): void {
            $activeSuperAdministrators = User::query()
                ->where('role', 'super_admin')
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $removesActiveSuperAdministrator = $lockedUser->role === 'super_admin'
                && $lockedUser->is_active
                && ($data['role'] !== 'super_admin' || ! $data['is_active']);

            if ($removesActiveSuperAdministrator && $activeSuperAdministrators->count() <= 1) {
                throw ValidationException::withMessages([
                    'account_lifecycle' => 'At least one active Super Administrator account must remain.',
                ]);
            }

            if ($request->hasFile('logo')) {
                if ($lockedUser->logo) {
                    Storage::disk('public')->delete($lockedUser->logo);
                }
                $data['logo'] = $request->file('logo')->store('logos', 'public');
            } else {
                unset($data['logo']);
            }

            $lockedUser->update($data);
        });

        return back()->with('success', 'User updated.');
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
