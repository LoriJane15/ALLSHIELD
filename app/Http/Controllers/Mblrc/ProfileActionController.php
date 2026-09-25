<?php

namespace App\Http\Controllers\Mblrc;

use App\Http\Controllers\Controller;
use App\Models\FormerRebel;
use App\Models\FrGovernmentAssistance;
use App\Models\FrSkill;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * AJAX/action endpoints backing the FR profile page widgets:
 * program status, geolocation, skills, assistance, education/work.
 */
class ProfileActionController extends Controller
{
    public function updateProgramStatus(Request $request, FormerRebel $formerRebel): JsonResponse
    {
        $data = $request->validate([
            'reintegration_status' => ['required', Rule::in(['Not-Started', 'On-going', 'Completed'])],
            'reintegration_date' => ['nullable', 'date'],
        ]);

        $formerRebel->programStatus()->updateOrCreate(
            ['former_rebel_id' => $formerRebel->id],
            [
                'reintegration_status' => $data['reintegration_status'],
                'reintegration_date' => $data['reintegration_date'] ?? now()->toDateString(),
                'updated_by' => $request->user()->name,
            ]
        );

        return response()->json(['success' => true]);
    }

    public function saveLocation(Request $request, FormerRebel $formerRebel): JsonResponse
    {
        $data = $request->validate([
            'placement_address' => ['required', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $location = $this->geocodeAddress(
                $data['placement_address'],
                $data['landmark'] ?? null
            );
        } catch (ConnectionException|RuntimeException) {
            return response()->json([
                'message' => 'Location lookup is temporarily unavailable. Please try again.',
            ], 503);
        }

        if (! $location) {
            return response()->json([
                'message' => 'We could not locate that address. Please provide a more specific address or landmark.',
                'errors' => [
                    'placement_address' => [
                        'We could not locate that address. Please provide a more specific address or landmark.',
                    ],
                ],
            ], 422);
        }

        DB::transaction(function () use ($formerRebel, $data, $location, $request) {
            $formerRebel->update([
                'placement_address' => $data['placement_address'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
            ]);
            $formerRebel->locationHistories()->create([
                'placement_address' => $data['placement_address'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'updated_by' => $request->user()->name,
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function geocode(Request $request, FormerRebel $formerRebel): JsonResponse
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'registration_address' => ['nullable', 'boolean'],
        ]);

        $address = trim((string) ($data['address'] ?? ''));
        $landmark = $data['landmark'] ?? null;
        $registrationCandidates = collect();

        if ($data['registration_address'] ?? false) {
            $formerRebel->loadMissing(['barangay', 'municipality']);
            $barangay = $formerRebel->barangay?->name
                ? 'Barangay '.$formerRebel->barangay->name
                : null;
            $municipality = $formerRebel->municipality?->name;

            $registrationCandidates = collect([
                [
                    'parts' => [$formerRebel->residential_address, $barangay, $municipality, $formerRebel->province],
                    'match_level' => filled($formerRebel->residential_address) ? 'address' : 'barangay',
                ],
                [
                    'parts' => [$barangay, $municipality, $formerRebel->province],
                    'match_level' => 'barangay',
                ],
                [
                    'parts' => [$municipality, $formerRebel->province],
                    'match_level' => 'municipality',
                ],
            ])->map(function (array $candidate): array {
                $candidate['address'] = collect($candidate['parts'])
                    ->filter(fn ($part) => filled($part))
                    ->implode(', ');

                return $candidate;
            })->filter(fn (array $candidate) => $candidate['address'] !== '')
                ->unique('address')
                ->values();

            $address = (string) ($registrationCandidates->first()['address'] ?? '');
            $landmark = null;
        }

        if ($address === '') {
            return response()->json([
                'message' => ($data['registration_address'] ?? false)
                    ? 'Saved residential address could not be located automatically.'
                    : 'Enter a placement address to locate it on the map.',
            ], 422);
        }

        try {
            if ($data['registration_address'] ?? false) {
                $location = null;

                foreach ($registrationCandidates as $candidate) {
                    $location = $this->geocodeAddress($candidate['address']);

                    if ($location) {
                        $location['match_level'] = $candidate['match_level'];
                        break;
                    }
                }
            } else {
                $location = $this->geocodeAddress($address, $landmark);
            }
        } catch (ConnectionException|RuntimeException) {
            return response()->json([
                'message' => 'Location lookup is temporarily unavailable. Please try again.',
            ], 503);
        }

        if (! $location) {
            return response()->json([
                'message' => ($data['registration_address'] ?? false)
                    ? 'Saved residential address could not be located automatically.'
                    : 'Location could not be found. Please provide a more specific address or landmark.',
            ], 422);
        }

        return response()->json(['success' => true] + $location);
    }

    public function locationHistory(FormerRebel $formerRebel): JsonResponse
    {
        return response()->json(
            $formerRebel->locationHistories()->latest()->get()
        );
    }

    /** @return array{latitude: string, longitude: string, display_name: string}|null */
    private function geocodeAddress(string $address, ?string $landmark = null): ?array
    {
        $query = collect([
            trim($address),
            filled($landmark) ? 'near '.trim($landmark) : null,
            'Philippines',
        ])->filter(fn ($part) => filled($part))->implode(', ');

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'User-Agent' => config('app.name', 'ALLSHIELD').'/1.0 ('.config('app.url').')',
                ])
                ->connectTimeout(5)
                ->timeout(10)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'ph',
                    'q' => $query,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Nominatim geocoding connection failed.', [
                'query' => $query,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (! $response->successful()) {
            Log::warning('Nominatim geocoding returned an unsuccessful response.', [
                'query' => $query,
                'status' => $response->status(),
                'content_type' => $response->header('Content-Type'),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new RuntimeException('Geocoding request failed with HTTP '.$response->status().'.');
        }

        $result = $response->json('0');
        if (! is_array($result)
            || ! is_numeric($result['lat'] ?? null)
            || ! is_numeric($result['lon'] ?? null)) {
            return null;
        }

        $latitude = (float) $result['lat'];
        $longitude = (float) $result['lon'];
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [
            'latitude' => number_format($latitude, 8, '.', ''),
            'longitude' => number_format($longitude, 8, '.', ''),
            'display_name' => (string) ($result['display_name'] ?? $address),
        ];
    }

    public function storeSkill(Request $request, FormerRebel $formerRebel): JsonResponse
    {
        $data = $request->validate([
            'skill_name' => ['required', 'string', 'max:255'],
            'proficiency_level' => ['required', Rule::in(['Beginner', 'Intermediate', 'Advanced'])],
        ]);

        // dedupe by name
        $skill = $formerRebel->skills()->firstOrCreate(
            ['skill_name' => $data['skill_name']],
            ['proficiency_level' => $data['proficiency_level']]
        );

        return response()->json(['success' => true, 'skill' => $skill]);
    }

    public function destroySkill(FrSkill $skill): JsonResponse
    {
        $skill->delete();

        return response()->json(['success' => true]);
    }

    /** Static vocational-skill autocomplete list (replaces get_skills_suggestions). */
    public function skillSuggestions(Request $request): JsonResponse
    {
        $all = [
            'Welding', 'Carpentry', 'Masonry', 'Plumbing', 'Electrical Installation',
            'Automotive Servicing', 'Driving', 'Farming', 'Livestock Raising', 'Fishing',
            'Cooking', 'Baking', 'Dressmaking', 'Tailoring', 'Hairdressing',
            'Computer Literacy', 'Electronics Repair', 'Handicrafts', 'Painting', 'Landscaping',
        ];
        $term = strtolower($request->query('term', ''));
        $matches = $term
            ? array_values(array_filter($all, fn ($s) => str_contains(strtolower($s), $term)))
            : $all;

        return response()->json($matches);
    }

    public function storeAssistance(Request $request, FormerRebel $formerRebel): JsonResponse
    {
        $data = $request->validate([
            'assistance_type' => ['required', 'string', 'max:255'],
            'date_received' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['Pending', 'In Progress', 'Completed'])],
            'certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:25600'],
        ]);

        $path = null;
        if ($request->hasFile('certificate')) {
            $path = $request->file('certificate')->store('fr/certificates', 'public');
        }

        $assistance = $formerRebel->assistances()->create([
            'assistance_type' => $data['assistance_type'],
            'date_received' => $data['date_received'] ?? null,
            'status' => $data['status'] ?? 'Pending',
            'certificate_file' => $path,
        ]);

        return response()->json(['success' => true, 'assistance' => $assistance]);
    }

    public function destroyAssistance(FrGovernmentAssistance $assistance): JsonResponse
    {
        if ($assistance->certificate_file) {
            Storage::disk('public')->delete($assistance->certificate_file);
        }
        $assistance->delete();

        return response()->json(['success' => true]);
    }

    public function updateEducationWork(Request $request, FormerRebel $formerRebel): JsonResponse
    {
        $data = $request->validate([
            'educational_attainment' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
        ]);

        $formerRebel->educationWorks()->create($data);
        // keep the FR's denormalized occupation in sync
        if (! empty($data['occupation'])) {
            $formerRebel->update(['occupation' => $data['occupation']]);
        }

        return response()->json(['success' => true]);
    }
}
