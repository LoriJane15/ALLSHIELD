<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\GovAgency;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(Request $request): View
    {
        $stats = [
            'total' => GovAgency::count(),
            'active_users' => User::whereNotNull('gov_agency_id')->where('is_active', true)->count(),
        ];

        return view('super_admin.agencies.index', [
            'agencies' => GovAgency::withCount([
                'users',
                'users as active_users_count' => fn ($query) => $query->where('is_active', true),
                'responses',
                'implementationTaggings',
            ])
                ->when($request->search, fn ($q, $s) => $q->where('acronym', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))
                ->orderBy('acronym')->paginate(15)->withQueryString(),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedAgencyData($request);

        $this->persistAgency(fn () => GovAgency::create($data));

        return back()->with('success', "Agency {$data['acronym']} added.");
    }

    public function update(Request $request, GovAgency $agency): RedirectResponse
    {
        $data = $this->validatedAgencyData($request, $agency);

        $this->persistAgency(fn () => $agency->update($data));

        return back()->with('success', 'Agency updated.');
    }

    public function destroy(GovAgency $agency): RedirectResponse
    {
        DB::transaction(function () use ($agency): void {
            $currentAgency = GovAgency::query()
                ->lockForUpdate()
                ->findOrFail($agency->getKey());

            $hasDependencies = $currentAgency->users()->exists()
                || $currentAgency->responses()->exists()
                || $currentAgency->implementationTaggings()->exists();

            if ($hasDependencies) {
                throw ValidationException::withMessages([
                    'agency_deletion' => 'Agency cannot be deleted while linked accounts or implementation records exist.',
                ]);
            }

            $currentAgency->delete();
        });

        return back()->with('success', 'Agency deleted.');
    }

    /** @return array{name: string, acronym: string, profile: ?string} */
    private function validatedAgencyData(Request $request, ?GovAgency $agency = null): array
    {
        $request->merge([
            'name' => $this->trimIdentity($request->input('name')),
            'acronym' => $this->trimIdentity($request->input('acronym')),
        ]);

        return $request->validate([
            'name' => [
                'bail', 'required', 'string', 'max:255',
                $this->canonicalUniqueRule('name', $agency),
            ],
            'acronym' => [
                'bail', 'required', 'string', 'max:50',
                $this->canonicalUniqueRule('acronym', $agency),
            ],
            'profile' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function trimIdentity(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function canonicalUniqueRule(string $column, ?GovAgency $agency): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($column, $agency): void {
            if (! is_string($value)) {
                return;
            }

            $duplicate = GovAgency::query()
                ->whereRaw("LOWER(TRIM({$column})) = LOWER(TRIM(?))", [$value])
                ->when($agency, fn ($query) => $query->whereKeyNot($agency->getKey()))
                ->exists();

            if ($duplicate) {
                $fail($this->duplicateIdentityMessage($column));
            }
        };
    }

    private function persistAgency(Closure $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            $field = $this->identityConflictField($exception);

            if ($field === null) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                $field => $this->duplicateIdentityMessage($field),
            ]);
        }
    }

    private function identityConflictField(QueryException $exception): ?string
    {
        $message = strtolower($exception->getMessage());

        foreach (['name', 'acronym'] as $field) {
            if (str_contains($message, "gov_agencies_{$field}_canonical_unique")
                || str_contains($message, "gov_agencies.{$field}_canonical")) {
                return $field;
            }
        }

        return null;
    }

    private function duplicateIdentityMessage(string $field): string
    {
        return "An agency with this {$field} already exists, regardless of capitalization or surrounding spaces.";
    }
}
