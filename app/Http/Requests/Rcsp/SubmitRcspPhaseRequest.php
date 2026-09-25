<?php

namespace App\Http\Requests\Rcsp;

use App\Models\RcspActivity;
use App\Models\RcspForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class SubmitRcspPhaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $barangay = $this->route('rcspBarangay');
        $activity = $this->route('activity');

        return $barangay && $activity
            && ($this->user()?->can('submitActivity', [$barangay, $activity]) ?? false);
    }

    public function rules(): array
    {
        return [
            'conduct' => ['required', 'in:yes,no,n/a'],
            'evidence' => [
                'nullable',
                'extensions:pdf,doc,docx,jpg,jpeg,png',
                File::types(['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])
                    ->max(self::maxEvidenceKilobytes()),
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $barangay = $this->route('rcspBarangay');
            $activity = $this->route('activity');
            $activity?->loadMissing('phase');
            if (! $barangay || ! $activity || ! $activity->phase
                || $activity->phase->catalog_key !== $barangay->catalog_key
                || $activity->phase->number !== $barangay->current_phase
                || ! RcspActivity::forBarangayPhase($barangay, $activity->phase)->whereKey($activity->id)->exists()) {
                $validator->errors()->add('activity', 'The activity must belong to this barangay and its current phase.');

                return;
            }

            $latest = RcspForm::where('rcsp_barangay_id', $barangay->id)
                ->where('rcsp_phase_id', $activity->rcsp_phase_id)
                ->where('rcsp_activity_id', $activity->id)
                ->orderByDesc('submission_version')->orderByDesc('id')->first();
            if ($latest && ! in_array($latest->status, ['disapproved', 'to be complied', 'to be conducted'], true)) {
                $validator->errors()->add('activity', 'Only a returned activity may be resubmitted.');
            }

            if ($this->input('conduct') === 'yes' && ! $this->hasFile('evidence') && ! $latest?->file) {
                $validator->errors()->add('evidence', 'Evidence is required when the activity is marked as Conducted.');
            }

            $this->validateEvidenceSignature($validator);

            $allowed = ['_token', 'conduct', 'evidence'];
            if (array_diff(array_keys($this->all()), $allowed)) {
                $validator->errors()->add('request', 'Unknown activity submission fields are not accepted.');
            }
        }];
    }

    public static function maxEvidenceKilobytes(): int
    {
        $limits = [25 * 1024];
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $kilobytes = self::iniSizeToKilobytes((string) ini_get($setting));
            if ($kilobytes > 0) {
                $limits[] = $kilobytes;
            }
        }

        return min($limits);
    }

    public static function maxEvidenceMegabytes(): int
    {
        return (int) floor(self::maxEvidenceKilobytes() / 1024);
    }

    private static function iniSizeToKilobytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (float) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => (int) floor($number * 1024 * 1024),
            'm' => (int) floor($number * 1024),
            'k' => (int) floor($number),
            default => (int) floor($number / 1024),
        };
    }

    private function validateEvidenceSignature(Validator $validator): void
    {
        $evidence = $this->file('evidence');
        if (! $evidence || ! $evidence->isValid()) {
            return;
        }

        $extension = strtolower($evidence->getClientOriginalExtension());
        $mime = strtolower((string) $evidence->getMimeType());
        $allowed = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/x-ole-storage', 'application/cdfv2'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
        ];

        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true)) {
            $validator->errors()->add('evidence', 'The evidence file extension does not match its detected type.');

            return;
        }

        if (! $this->hasExpectedSignature($evidence->getPathname(), $extension)) {
            $validator->errors()->add('evidence', 'The evidence file content does not match its extension.');
        }
    }

    private function hasExpectedSignature(string $path, string $extension): bool
    {
        $signature = file_get_contents($path, false, null, 0, 8);
        if ($signature === false) {
            return false;
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $image = @getimagesize($path);

            return is_array($image) && match ($extension) {
                'jpg', 'jpeg' => ($image['mime'] ?? null) === 'image/jpeg',
                'png' => ($image['mime'] ?? null) === 'image/png',
            };
        }

        return match ($extension) {
            'pdf' => str_starts_with($signature, '%PDF-'),
            'doc' => str_starts_with($signature, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'docx' => $this->isDocxArchive($path, $signature),
            default => false,
        };
    }

    private function isDocxArchive(string $path, string $signature): bool
    {
        if (! str_starts_with($signature, "PK\x03\x04") || ! class_exists(\ZipArchive::class)) {
            return false;
        }

        $archive = new \ZipArchive;
        if ($archive->open($path) !== true) {
            return false;
        }

        $valid = $archive->locateName('[Content_Types].xml') !== false
            && $archive->locateName('word/document.xml') !== false;
        $archive->close();

        return $valid;
    }
}
