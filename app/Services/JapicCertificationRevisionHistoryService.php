<?php

namespace App\Services;

use App\Models\JapicCertificationProcessing;
use App\Support\JapicCertificationDraftSchema;
use Illuminate\Support\Str;

class JapicCertificationRevisionHistoryService
{
    private const NARRATIVE_LABELS = [
        'fr_name' => 'FR name',
        'residence' => 'FR residence',
        'former_organization_or_category' => 'Former organization or category',
        'areas_of_operation' => 'Area of operation',
        'affiliated_organization' => 'Affiliated organization',
        'surrendered_to' => 'Surrendered to',
        'surrendered_on' => 'Date surrendered',
        'surrendered_at' => 'Place surrendered',
    ];

    public function __construct(private readonly JapicCertificationDraftSchema $schema) {}

    public function viewModel(JapicCertificationProcessing $processing, ?int $selectedRevision = null): array
    {
        $histories = $processing->draftHistories()
            ->with('savedBy:id,name')
            ->orderBy('revision')
            ->get();
        $normalized = $histories->mapWithKeys(fn ($history): array => [
            $history->revision => $this->schema->forReading($history->payload, $processing->control_number),
        ]);
        $items = $histories->values()->map(function ($history, int $index) use ($histories, $normalized): array {
            $previous = $index === 0 ? null : $normalized->get($histories[$index - 1]->revision);
            $changes = $this->differences($previous, $normalized->get($history->revision));

            return [
                'revision' => $history->revision,
                'saved_at' => $history->saved_at,
                'saved_by' => $this->display($history->savedBy?->name) ?? 'System',
                'summary' => $index === 0 ? 'Initial draft.' : $this->summary($changes),
                'initial' => $index === 0,
                'changes' => $changes,
            ];
        });

        $selected = $selectedRevision === null
            ? $items->last()
            : $items->firstWhere('revision', $selectedRevision);
        abort_if($selectedRevision !== null && $selected === null, 404);

        $workflowEvents = $processing->histories()
            ->with('actor:id,name')
            ->oldest('occurred_at')
            ->get()
            ->map(fn ($history): array => [
                'event' => Str::headline($history->event->value),
                'from_status' => $history->from_status?->value,
                'to_status' => $history->to_status->value,
                'actor' => $this->display($history->actor?->name) ?? 'System',
                'occurred_at' => $history->occurred_at,
                'document_version_id' => $history->document_version_id,
                'completion_path' => match (data_get($history->metadata, 'completion_path')) {
                    'direct_from_pending' => 'Final signed certification uploaded directly from Pending',
                    'direct_from_drafting' => 'Final signed certification uploaded directly from Drafting',
                    'signed_workflow' => 'Final signed certification uploaded after preparation for signature',
                    default => null,
                },
            ]);
        $documentVersions = $processing->documentVersions()
            ->with(['uploader:id,name', 'replacesVersion:id,version_number'])
            ->orderBy('version_number')
            ->get()
            ->map(fn ($version): array => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'original_filename' => $version->original_filename,
                'mime_type' => $version->mime_type,
                'size_bytes' => $version->size_bytes,
                'sha256' => $version->sha256,
                'uploaded_by' => $this->display($version->uploader?->name) ?? 'System',
                'uploaded_at' => $version->uploaded_at,
                'replaces_version_number' => $version->replacesVersion?->version_number,
            ]);

        return [
            'processing' => $processing,
            'revisions' => $items,
            'selectedRevision' => $selected,
            'workflowEvents' => $workflowEvents,
            'documentVersions' => $documentVersions,
        ];
    }

    private function differences(?array $previous, array $current): array
    {
        $before = $previous === null ? [] : $this->fields($previous);
        $after = $this->fields($current);
        $keys = $previous === null ? array_keys($after) : array_values(array_unique([...array_keys($before), ...array_keys($after)]));

        return collect($keys)->filter(fn (string $key): bool => $previous === null || ($before[$key]['value'] ?? null) !== ($after[$key]['value'] ?? null))
            ->map(function (string $key) use ($after, $before, $previous): array {
                $oldDisplay = $before[$key]['display'] ?? 'Not provided';
                $newDisplay = $after[$key]['display'] ?? 'Not provided';
                if ($key === 'photo' && $previous !== null && ($before[$key]['value'] ?? null) !== ($after[$key]['value'] ?? null)) {
                    $oldDisplay = ($before[$key]['value'] ?? null) ? 'Previous JAPIC certification photograph' : 'Authoritative CDR photograph';
                    $newDisplay = ($after[$key]['value'] ?? null) ? 'Replacement JAPIC certification photograph' : 'Authoritative CDR photograph';
                }

                return [
                    'field' => $after[$key]['label'] ?? $before[$key]['label'],
                    'previous_value' => $previous === null ? null : $oldDisplay,
                    'new_value' => $newDisplay,
                ];
            })->values()->all();
    }

    private function fields(array $payload): array
    {
        $certificate = $payload['certificate'];
        $fields = [
            'date_issued' => $this->field('Date issued', $certificate['date_issued'] ?? null),
            'control_number' => $this->field('Control number', $certificate['control_number'] ?? null),
        ];
        foreach (self::NARRATIVE_LABELS as $key => $label) {
            $fields['narrative.'.$key] = $this->field($label, data_get($certificate, 'narrative_values.'.$key));
        }
        foreach (['prepared_by' => 'Prepared By', 'attested_by' => 'Attested By'] as $section => $label) {
            $rows = $certificate[$section] ?? [];
            foreach ($rows as $index => $row) {
                $number = $index + 1;
                $fields["{$section}.{$index}.full_name"] = $this->field("{$label} {$number} name", $row['full_name'] ?? null);
                $fields["{$section}.{$index}.rank"] = $this->field("{$label} {$number} rank", $row['rank'] ?? null);
            }
        }
        $photo = data_get($certificate, 'photo_version_id');
        $fields['photo'] = [
            'label' => 'Certification photograph',
            'value' => $photo,
            'display' => $photo ? 'JAPIC certification photograph' : 'Authoritative CDR photograph',
        ];

        return $fields;
    }

    private function field(string $label, mixed $value): array
    {
        $value = $this->display($value);

        return ['label' => $label, 'value' => $value, 'display' => $value ?? 'Not provided'];
    }

    private function display(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function summary(array $changes): string
    {
        if ($changes === []) {
            return 'No approved certification fields changed.';
        }

        $labels = collect($changes)->pluck('field')->unique()->take(3)->implode(', ');

        return $labels.(count($changes) > 3 ? ' and additional fields changed.' : ' changed.');
    }
}
