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
