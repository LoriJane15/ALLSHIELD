<?php

namespace Database\Seeders;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\Barangay;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\PswdoEnrollment;
use App\Models\User;
use App\Services\PswdoEnrollmentIntakeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LocalPswdoDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('PSWDO fixture seeding is permitted only in the automated test environment.');
        }

        $municipality = Municipality::firstOrCreate(['name' => 'DEMO Municipality']);
        $ib39 = User::where('username', 'ib39_officer')->firstOrFail();
        $japic = User::where('username', 'japic_officer')->firstOrFail();
        $pswdo = User::where('username', 'pswdo_officer')->firstOrFail();

        $completedBarangay = Barangay::firstOrCreate([
            'municipality_id' => $municipality->id,
            'name' => 'DEMO Completed Barangay',
        ]);
        $progressBarangay = Barangay::firstOrCreate([
            'municipality_id' => $municipality->id,
            'name' => 'DEMO In-Progress Barangay',
        ]);

        $complete = $this->eligibleEnrollment(
            'DEMO-PSWDO-001', 'Taylor', 'Complete', $municipality, $completedBarangay, $ib39, $japic,
        );
        $pending = $this->eligibleEnrollment(
            'DEMO-PSWDO-002', 'Morgan', 'Pending', $municipality, $progressBarangay, $ib39, $japic,
        );

        $this->storeDocuments($complete, PswdoEnrollmentDocumentType::cases(), $pswdo);
        $this->storeDocuments($pending, [
            PswdoEnrollmentDocumentType::EclipEnrollmentForm,
            PswdoEnrollmentDocumentType::InitialInterviewForm,
        ], $pswdo);
    }

    private function eligibleEnrollment(
        string $reference,
        string $firstName,
        string $lastName,
        Municipality $municipality,
        Barangay $barangay,
        User $ib39,
        User $japic,
    ): PswdoEnrollment {
        $record = Ib39SurfacedFormerRebel::query()->where('reference_number', $reference)->first();
        if (! $record) {
            $record = Ib39SurfacedFormerRebel::query()->forceCreate([
                'reference_number' => $reference,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'category' => Ib39FrCategory::RegularMember,
                'province' => 'DEMO Province',
                'municipality_id' => $municipality->id,
                'barangay_id' => $barangay->id,
                'specific_location' => 'DEMO PSWDO intake location',
                'surfaced_at' => now()->subMonths(8)->toDateString(),
                'possessed_firearms' => false,
                'initial_remarks' => 'DEMO PSWDO eligibility record. No real person or case data.',
                'created_by' => $ib39->id,
            ]);
        }

        $cdr = $record->cdrProcessing()->firstOrCreate([], [
            'status' => Ib39CdrStatus::Pending,
        ]);
        $cdrFinal = $cdr->documentVersions()->firstOrCreate(['version_number' => 1], [
            'source_type' => Ib39CdrDocumentSource::Uploaded,
            'storage_path' => "demo/cdr/{$reference}/final.pdf",
            'original_filename' => "{$reference}-cdr-final.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 44,
            'sha256' => hash('sha256', "{$reference}-cdr-final"),
            'created_by' => $ib39->id,
            'finalized_at' => now()->subMonths(6),
        ]);
        $cdr->update([
            'status' => Ib39CdrStatus::Completed,
            'completed_at' => now()->subMonths(6),
            'completed_by' => $ib39->id,
            'current_final_version_id' => $cdrFinal->id,
            'remarks' => 'DEMO CDR final record completed for PSWDO workflow testing.',
        ]);

        $japicProcessing = JapicCertificationProcessing::query()
            ->where('ib39_surfaced_former_rebel_id', $record->id)->first();
        if (! $japicProcessing) {
            $receivedAt = now()->subMonths(5);
            $japicProcessing = JapicCertificationProcessing::query()->forceCreate([
                'ib39_surfaced_former_rebel_id' => $record->id,
                'triggering_cdr_document_version_id' => $cdrFinal->id,
                'status' => JapicCertificationStatus::Completed,
                'received_at' => $receivedAt,
                'due_at' => $receivedAt->copy()->addDays(14),
                'completed_at' => now()->subMonths(4),
                'completed_by' => $japic->id,
                'lock_version' => 1,
            ]);
        }
        $japicFinal = $japicProcessing->documentVersions()->firstOrCreate(['version_number' => 1], [
            'storage_path' => "demo/japic/{$reference}/final.pdf",
            'original_filename' => "{$reference}-japic-final.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 48,
            'sha256' => hash('sha256', "{$reference}-japic-final"),
            'uploaded_by' => $japic->id,
            'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true,
            'uploaded_at' => now()->subMonths(4),
        ]);
        $japicProcessing->forceFill([
            'status' => JapicCertificationStatus::Completed,
            'completed_at' => now()->subMonths(4),
            'completed_by' => $japic->id,
            'current_final_version_id' => $japicFinal->id,
        ])->save();

        return DB::transaction(fn () => app(PswdoEnrollmentIntakeService::class)->receiveEligible($record->id));
    }

    /** @param array<int, PswdoEnrollmentDocumentType> $types */
    private function storeDocuments(PswdoEnrollment $enrollment, array $types, User $pswdo): void
    {
        foreach ($types as $type) {
            $contents = "%PDF-1.4\n% DEMO PSWDO {$type->value}\n%%EOF";
            $path = "pswdo/enrollments/{$enrollment->id}/demo-{$type->value}.pdf";
            Storage::disk('local')->put($path, $contents);
            $enrollment->documents()->firstOrCreate(['document_type' => $type->value], [
                'storage_path' => $path,
                'original_filename' => "DEMO-{$type->value}.pdf",
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'uploaded_by' => $pswdo->id,
                'correct_document_type_confirmed' => true,
                'belongs_to_fr_confirmed' => true,
                'final_signed_confirmed' => true,
                'uploaded_at' => now()->subDays(3),
            ]);
        }
    }
}
