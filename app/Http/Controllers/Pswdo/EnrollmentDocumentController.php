<?php

namespace App\Http\Controllers\Pswdo;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pswdo\UploadFinalEnrollmentDocumentRequest;
use App\Models\PswdoEnrollment;
use App\Models\PswdoEnrollmentDocument;
use App\Models\PswdoEnrollmentDocumentDraft;
use App\Services\PswdoEnrollmentDocumentService;
use App\Support\OfficialDocumentPdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnrollmentDocumentController extends Controller
{
    public function store(UploadFinalEnrollmentDocumentRequest $request, PswdoEnrollment $pswdoEnrollment, PswdoEnrollmentDocumentType $documentType, PswdoEnrollmentDocumentService $documents): RedirectResponse
    {
        $documents->uploadFinal(
            $pswdoEnrollment,
            $documentType,
            $request->file('document'),
            (int) $request->validated('lock_version'),
            (bool) $request->validated('correct_document_type_confirmed'),
            (bool) $request->validated('belongs_to_fr_confirmed'),
            (bool) $request->validated('final_signed_confirmed'),
            $request->user(),
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('pswdo.enrollments.workspace', [
            'pswdoEnrollment' => $pswdoEnrollment,
            'document_type' => $documentType->value,
        ])
            ->with('status', $documentType->label().' was stored securely and marked Completed.');
    }

    public function saveEclipDraft(Request $request, PswdoEnrollment $pswdoEnrollment): RedirectResponse|JsonResponse
    {
        Gate::authorize('editDraft', [$pswdoEnrollment, PswdoEnrollmentDocumentType::EclipEnrollmentForm]);
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'draft' => ['required', 'array:last_name,first_name,middle_name,address,reintegration_monitoring_number,japic_validation_date,civil_society_organization,other_government_agency,remarks,firearm_type,caliber,make,serial_number,firearm_remarks,date_of_issuance,place_of_issuance'],
            'draft.last_name' => ['required', 'string', 'max:100'],
            'draft.first_name' => ['required', 'string', 'max:100'],
            'draft.middle_name' => ['nullable', 'string', 'max:100'],
            'draft.address' => ['required', 'string', 'max:1000'],
            'draft.reintegration_monitoring_number' => ['nullable', 'string', 'max:100'],
            'draft.japic_validation_date' => ['nullable', 'date'],
            'draft.civil_society_organization' => ['nullable', 'string', 'max:500'],
            'draft.other_government_agency' => ['nullable', 'string', 'max:500'],
            'draft.remarks' => ['nullable', 'string', 'max:4000'],
            'draft.firearm_type' => ['nullable', 'string', 'max:500'],
            'draft.caliber' => ['nullable', 'string', 'max:255'],
            'draft.make' => ['nullable', 'string', 'max:255'],
            'draft.serial_number' => ['nullable', 'string', 'max:255'],
            'draft.firearm_remarks' => ['nullable', 'string', 'max:1000'],
            'draft.date_of_issuance' => ['nullable', 'date'],
            'draft.place_of_issuance' => ['nullable', 'string', 'max:500'],
        ]);
        $payload = collect($validated['draft'])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();

        return $this->saveDraftResponse(
            $request,
            $pswdoEnrollment,
            PswdoEnrollmentDocumentType::EclipEnrollmentForm,
            $payload,
            (int) $validated['revision'],
            'A newer E-CLIP draft exists. Reload before saving.',
            'E-CLIP Enrollment Form draft saved.',
        );
    }

    public function previewEclipDraft(PswdoEnrollment $pswdoEnrollment): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $draft = $this->eclipDraft($pswdoEnrollment);

        return view('pswdo.enrollments.documents.eclip-enrollment-form', [
            'payload' => $draft->payload,
            'printMode' => false,
            'backUrl' => route('pswdo.enrollments.workspace', [
                'pswdoEnrollment' => $pswdoEnrollment,
                'document_type' => PswdoEnrollmentDocumentType::EclipEnrollmentForm->value,
            ]),
            'downloadUrl' => route('pswdo.enrollments.eclip-draft.download', $pswdoEnrollment),
        ]);
    }

    public function downloadEclipDraft(PswdoEnrollment $pswdoEnrollment, OfficialDocumentPdf $pdf): Response
    {
        Gate::authorize('view', $pswdoEnrollment);
        $draft = $this->eclipDraft($pswdoEnrollment);
        $html = view('pswdo.enrollments.documents.eclip-enrollment-form', [
            'payload' => $draft->payload,
            'printMode' => true,
            'backUrl' => '',
            'downloadUrl' => '',
        ])->render();

        return response($pdf->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="eclip-enrollment-form-'.$pswdoEnrollment->id.'.pdf"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function saveInitialInterviewDraft(Request $request, PswdoEnrollment $pswdoEnrollment): RedirectResponse|JsonResponse
    {
        $type = PswdoEnrollmentDocumentType::InitialInterviewForm;
        Gate::authorize('editDraft', [$pswdoEnrollment, $type]);
        $validated = $request->validate($this->initialInterviewRules());

        return $this->saveDraftResponse(
            $request,
            $pswdoEnrollment,
            $type,
            $this->normalizeDraftPayload($validated['draft']),
            (int) $validated['revision'],
            'A newer Initial Interview Form draft exists. Reload before saving.',
            'Initial Interview Form draft saved.',
        );
    }

    public function previewInitialInterviewDraft(PswdoEnrollment $pswdoEnrollment): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $draft = $this->documentDraft($pswdoEnrollment, PswdoEnrollmentDocumentType::InitialInterviewForm);

        return view('pswdo.enrollments.documents.initial-interview-form', [
            'payload' => $draft->payload,
            'printMode' => false,
            'backUrl' => route('pswdo.enrollments.workspace', [
                'pswdoEnrollment' => $pswdoEnrollment,
                'document_type' => PswdoEnrollmentDocumentType::InitialInterviewForm->value,
            ]),
            'downloadUrl' => route('pswdo.enrollments.initial-interview-draft.download', $pswdoEnrollment),
        ]);
    }

    public function downloadInitialInterviewDraft(PswdoEnrollment $pswdoEnrollment, OfficialDocumentPdf $pdf): Response
    {
        Gate::authorize('view', $pswdoEnrollment);
        $draft = $this->documentDraft($pswdoEnrollment, PswdoEnrollmentDocumentType::InitialInterviewForm);
        $html = view('pswdo.enrollments.documents.initial-interview-form', [
            'payload' => $draft->payload,
            'printMode' => true,
            'backUrl' => '',
            'downloadUrl' => '',
        ])->render();

        return response($pdf->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="initial-interview-form-'.$pswdoEnrollment->id.'.pdf"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function saveProfilingInterviewDraft(Request $request, PswdoEnrollment $pswdoEnrollment): RedirectResponse|JsonResponse
    {
        $type = PswdoEnrollmentDocumentType::ProfilingInterviewForm;
        Gate::authorize('editDraft', [$pswdoEnrollment, $type]);
        $validated = $request->validate(
            $this->profilingInterviewRules(),
            $this->profilingInterviewValidationMessages(),
        );

        return $this->saveDraftResponse(
            $request,
            $pswdoEnrollment,
            $type,
            $this->normalizeProfilingInterviewPayload($validated['draft']),
            (int) $validated['revision'],
            'A newer Profiling Interview Form draft exists. Reload before saving.',
            'Profiling Interview Form draft saved.',
        );
    }

    public function previewProfilingInterviewDraft(PswdoEnrollment $pswdoEnrollment): View
    {
        Gate::authorize('view', $pswdoEnrollment);
        $draft = $this->documentDraft($pswdoEnrollment, PswdoEnrollmentDocumentType::ProfilingInterviewForm);

        return view(
            'pswdo.enrollments.documents.profiling-interview-form',
            $this->profilingInterviewViewData($pswdoEnrollment, $draft, false),
        );
    }

    public function downloadProfilingInterviewDraft(PswdoEnrollment $pswdoEnrollment, OfficialDocumentPdf $pdf): Response
    {
        Gate::authorize('view', $pswdoEnrollment);
        $draft = $this->documentDraft($pswdoEnrollment, PswdoEnrollmentDocumentType::ProfilingInterviewForm);
        $html = view(
            'pswdo.enrollments.documents.profiling-interview-form',
            $this->profilingInterviewViewData($pswdoEnrollment, $draft, true),
        )->render();

        return response($pdf->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="profiling-interview-form-'.$pswdoEnrollment->id.'.pdf"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, PswdoEnrollment $pswdoEnrollment, PswdoEnrollmentDocument $document, PswdoEnrollmentDocumentService $documents): StreamedResponse|RedirectResponse
    {
        $unavailable = $this->unavailable($request, $pswdoEnrollment, $document);
        if ($unavailable) {
            return $unavailable;
        }

        return $documents->preview($pswdoEnrollment, $document, $request->user(), $request->ip(), $request->userAgent());
    }

    public function download(Request $request, PswdoEnrollment $pswdoEnrollment, PswdoEnrollmentDocument $document, PswdoEnrollmentDocumentService $documents): StreamedResponse|RedirectResponse
    {
        $unavailable = $this->unavailable($request, $pswdoEnrollment, $document);
        if ($unavailable) {
            return $unavailable;
        }

        return $documents->download($pswdoEnrollment, $document, $request->user(), $request->ip(), $request->userAgent());
    }

    private function unavailable(Request $request, PswdoEnrollment $enrollment, PswdoEnrollmentDocument $document): ?RedirectResponse
    {
        abort_unless($document->pswdo_enrollment_id === $enrollment->id, 404);
        $document->setRelation('enrollment', $enrollment);
        Gate::authorize('view', $document);
        if ($document->isConfirmedFinal()) {
            return null;
        }

        $enrollment->loadMissing('surfacedFormerRebel.japicCertificationProcessing');
        $record = $enrollment->surfacedFormerRebel;
        $destination = match (true) {
            $request->user()->hasRole('pswdo') => route('pswdo.enrollments.show', $enrollment),
            $request->user()->hasRole('japic') => route('japic.certifications.show', $record->japicCertificationProcessing),
            default => route('ib39.fr-profiles.show', $record),
        };

        return redirect($destination)->with('error', 'Document is not available yet.');
    }

    private function eclipDraft(PswdoEnrollment $enrollment): PswdoEnrollmentDocumentDraft
    {
        return $this->documentDraft($enrollment, PswdoEnrollmentDocumentType::EclipEnrollmentForm);
    }

    private function documentDraft(PswdoEnrollment $enrollment, PswdoEnrollmentDocumentType $type): PswdoEnrollmentDocumentDraft
    {
        return $enrollment->documentDrafts()->where('document_type', $type)->firstOrFail();
    }

    /** @param array<string, mixed> $payload */
    private function saveDraftResponse(
        Request $request,
        PswdoEnrollment $enrollment,
        PswdoEnrollmentDocumentType $type,
        array $payload,
        int $expectedRevision,
        string $conflictMessage,
        string $statusMessage,
    ): RedirectResponse|JsonResponse {
        $draft = DB::transaction(function () use ($enrollment, $request, $type, $payload, $expectedRevision, $conflictMessage): PswdoEnrollmentDocumentDraft {
            $locked = PswdoEnrollment::query()
                ->with(['documents', 'surfacedFormerRebel'])
                ->lockForUpdate()
                ->findOrFail($enrollment->id);
            Gate::authorize('editDraft', [$locked, $type]);
            $draft = $locked->documentDrafts()
                ->where('document_type', $type)
                ->lockForUpdate()
                ->first();
            $revision = $draft?->revision ?? 0;
            if ($revision !== $expectedRevision) {
                throw new ConflictHttpException($conflictMessage);
            }

            if (! $draft) {
                $draft = $locked->documentDrafts()->make([
                    'document_type' => $type,
                    'schema_version' => 1,
                ]);
            }
            $draft->forceFill([
                'payload' => $payload,
                'revision' => $revision + 1,
                'saved_by' => $request->user()->id,
                'saved_at' => now(),
            ])->save();

            return $draft;
        }, 5);

        if ($request->expectsJson()) {
            return response()->json([
                'saved' => true,
                'revision' => $draft->revision,
                'message' => 'All changes saved',
            ]);
        }

        return redirect()->route('pswdo.enrollments.workspace', [
            'pswdoEnrollment' => $enrollment,
            'document_type' => $type->value,
        ])->with('status', $statusMessage);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeDraftPayload(array $payload): array
    {
        return collect($payload)->map(function ($value) {
            if (is_array($value)) {
                return collect($value)
                    ->map(fn ($item) => is_string($item) ? trim($item) : $item)
                    ->filter(fn ($item): bool => filled($item))
                    ->values()
                    ->all();
            }

            return is_string($value) ? trim($value) : $value;
        })->all();
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeProfilingInterviewPayload(array $payload): array
    {
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return is_string($value) ? trim($value) : $value;
            }

            return collect($value)
                ->map(fn (mixed $item): mixed => $normalize($item))
                ->all();
        };

        $hasValue = function (mixed $value) use (&$hasValue): bool {
            if (is_array($value)) {
                return collect($value)->contains(fn (mixed $item): bool => $hasValue($item));
            }

            return $value !== null && $value !== '';
        };
        $normalized = $normalize($payload);

        $normalizeCode = static function (mixed $value): string {
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value), 'strlen'));
            }

            return trim((string) $value);
        };

        $normalized['partner_income_sources'] = $normalizeCode($normalized['partner_income_sources'] ?? '');

        foreach (['children', 'other_family_members'] as $collection) {
            $normalized[$collection] = collect($normalized[$collection] ?? [])
                ->filter(fn (mixed $row): bool => is_array($row) && $hasValue($row))
                ->map(function (array $row) use ($normalizeCode): array {
                    $row['income_sources'] = $normalizeCode($row['income_sources'] ?? '');

                    return $row;
                })
                ->values()
                ->all();
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function profilingInterviewViewData(
        PswdoEnrollment $enrollment,
        PswdoEnrollmentDocumentDraft $draft,
        bool $printMode,
    ): array {
        $payload = $draft->payload;
        $choices = $this->profilingInterviewChoices();

        return [
            'payload' => $payload,
            'printMode' => $printMode,
            'choices' => $choices,
            'incomeCodes' => $choices['income_codes'],
            'relationCodes' => $choices['relation_codes'],
            'backUrl' => $printMode ? '' : route('pswdo.enrollments.workspace', [
                'pswdoEnrollment' => $enrollment,
                'document_type' => PswdoEnrollmentDocumentType::ProfilingInterviewForm->value,
            ]),
            'downloadUrl' => $printMode ? '' : route('pswdo.enrollments.profiling-interview-draft.download', $enrollment),
        ];
    }

    /** @return array<string, array<string, string>> */
    private function profilingInterviewChoices(): array
    {
        return [
            'sex' => ['male' => 'Male', 'female' => 'Female'],
            'yes_no' => ['yes' => 'Yes', 'no' => 'No'],
            'yes_no_unknown' => ['yes' => 'Yes', 'no' => 'No', 'dont_know' => "Don't Know"],
            'yes_no_unsure' => ['yes' => 'Yes', 'no' => 'No', 'not_sure' => 'Not Sure'],
            'yes_no_unknown_unsure' => ['yes' => 'Yes', 'no' => 'No', 'dont_know' => "Don't Know", 'not_sure' => 'Not Sure'],
            'validity' => ['valid' => 'Valid', 'expired' => 'Expired'],
            'income_codes' => [
                '1' => 'Stay-home/Housewife Industry', '2' => 'Home-based Business',
                '3' => 'Agriculture-based', '4' => 'Regular Wage Earner',
                '5' => 'Crafts/Cottage', '6' => 'Trade', '7' => 'Professional', '8' => 'Others',
            ],
            'relation_codes' => [
                '1' => 'Grand Parents (including in-laws)', '2' => 'Parent/Parent-in-law (including in-laws)',
                '3' => 'Aunt/Uncle (including in-laws)', '4' => 'Cousin (including in-laws)',
                '5' => 'Brother/Sister', '6' => 'Grand Child', '7' => 'Others',
            ],
            'income_bands' => [
                '500_below' => '500 and below', '501_2500' => '501php – 2,500php',
                '2500_5000' => '2,500php – 5,000php', '5001_10000' => '5,001php – 10,000php',
                '10001_15000' => '10,001php – 15,000php', '15001_20000' => '15,001php – 20,000php',
                '20001_above' => '20,001php and above', 'dont_know' => "I don't know",
            ],
            'education' => [
                'did_not_enter_school' => 'Did not enter school', 'preschool' => 'Preschool',
                'elementary_level' => 'Elementary Level', 'elementary_graduate' => 'Elementary Graduate',
                'high_school_level' => 'High School Level', 'high_school_graduate' => 'High School Graduate',
                'college_level' => 'College Level', 'college_graduate' => 'College graduate',
                'post_graduate' => 'Post Graduate (Masters, Doctorate)', 'als' => 'ALS', 'vocational' => 'Vocational',
            ],
            'income_types' => ['wage_earner' => 'Wage Earner', 'self_employed' => 'Self-employed', 'others' => 'Others'],
            'wage_sources' => [
                'government' => 'Government (Public Sector)', 'private_firm' => 'Private Firm',
                'ngo_po' => 'NGO/PO', 'private_individual' => 'Private Individual',
                'seasonal_worker' => 'Seasonal worker', 'others' => 'Others',
            ],
            'self_employed_sources' => [
                'farmer_agriculture' => 'Farmer/Agriculture-based', 'fisheries' => 'Fisheries',
                'home_business' => 'Home-based business', 'cottage_industry' => 'Cottage Industry',
                'trader' => 'Trader', 'local_transport' => 'Local Transport Service', 'others' => 'Others',
            ],
            'skill_categories' => [
                'agriculture_fishery' => 'Agriculture and Fishery', 'construction' => 'Construction',
                'furniture_fixture' => 'Furniture and Fixture', 'garments' => 'Garments',
                'community_services' => 'Health, Social and other Community Development Services',
                'land_transportation' => 'Land Transportation', 'metals_engineering' => 'Metals and Engineering',
                'machine_operation' => 'Machine Operation', 'culinary' => 'Culinary',
                'food_processing' => 'Food Processing', 'other_technical' => 'Other technical skills',
            ],
            'desired_skill_categories' => [
                'agriculture_fishery' => 'Agriculture and Fishery', 'construction' => 'Construction',
                'furniture_fixture' => 'Furniture and Fixture', 'garments' => 'Garments',
                'community_services' => 'Health, Social and other Community Development Services',
                'land_transportation' => 'Land Transportation', 'metals_engineering' => 'Metals and Engineering',
                'culinary' => 'Culinary', 'other_technical' => 'Other technical skills',
            ],
            'current_skills' => [
                'agricultural_crops' => 'Agricultural Crops', 'livestock' => 'Livestock', 'aquaculture' => 'Aquaculture',
                'fish_capture' => 'Fish Capture', 'carpentry' => 'Carpentry', 'painting' => 'Painting',
                'masonry' => 'Masonry', 'pipefitting' => 'Pipefitting', 'furniture_making' => 'Furniture Making',
                'furniture_fixing' => 'Furniture Fixing', 'dress_making' => 'Dress Making', 'dress_fixing' => 'Dress Fixing',
                'barbering' => 'Barbering', 'hilot' => 'Hilot', 'health_care_service' => 'Health Care Service',
                'massage_training' => 'Massage Training', 'regular_vehicle' => 'Regular Vehicle', 'bus' => 'Bus',
                'truck' => 'Truck', 'motorcycle' => 'Motorcycle', 'heavy_equipment_operation' => 'Heavy Equipment Operation',
                'mechanical_drafting' => 'Mechanical Drafting', 'mining' => 'Mining',
                'food_beverage_services' => 'Food and Beverage Services',
            ],
            'desired_skills' => [
                'agricultural_crops' => 'Agricultural Crops', 'livestock' => 'Livestock', 'aquaculture' => 'Aquaculture',
                'fish_capture' => 'Fish Capture', 'carpentry' => 'Carpentry', 'painting' => 'Painting',
                'masonry' => 'Masonry', 'pipefitting' => 'Pipefitting', 'furniture_making' => 'Furniture Making',
                'furniture_fixing' => 'Furniture Fixing', 'dress_making' => 'Dress Making', 'dress_fixing' => 'Dress Fixing',
                'barbering' => 'Barbering', 'hilot' => 'Hilot', 'health_care_service' => 'Health Care Service',
                'massage_training' => 'Massage Training', 'regular_vehicle' => 'Regular Vehicle', 'bus' => 'Bus',
                'truck' => 'Truck', 'motorcycle' => 'Motorcycle', 'heavy_equipment_operation' => 'Heavy Equipment Operation',
                'machine_operation' => 'Machine Operation', 'mechanical_drafting' => 'Mechanical Drafting', 'mining' => 'Mining',
                'food_beverage_services' => 'Food and Beverage Services', 'food_processing' => 'Food Processing',
            ],
            'house_tenures' => [
                'owner_amortized' => 'Owner/being amortized', 'settler' => 'Settler', 'rented_shared' => 'Rented / Shared',
                'government_dwelling' => 'Government-issued dwelling', 'rent_free_consent' => 'Rent-free with consent of owner',
                'others' => 'Others',
            ],
            'house_materials' => [
                'concrete' => 'Concrete', 'semi_concrete' => 'Semi-concrete',
                'light_materials' => 'Light materials', 'typhoon_resistant' => 'Typhoon resistant',
            ],
            'residential_tenures' => [
                'fully_owned' => 'Fully Owned', 'tenanted' => 'Tenanted', 'lease_rented' => 'Lease/Rented',
                'ancestral_domain' => 'Ancestral domain claim', 'cadt' => 'CADT', 'rent_free' => 'Rent Free', 'others' => 'Other Forms of Tenure',
            ],
            'agricultural_tenures' => [
                'fully_owned' => 'Fully Owned', 'rent_free' => 'Rent Free', 'tenanted' => 'Tenanted',
                'ancestral_domain' => 'Ancestral domain claim', 'cadt' => 'CADT', 'lease_rented' => 'Lease/Rented',
                'clt_cloa' => 'Held under Certificate of Land Transfer (CLT) or Certificate of Land Ownership (CLOA)',
                'others' => 'Other Forms of Tenure',
            ],
            'prior_organizations' => [
                'cooperative' => 'Cooperative', 'workers_union' => "Worker's Union",
                'barangay_committee' => 'Barangay Committee/Task Force', 'peoples_association' => "People's Association",
                'ngo_po' => 'NGO/PO', 'others' => 'Others',
            ],
            'current_organizations' => [
                'cooperative_microfinance' => 'Cooperative/Microfinance', 'workers_union' => "Worker's Union",
                'barangay_committee' => 'Barangay Committee/Task Force', 'peoples_association' => "People's Association",
                'others' => 'Others',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function profilingInterviewRules(): array
    {
        $choices = $this->profilingInterviewChoices();
        $short = ['nullable', 'string', 'max:500'];
        $name = ['nullable', 'string', 'max:100'];
        $date = ['nullable', 'date'];
        $integer = ['nullable', 'integer', 'min:0', 'max:999'];
        $choice = fn (string $set): array => ['nullable', Rule::in(array_keys($choices[$set]))];
        $incomeCode = ['nullable', 'string', 'max:100', 'regex:/^\s*[1-8](?:\s*[,\/]\s*[1-8])*\s*$/'];
        $draftRules = [
            'as_of_date' => $date, 'interviewer_name' => $short, 'interviewer_office_designation' => $short,
            'interview_date' => $date, 'submission_date' => $date, 'conducting_entity' => $short,
            'encoder_name' => $short, 'encoder_office_designation' => $short, 'date_encoded' => $date,
            'respondent_last_name' => $name, 'respondent_first_name' => $name, 'respondent_middle_name' => $name,
            'partner_first_name' => $name, 'partner_middle_name' => $name, 'partner_last_name' => $name,
            'partner_sex' => $choice('sex'), 'partner_birthdate' => $date, 'partner_education' => $short,
            'partner_income_sources' => $incomeCode,
            'children' => ['nullable', 'array'], 'other_family_members' => ['nullable', 'array'],
            'total_children' => $integer, 'total_family_members' => $integer,
            'monthly_household_income_band' => $choice('income_bands'),
            'average_monthly_income' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'eyesight_problem' => $choice('yes_no'), 'eyesight_problem_details' => $short, 'eyesight_problem_count' => $integer,
            'hearing_problem' => $choice('yes_no'), 'hearing_problem_details' => $short, 'hearing_problem_count' => $integer,
            'speech_problem' => $choice('yes_no'), 'speech_problem_details' => $short, 'speech_problem_count' => $integer,
            'physical_disability' => $choice('yes_no'), 'physical_disability_details' => $short, 'physical_disability_count' => $integer,
            'other_health_concern' => $choice('yes_no'), 'other_health_concern_details' => $short, 'other_health_concern_count' => $integer,
            'social_protection_other_name' => $short, 'other_government_id_name' => $short,
            'government_ids' => ['nullable', 'array:prc_license,philid,drivers_license,postal_id,senior_citizen_id,passport,solo_parent_id,owwa,ncwdp,sss,gsis,philhealth,others'],
            'has_birth_certificate' => $choice('yes_no_unknown'), 'has_legal_cases' => $choice('yes_no_unknown_unsure'),
            'highest_education' => $choice('education'), 'elementary_level_year' => $short,
            'high_school_level_year' => $short, 'college_level_year' => $short,
            'prior_income_type' => $choice('income_types'), 'prior_wage_sources' => ['nullable', 'array'],
            'prior_wage_other' => $short, 'prior_self_employed_sources' => ['nullable', 'array'],
            'prior_self_employed_other' => $short, 'prior_income_other' => $short,
            'current_income_type' => $choice('income_types'), 'current_wage_sources' => ['nullable', 'array'],
            'current_wage_other' => $short, 'current_self_employed_sources' => ['nullable', 'array'],
            'current_self_employed_other' => $short, 'current_income_other' => $short,
            'current_skill_categories' => ['nullable', 'array'], 'current_skills' => ['nullable', 'array'],
            'current_agriculture_other' => $short, 'current_construction_other' => $short,
            'current_furniture_other' => $short, 'current_garments_other' => $short,
            'current_community_services_other' => $short, 'current_transport_other' => $short,
            'current_machine_operation_other' => $short, 'current_food_processing_other' => $short,
            'current_other_technical_skills' => $short,
            'desired_skill_categories' => ['nullable', 'array'], 'desired_skills' => ['nullable', 'array'],
            'desired_agriculture_other' => $short, 'desired_construction_other' => $short,
            'desired_furniture_other' => $short, 'desired_garments_other' => $short,
            'desired_community_services_other' => $short, 'desired_transport_other' => $short,
            'desired_engineering_other' => $short, 'desired_culinary_other' => $short,
            'desired_other_technical_skills' => $short,
            'house_tenures' => ['nullable', 'array'], 'house_rent_free_owner_details' => $short,
            'house_tenure_other' => $short, 'house_materials' => ['nullable', 'array'],
            'residential_lot_tenures' => ['nullable', 'array'], 'residential_lot_other' => $short,
            'agricultural_land_tenures' => ['nullable', 'array'], 'agricultural_land_other' => $short,
            'prior_organization_member' => $choice('yes_no'), 'prior_organization_types' => ['nullable', 'array'],
            'prior_cooperative_details' => $short, 'prior_workers_union_details' => $short,
            'prior_barangay_group_details' => $short, 'prior_ngo_details' => $short, 'prior_organization_other' => $short,
            'current_organization_member' => $choice('yes_no'), 'current_organization_types' => ['nullable', 'array'],
            'current_organization_other' => $short, 'plans_join_organization' => $choice('yes_no_unsure'),
            'planned_organization' => $short, 'no_join_reason' => $short, 'not_sure_join_reason' => $short,
            'registered_voter' => $choice('yes_no_unsure'), 'plans_to_vote' => $choice('yes_no_unsure'),
            'plans_to_run_for_office' => $choice('yes_no_unsure'),
        ];

        foreach (['sss', 'gsis', 'philhealth', 'pagibig', '4ps', 'other'] as $program) {
            $draftRules["social_protection_{$program}"] = $choice('yes_no_unknown');
            $draftRules["social_protection_{$program}_who"] = $short;
        }
        foreach (['prior_wage_sources', 'current_wage_sources'] as $field) {
            $draftRules["{$field}.*"] = $choice('wage_sources');
        }
        foreach (['prior_self_employed_sources', 'current_self_employed_sources'] as $field) {
            $draftRules["{$field}.*"] = $choice('self_employed_sources');
        }
        foreach ([
            'current_skill_categories' => 'skill_categories', 'desired_skill_categories' => 'desired_skill_categories',
            'current_skills' => 'current_skills', 'desired_skills' => 'desired_skills',
            'house_tenures' => 'house_tenures', 'house_materials' => 'house_materials',
            'residential_lot_tenures' => 'residential_tenures', 'agricultural_land_tenures' => 'agricultural_tenures',
            'prior_organization_types' => 'prior_organizations', 'current_organization_types' => 'current_organizations',
        ] as $field => $set) {
            $draftRules["{$field}.*"] = $choice($set);
        }
        foreach (['children', 'other_family_members'] as $collection) {
            $keys = $collection === 'children'
                ? 'first_name,middle_name,last_name,sex,civil_status,birthdate,in_school,education,income_sources'
                : 'first_name,middle_name,last_name,sex,relation,civil_status,birthdate,in_school,education,income_sources';
            $draftRules["{$collection}.*"] = ['array:'.$keys];
            foreach (['first_name', 'middle_name', 'last_name'] as $field) {
                $draftRules["{$collection}.*.{$field}"] = $name;
            }
            $draftRules["{$collection}.*.sex"] = $choice('sex');
            $draftRules["{$collection}.*.civil_status"] = $short;
            $draftRules["{$collection}.*.birthdate"] = $date;
            $draftRules["{$collection}.*.in_school"] = $choice('yes_no');
            $draftRules["{$collection}.*.education"] = $short;
            $draftRules["{$collection}.*.income_sources"] = $incomeCode;
        }
        $draftRules['other_family_members.*.relation'] = $choice('relation_codes');
        foreach (['prc_license', 'philid', 'drivers_license', 'postal_id', 'senior_citizen_id', 'passport', 'solo_parent_id', 'owwa', 'ncwdp', 'sss', 'gsis', 'philhealth', 'others'] as $id) {
            $draftRules["government_ids.{$id}"] = ['nullable', 'array:held,validity'];
            $draftRules["government_ids.{$id}.held"] = $choice('yes_no');
            $draftRules["government_ids.{$id}.validity"] = $choice('validity');
        }

        return [
            'revision' => ['required', 'integer', 'min:0'],
            'draft' => ['required', 'array:'.implode(',', array_keys(array_filter(
                $draftRules,
                fn (string $key): bool => ! str_contains($key, '.'),
                ARRAY_FILTER_USE_KEY,
            )))],
            ...collect($draftRules)->mapWithKeys(fn (array $rules, string $key): array => ["draft.{$key}" => $rules])->all(),
        ];
    }

    /** @return array<string, string> */
    private function profilingInterviewValidationMessages(): array
    {
        return [
            'draft.partner_income_sources.regex' => 'Partner Source/s of Income must contain codes 1 to 8 separated by commas or slashes.',
            'draft.children.*.income_sources.regex' => 'Each child Source/s of Income must contain codes 1 to 8 separated by commas or slashes.',
            'draft.other_family_members.*.income_sources.regex' => 'Each family member Source/s of Income must contain codes 1 to 8 separated by commas or slashes.',
            'draft.other_family_members.*.relation.in' => 'The Relationship Code must be a code from 1 to 7.',
        ];
    }

    /** @return array<string, mixed> */
    private function initialInterviewRules(): array
    {
        $yesNo = ['nullable', Rule::in(['yes', 'no'])];
        $shortText = ['nullable', 'string', 'max:500'];
        $longText = ['nullable', 'string', 'max:4000'];
        $date = ['nullable', 'date'];
        $frequency = ['nullable', Rule::in(['never', 'rarely', 'sometimes', 'often', 'always'])];
        $draftRules = [
            'as_of_date' => $date,
            'interviewer_name' => $shortText,
            'interviewer_office_designation' => $shortText,
            'interview_date' => $date,
            'submission_date' => $date,
            'conducting_entity' => $shortText,
            'encoder_name' => $shortText,
            'encoder_office_designation' => $shortText,
            'date_encoded' => $date,
            'last_name' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'alias' => $shortText,
            'sex' => ['nullable', Rule::in(['male', 'female'])],
            'birthdate' => $date,
            'birthplace' => ['nullable', 'string', 'max:1000'],
            'civil_status' => ['nullable', Rule::in(['single', 'married', 'widow_widower', 'separated', 'common_law_partner', 'others'])],
            'civil_status_other' => $shortText,
            'tribal_group' => $yesNo,
            'tribal_group_name' => $shortText,
            'religion' => $shortText,
            'movement_years' => ['nullable', 'integer', 'min:0', 'max:100'],
            'entry_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'reasons_joining' => $longText,
            'reasons_staying' => $longText,
            'position_before_leaving' => $shortText,
            'unit_before_leaving' => $shortText,
            'areas_of_operation' => $longText,
            'unfair_treatment' => $yesNo,
            'unfair_treatment_details' => $longText,
            'reasons_leaving' => $longText,
            'firearms_had' => $yesNo,
            'firearms_brought' => $yesNo,
            'firearms_turned_in' => $yesNo,
            'firearms_turned_in_to' => $shortText,
            'firearms_not_turned_in_reason' => $longText,
            'explosives_had' => $yesNo,
            'explosives_brought' => $yesNo,
            'explosives_turned_in' => $yesNo,
            'explosives_turned_in_to' => $shortText,
            'explosives_not_turned_in_reason' => $longText,
            'current_street' => $shortText,
            'current_sitio' => $shortText,
            'current_barangay' => $shortText,
            'current_municipality_city' => $shortText,
            'current_province' => $shortText,
            'current_psgc_barangay_code' => ['nullable', 'string', 'max:100'],
            'stay_years' => ['nullable', 'integer', 'min:0', 'max:100'],
            'stay_months' => ['nullable', 'integer', 'min:0', 'max:11'],
            'family_street' => $shortText,
            'family_sitio' => $shortText,
            'family_barangay' => $shortText,
            'family_municipality_city' => $shortText,
            'family_province' => $shortText,
            'family_contact_information' => $shortText,
            'emergency_contact_name' => $shortText,
            'emergency_contact_relationship' => $shortText,
            'emergency_contact_information' => $shortText,
            'emergency_contact_address' => ['nullable', 'string', 'max:1000'],
            'current_separation_reasons' => ['nullable', 'array'],
            'current_separation_other' => $shortText,
            'relocate_plans' => $yesNo,
            'relocate_same_municipality' => $yesNo,
            'relocate_where' => $shortText,
            'relocation_reasons' => ['nullable', 'array'],
            'relocation_other' => $shortText,
            'respondent_safety' => ['nullable', Rule::in(['high_threat', 'considerable_threat', 'threat_avoid_areas', 'little_threat', 'no_threat'])],
            'respondent_threat_sources' => ['nullable', 'array'],
            'respondent_threat_other' => $shortText,
            'family_safety' => ['nullable', Rule::in(['high_threat', 'considerable_threat', 'threat_avoid_areas', 'little_threat', 'no_threat'])],
            'family_threat_sources' => ['nullable', 'array'],
            'family_threat_other' => $shortText,
            'visual_impairment' => $yesNo,
            'visual_impairment_details' => $shortText,
            'hearing_impairment' => $yesNo,
            'hearing_impairment_details' => $shortText,
            'speech_impairment' => $yesNo,
            'speech_impairment_details' => $shortText,
            'physical_disabilities' => $yesNo,
            'physical_disabilities_details' => $shortText,
            'other_disabilities' => $yesNo,
            'other_disabilities_details' => $shortText,
            'medical_received' => $yesNo,
            'medical_times' => ['nullable', 'integer', 'min:0', 'max:999'],
            'medical_conditions' => $longText,
            'board_lodging_received' => $yesNo,
            'board_lodging_sources' => ['nullable', 'array'],
            'board_lodging_other' => $shortText,
            'food_received' => $yesNo,
            'food_sources' => ['nullable', 'array'],
            'food_other' => $shortText,
            'transport_received' => $yesNo,
            'transport_sources' => ['nullable', 'array'],
            'transport_other' => $shortText,
            'psychosocial_received' => $yesNo,
            'psychosocial_sources' => ['nullable', 'array'],
            'psychosocial_other' => $shortText,
            'assistance_other' => $longText,
            'difficulty_sleeping' => $frequency,
            'anxiety' => $frequency,
            'addictive_substances' => $frequency,
            'difficulty_concentrating' => $frequency,
            'disengaged_environment' => $frequency,
            'panic_attacks' => $frequency,
            'avoidance_people_places' => $frequency,
            'avoidance_who' => $shortText,
            'trusting_others' => $frequency,
            'trusting_who' => $shortText,
            'remembering_violent_incidents' => $frequency,
            'violent_thoughts' => $frequency,
            'irritability_anger' => $frequency,
            'feelings_guilt' => $frequency,
        ];
        $rules = [
            'revision' => ['required', 'integer', 'min:0'],
            'draft' => ['required', 'array:'.implode(',', array_keys($draftRules))],
        ];
        foreach ($draftRules as $field => $rule) {
            $rules['draft.'.$field] = $rule;
        }
        $rules['draft.current_separation_reasons.*'] = [Rule::in(['unsafe_living_there', 'endanger_family', 'lack_basic_needs', 'no_transport', 'family_conflict', 'others'])];
        $rules['draft.relocation_reasons.*'] = [Rule::in(['lack_security', 'no_livelihood', 'hazardous_location', 'poor_living_conditions', 'others'])];
        $threatSources = [Rule::in(['former_comrades', 'mass_base_members', 'private_armed_groups', 'criminal_groups', 'neighbors', 'adjacent_communities', 'others'])];
        $rules['draft.respondent_threat_sources.*'] = $threatSources;
        $rules['draft.family_threat_sources.*'] = $threatSources;
        $assistanceSources = [Rule::in(['lgu', 'afp_pnp', 'others'])];
        foreach (['board_lodging_sources', 'food_sources', 'transport_sources', 'psychosocial_sources'] as $field) {
            $rules['draft.'.$field.'.*'] = $assistanceSources;
        }

        return $rules;
    }
}
