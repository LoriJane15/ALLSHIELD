<?php

namespace App\Support;

use App\Models\JapicCertificationProcessing;
use Illuminate\Support\Str;

class JapicCertificationDraftSchema
{
    public const VERSION = 2;

    public const LEGACY_VERSION = 1;

    public const MAX_PERSONNEL_ROWS = 8;

    public const COPY_FURNISHED = [
        'Task Force Balik Loob (TFBL);',
        'DILG Provincial/HUC/ICC Office;',
        'E-CLIP and Amnesty Program Cluster of NTF-ELCAC.',
    ];

    public const NARRATIVE_FIELDS = [
        'fr_name',
        'residence',
        'former_organization_or_category',
        'areas_of_operation',
        'affiliated_organization',
        'surrendered_to',
        'surrendered_on',
        'surrendered_at',
    ];

    private const LEGACY_POSITIONS = [
        'provincial_afp',
        'provincial_pnp',
        'area_afp',
        'area_pnp',
    ];

    public function sourceSnapshot(JapicCertificationProcessing $processing): array
    {
        $processing->loadMissing(['surfacedFormerRebel.municipality', 'surfacedFormerRebel.barangay', 'triggeringCdrDocumentVersion.processing']);
        $fr = $processing->surfacedFormerRebel;
        $version = $processing->triggeringCdrDocumentVersion;
        abort_unless($version && $version->processing?->ib39_surfaced_former_rebel_id === $fr->id, 409, 'The authoritative CDR source is unavailable.');
        $snapshot = $version->content_snapshot ?? [];
        $content = $snapshot['content'] ?? [];
        $gender = $this->clean($content['gender'] ?? null);
        $areas = collect($content['posting_areas'] ?? [])->pluck('place')->map(fn ($value) => $this->clean($value))->filter()->unique()->values()->all();
        $location = collect([$fr->specific_location, $fr->barangay?->name, $fr->municipality?->name, $fr->province])->filter()->implode(', ');

        return [
            'fr_id' => $fr->id,
            'fr_reference' => $fr->reference_number,
            'triggering_cdr_document_version_id' => $version->id,
            'subject_photo_version_id' => isset($snapshot['fr_photo_version_id']) ? (int) $snapshot['fr_photo_version_id'] : null,
            'subject_name' => $fr->display_name,
            'alias' => $this->clean($content['alias'] ?? null),
            'gender' => $gender,
            'pronouns' => match (Str::lower((string) $gender)) {
                'female' => ['subject' => 'she', 'object' => 'her', 'possessive' => 'her'],
                'male' => ['subject' => 'he', 'object' => 'him', 'possessive' => 'his'],
                default => ['subject' => 'they', 'object' => 'them', 'possessive' => 'their'],
            },
            'classification' => $this->clean($content['classification'] ?? null),
            'residential_address' => $this->clean($content['present_address'] ?? null) ?: $location,
            'former_position' => $this->clean($content['latest_position'] ?? $content['position_held'] ?? null),
            'former_organization' => $this->clean($content['organization_affiliation'] ?? $content['individual_affiliation'] ?? null),
            'operating_areas' => $areas,
            'affiliation_period' => $this->clean($content['recruitment_date'] ?? $content['duration_with_group'] ?? null),
            'surfacing_date' => $fr->surfaced_at?->format('Y-m-d'),
            'surfacing_location' => $location,
        ];
    }

    public function normalize(array $manual, array $source, ?string $controlNumber, ?int $photoVersionId): array
    {
        $certificate = $manual['certificate'] ?? [];
        $providedNarrative = is_array($certificate['narrative_values'] ?? null) ? $certificate['narrative_values'] : [];
        $narrative = collect(self::NARRATIVE_FIELDS)->mapWithKeys(
            fn (string $field): array => [$field => $providedNarrative[$field] ?? null]
        )->all();

        return $this->normalizeValue([
            'schema_version' => self::VERSION,
            'source_snapshot' => $source,
            'certificate' => [
                'control_number' => $controlNumber,
                'date_issued' => $certificate['date_issued'] ?? null,
                'narrative_values' => $narrative,
                'prepared_by' => $this->personnel($certificate['prepared_by'] ?? []),
                'attested_by' => $this->personnel($certificate['attested_by'] ?? []),
                'photo_version_id' => $photoVersionId,
            ],
        ]);
    }

    public function initial(array $source, ?string $controlNumber, ?int $photoVersionId): array
    {
        $payload = $this->fromLegacy([
            'schema_version' => self::LEGACY_VERSION,
            'source_snapshot' => $source,
            'certificate' => [],
            'signatories' => [],
        ], $controlNumber);
        data_set($payload, 'certificate.photo_version_id', $photoVersionId);

        return $this->forReading($payload, $controlNumber);
    }

    public function forReading(array $payload, ?string $controlNumber): array
    {
        return match ((int) ($payload['schema_version'] ?? 0)) {
            self::VERSION => $this->normalize(
                ['certificate' => $payload['certificate'] ?? []],
                $payload['source_snapshot'] ?? [],
                data_get($payload, 'certificate.control_number', $controlNumber),
                $this->integerOrNull(data_get($payload, 'certificate.photo_version_id')),
            ),
            self::LEGACY_VERSION => $this->fromLegacy($payload, $controlNumber),
            default => abort(409, 'The draft schema is unsupported.'),
        };
    }

    public function fingerprint(array $payload): string
    {
        return hash_hmac('sha256', json_encode($this->normalizeValue($payload), JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function wording(array $payload): array
    {
        [$subject, $possessive] = match (Str::lower((string) data_get($payload, 'source_snapshot.gender'))) {
            'female' => ['She', 'her'],
            'male' => ['He', 'his'],
            default => ['The former rebel', 'their'],
        };

        return [
            'affiliation_subject' => $subject,
            'possessive' => $possessive,
            'purpose' => "This certification is being issued to attest {$possessive} legitimacy as a former rebel to support {$possessive} application for the Enhanced Comprehensive Local Integration Program(E-CLIP).",
        ];
    }

    public function controlNumberHash(string $controlNumber): string
    {
        $normalized = Str::lower(Str::squish(Str::of($controlNumber)->trim()->toString()));

        return hash_hmac('sha256', 'japic-control-number:'.$normalized, (string) config('app.key'));
    }

    public function missingForSigning(array $payload, ?string $controlNumber): array
    {
        $certificate = $this->forReading($payload, $controlNumber)['certificate'];
        $missing = [];

        foreach (['control_number', 'date_issued'] as $field) {
            if (blank($certificate[$field] ?? null)) {
                $missing[] = 'certificate.'.$field;
            }
        }
        foreach (self::NARRATIVE_FIELDS as $field) {
            if (blank(data_get($certificate, 'narrative_values.'.$field))) {
                $missing[] = 'certificate.narrative_values.'.$field;
            }
        }
        foreach (['prepared_by', 'attested_by'] as $section) {
            $rows = $certificate[$section] ?? [];
            if ($rows === []) {
                $missing[] = 'certificate.'.$section;
            }
            foreach ($rows as $index => $row) {
                foreach (['full_name', 'rank'] as $field) {
                    if (blank($row[$field] ?? null)) {
                        $missing[] = "certificate.{$section}.{$index}.{$field}";
                    }
                }
            }
        }

        return $missing;
    }

    private function fromLegacy(array $payload, ?string $controlNumber): array
    {
        $source = $payload['source_snapshot'] ?? [];
        $legacyCertificate = $payload['certificate'] ?? [];
        $signatories = $payload['signatories'] ?? [];
        $name = collect([
            $this->clean($source['subject_name'] ?? null),
            filled($source['alias'] ?? null) ? '@'.ltrim((string) $source['alias'], '@') : null,
            filled($source['classification'] ?? null) ? '('.$source['classification'].')' : null,
        ])->filter()->implode(' ');
        $former = collect([
            $this->clean($source['former_position'] ?? null),
            filled($source['former_organization'] ?? null) ? 'of '.$source['former_organization'] : null,
        ])->filter()->implode(' ');
        $areas = collect($source['operating_areas'] ?? [])
            ->push($legacyCertificate['operating_area_supplement'] ?? null)
            ->map(fn ($value) => $this->clean($value))->filter()->unique()->implode(', ');

        return $this->normalize([
            'certificate' => [
                'date_issued' => $legacyCertificate['date_issued'] ?? null,
                'narrative_values' => [
                    'fr_name' => $name,
                    'residence' => $source['residential_address'] ?? null,
                    'former_organization_or_category' => $former,
                    'areas_of_operation' => $areas,
                    'affiliated_organization' => 'Communist Terrorist Group (CTG)',
                    'surrendered_to' => $legacyCertificate['surrendering_unit'] ?? null,
                    'surrendered_on' => $legacyCertificate['surrender_date'] ?? $source['surfacing_date'] ?? null,
                    'surrendered_at' => $legacyCertificate['surrender_location'] ?? $source['surfacing_location'] ?? null,
                ],
                'prepared_by' => $this->legacyPersonnel($signatories, array_slice(self::LEGACY_POSITIONS, 0, 2)),
                'attested_by' => $this->legacyPersonnel($signatories, array_slice(self::LEGACY_POSITIONS, 2)),
            ],
        ], $source, $controlNumber, null);
    }

    private function legacyPersonnel(array $signatories, array $positions): array
    {
        return collect($positions)->map(function (string $position) use ($signatories): array {
            $row = $signatories[$position] ?? [];

            return [
                'full_name' => collect([$row['name'] ?? null, $row['suffix'] ?? null])->filter()->implode(' '),
                'rank' => $row['rank'] ?? null,
            ];
        })->filter(fn (array $row): bool => filled($row['full_name']) || filled($row['rank']))->values()->all();
    }

    private function personnel(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect(array_slice($rows, 0, self::MAX_PERSONNEL_ROWS))
            ->map(function ($row): array {
                $row = is_array($row) ? $row : [];

                return ['full_name' => $row['full_name'] ?? null, 'rank' => $row['rank'] ?? null];
            })
            ->values()->all();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = preg_replace('/\R/u', "\n", trim($value));

            return $value === '' ? null : (preg_match('/\n/u', $value) ? $value : Str::squish($value));
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->normalizeValue($item), $value);
    }

    private function clean(mixed $value): ?string
    {
        return is_scalar($value) ? $this->normalizeValue((string) $value) : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
