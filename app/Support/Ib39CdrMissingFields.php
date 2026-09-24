<?php

namespace App\Support;

class Ib39CdrMissingFields
{
    public function analyze(array $content, bool $hasPhoto): array
    {
        $missing = [];

        foreach (Ib39CdrFormSchema::orderedBlocks() as [$kind, $key]) {
            if ($kind === 'section') {
                $this->checkFields($missing, Ib39CdrFormSchema::sections()[$key], $content);
            } else {
                $this->checkTable($missing, Ib39CdrFormSchema::repeatableSections()[$key], $key, $content);
            }
        }

        if (! $hasPhoto) {
            $missing['FR Photo'][] = 'FR Photo (optional)';
        }

        return $missing;
    }

    private function checkFields(array &$missing, array $section, array $content): void
    {
        $detailStatuses = [
            'significant_information' => 'significant_information_status',
            'white_area_information' => 'white_area_status',
            'projected_enemy_operations' => 'projected_enemy_status',
        ];

        foreach ($section['fields'] as $key => $field) {
            if (isset($detailStatuses[$key])) {
                $status = $content[$detailStatuses[$key]] ?? null;
                if ($status === 'na') {
                    continue;
                }
            }

            if ($this->blank($content[$key] ?? null)) {
                $label = $field['label'];
                if ($label === 'Details') {
                    $label = match ($key) {
                        'significant_information' => 'Significant information/changes, plans and programs details',
                        'white_area_information' => 'Information related to white area details',
                        'projected_enemy_operations' => 'Projected operation of the enemy details',
                        default => $label,
                    };
                }
                $missing[$section['label']][] = $label;
            }
        }
    }

    private function checkTable(array &$missing, array $section, string $key, array $content): void
    {
        $statusKey = $section['status_key'] ?? null;
        $status = $statusKey ? ($content[$statusKey] ?? null) : null;
        if ($status === 'na') {
            return;
        }
        if ($statusKey && $this->blank($status)) {
            $missing[$section['label']][] = 'Applicability (Yes or N/A)';

            return;
        }

        $rows = $content[$key] ?? [];
        $meaningful = collect(is_array($rows) ? $rows : [])->contains(
            fn ($row) => is_array($row) && collect($row)->contains(fn ($value) => ! $this->blank($value))
        );
        if (! $meaningful) {
            $missing[$section['label']][] = $section['label'].' table';
        }
    }

    private function blank(mixed $value): bool
    {
        return ! is_string($value) || trim($value) === '';
    }
}
