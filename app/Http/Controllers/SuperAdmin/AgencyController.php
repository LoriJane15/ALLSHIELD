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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AgencyController extends Controller
{
    private const LOGO_DIRECTORY = 'assets/logoAgency';

    private const LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const LOGO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

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
            'agencyLogos' => $this->availableAgencyLogos(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedAgencyData($request);

        $this->persistAgency(
            fn () => GovAgency::create($data),
            $request->hasFile('profile_upload') ? $data['profile'] : null,
        );

        return back()->with('success', "Agency {$data['acronym']} added.");
    }

    public function update(Request $request, GovAgency $agency): RedirectResponse
    {
        $data = $this->validatedAgencyData($request, $agency);

        $this->persistAgency(
            fn () => $agency->update($data),
            $request->hasFile('profile_upload') ? $data['profile'] : null,
        );

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
        $availableLogos = $this->availableAgencyLogos();

        $request->merge([
            'name' => $this->trimIdentity($request->input('name')),
            'acronym' => $this->trimIdentity($request->input('acronym')),
        ]);

        $data = $request->validate([
            'name' => [
                'bail', 'required', 'string', 'max:255',
                $this->canonicalUniqueRule('name', $agency),
            ],
            'acronym' => [
                'bail', 'required', 'string', 'max:50',
                $this->canonicalUniqueRule('acronym', $agency),
            ],
            'profile' => [
                'nullable', 'string', 'max:255', Rule::in($availableLogos),
                Rule::prohibitedIf(fn (): bool => $request->hasFile('profile_upload')),
            ],
            'profile_upload' => [
                'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                Rule::prohibitedIf(fn (): bool => filled($request->input('profile'))),
            ],
        ]);

        unset($data['profile_upload']);

        if ($request->hasFile('profile_upload')) {
            $data['profile'] = $this->storeAgencyLogo($request);
        } elseif (blank($data['profile'] ?? null)) {
            $data['profile'] = $agency?->profile;
        }

        return $data;
    }

    /** @return list<string> */
    private function availableAgencyLogos(): array
    {
        $directory = public_path(self::LOGO_DIRECTORY);

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->reject(fn ($file): bool => $file->isLink() || str_starts_with($file->getFilename(), '.'))
            ->filter(fn ($file): bool => in_array(strtolower($file->getExtension()), self::LOGO_EXTENSIONS, true)
                && in_array(File::mimeType($file->getPathname()), self::LOGO_MIME_TYPES, true))
            ->map(fn ($file): string => $file->getFilename())
            ->sortBy(fn (string $filename): string => mb_strtolower($filename), SORT_NATURAL)
            ->values()
            ->all();
    }

    private function storeAgencyLogo(Request $request): string
    {
        $upload = $request->file('profile_upload');
        $extension = match ($upload->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
        $filename = Str::uuid()->toString().'.'.$extension;

        $upload->move(public_path(self::LOGO_DIRECTORY), $filename);

        return $filename;
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

    private function persistAgency(Closure $operation, ?string $uploadedLogo = null): void
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            if ($uploadedLogo !== null) {
                File::delete(public_path(self::LOGO_DIRECTORY.'/'.$uploadedLogo));
            }

            if (! $exception instanceof QueryException) {
                throw $exception;
            }

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
