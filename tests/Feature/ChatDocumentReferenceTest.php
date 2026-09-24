<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FeaUploadSlot;
use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\ChatMessageDocumentReference;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\PswdoEnrollment;
use App\Models\User;
use App\Services\ChatConversationService;
use App\Services\ChatDocumentReferenceService;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChatDocumentReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_approved_document_type_can_be_referenced_to_its_responsible_receiver(): void
    {
        $context = $this->context();
        $ib39Conversation = app(ChatConversationService::class)->start($context['japic'], $context['ib39']->id);
        $japicConversation = app(ChatConversationService::class)->start($context['ib39'], $context['japic']->id);
        $pswdoConversation = app(ChatConversationService::class)->start($context['ib39'], $context['pswdo']->id);

        $responses = [
            $this->send($context['japic'], $ib39Conversation, ChatDocumentReferenceService::TYPE_CDR, $context['cdr']),
            $this->send($context['ib39'], $japicConversation, ChatDocumentReferenceService::TYPE_JAPIC, $context['japic_document']),
            $this->send($context['ib39'], $pswdoConversation, ChatDocumentReferenceService::TYPE_PSWDO, $context['pswdo_document']),
            $this->send($context['japic'], $ib39Conversation, ChatDocumentReferenceService::TYPE_FEA, $context['fea_version']),
        ];
        $expectedLabels = [
            'FR001 CDR',
            'FR001 JAPIC Certification',
            'FR001 E-CLIP Enrollment Form',
            'FR001 Technical Inspection Report',
        ];

        foreach ($responses as $index => $response) {
            $response->assertCreated()
                ->assertJsonPath('message.document_reference.available', true)
                ->assertJsonPath('message.document_reference.label', $expectedLabels[$index]);
            foreach (['storage_path', 'sha256', 'original_filename', 'private/'] as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent());
            }
        }

        $this->assertDatabaseCount('chat_message_document_references', 4);
        foreach ([
            'ib39_cdr_document_version_id',
            'japic_certification_document_version_id',
            'pswdo_enrollment_document_id',
            'ib39_fea_document_version_id',
        ] as $column) {
            $this->assertSame(1, ChatMessageDocumentReference::query()->whereNotNull($column)->count());
        }
        $responses[0]->assertJsonPath('message.document_reference.preview_url', route(
            'japic.cdr.documents.preview',
            [$context['cdr']->processing, $context['cdr']],
        ));
    }

    public function test_candidate_and_message_labels_use_the_stored_reference_and_existing_type_labels(): void
    {
        $context = $this->context();
        $this->assertSame('FR001', $context['record']->reference_number);

        $ib39Conversation = app(ChatConversationService::class)->start($context['japic'], $context['ib39']->id);
        $japicConversation = app(ChatConversationService::class)->start($context['ib39'], $context['japic']->id);
        $pswdoConversation = app(ChatConversationService::class)->start($context['ib39'], $context['pswdo']->id);
        $references = app(ChatDocumentReferenceService::class);

        $cases = [
            [$context['japic'], $ib39Conversation, ChatDocumentReferenceService::TYPE_CDR, $context['cdr'], 'FR001 CDR'],
            [$context['ib39'], $japicConversation, ChatDocumentReferenceService::TYPE_JAPIC, $context['japic_document'], 'FR001 JAPIC Certification'],
        ];

        $pswdoLabels = [
            PswdoEnrollmentDocumentType::EclipEnrollmentForm->value => 'FR001 E-CLIP Enrollment Form',
            PswdoEnrollmentDocumentType::InitialInterviewForm->value => 'FR001 Initial Interview Form',
            PswdoEnrollmentDocumentType::ProfilingInterviewForm->value => 'FR001 Profiling Interview Form',
            PswdoEnrollmentDocumentType::EndorsementLetter->value => 'FR001 Endorsement Letter',
        ];
        foreach ($context['pswdo_documents'] as $document) {
            $cases[] = [
                $context['ib39'],
                $pswdoConversation,
                ChatDocumentReferenceService::TYPE_PSWDO,
                $document,
                $pswdoLabels[$document->document_type->value],
            ];
        }

        $feaLabels = [
            Ib39FeaDocumentType::Tir->value => 'FR001 Technical Inspection Report',
            Ib39FeaDocumentType::Cvif->value => 'FR001 Cost Valuation of Inventoried Firearms',
            Ib39FeaDocumentType::Ptis->value => 'FR001 Property Turn-In Slip',
            Ib39FeaDocumentType::Justification->value => 'FR001 Justification on the TIR and CVC/CVIF',
            Ib39FeaDocumentType::FirearmPhoto->value => 'FR001 Photograph of the firearm',
            Ib39FeaDocumentType::FrWithFirearmPhoto->value => 'FR001 Photograph of the FR with the firearm',
        ];
        foreach ($context['fea_versions'] as $version) {
            $cases[] = [
                $context['japic'],
                $ib39Conversation,
                ChatDocumentReferenceService::TYPE_FEA,
                $version,
                $feaLabels[$version->document->document_type->value],
            ];
        }

        $candidatesByConversation = [];
        foreach ($cases as [$sender, $conversation, $type, $document, $expectedLabel]) {
            $candidateKey = $sender->getKey().':'.$conversation->getKey();
            $candidates = $candidatesByConversation[$candidateKey] ??= $references
                ->availableFor($conversation, $sender)
                ->keyBy('value');
            $selection = $type.':'.$document->getKey();
            $this->assertSame($expectedLabel, $candidates->get($selection)['label']);

            $response = $this->send($sender, $conversation, $type, $document)
                ->assertCreated()
                ->assertJsonPath('message.document_reference.label', $expectedLabel);
            $this->assertSame($candidates->get($selection)['label'], $response->json('message.document_reference.label'));
            foreach (['Version ', 'Reference ', 'DocumentVersion', 'document_versions', 'storage_path', 'sha256', $document->original_filename] as $internal) {
                $this->assertStringNotContainsString($internal, $response->json('message.document_reference.label'));
            }
        }
    }

    public function test_invalid_responsibility_historical_japic_and_unsupported_references_are_rejected(): void
    {
        $context = $this->context();
        $wrongReceiver = User::factory()->role('39th_ib')->create();
        $conversation = app(ChatConversationService::class)->start($context['japic'], $wrongReceiver->id);
        $this->send($context['japic'], $conversation, ChatDocumentReferenceService::TYPE_CDR, $context['cdr'])
            ->assertUnprocessable()->assertJsonValidationErrors('document_reference');

        $historical = JapicCertificationDocumentVersion::query()->forceCreate([
            'processing_id' => $context['japic_processing']->id, 'version_number' => 2,
            'storage_path' => 'japic/historical.pdf', 'original_filename' => 'historical.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('9', 64),
            'uploaded_by' => $context['japic']->id, 'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $responsible = app(ChatConversationService::class)->start($context['ib39'], $context['japic']->id);
        $this->send($context['ib39'], $responsible, ChatDocumentReferenceService::TYPE_JAPIC, $historical)
            ->assertUnprocessable()->assertJsonValidationErrors('document_reference');

        foreach (['assistance:1', 'https://example.test/private.pdf'] as $reference) {
            $this->actingAs($context['ib39'])->postJson(route('chat.messages.store', $responsible), [
                'body' => 'Unsupported', 'document_reference' => $reference,
            ])->assertUnprocessable()->assertJsonValidationErrors('document_reference');
        }
        $this->actingAs($context['ib39'])->postJson(route('chat.messages.store', $responsible), [
            'body' => 'Substituted parent',
            'document_reference' => ChatDocumentReferenceService::TYPE_JAPIC.':'.$context['japic_document']->id,
            'former_rebel_id' => 999999,
            'preview_url' => 'https://example.test/forged',
        ])->assertUnprocessable()->assertJsonValidationErrors(['former_rebel_id', 'preview_url']);

        $unauthorizedSender = User::factory()->role('admin')->create();
        $unauthorizedConversation = app(ChatConversationService::class)->start($unauthorizedSender, $context['ib39']->id);
        $this->send($unauthorizedSender, $unauthorizedConversation, ChatDocumentReferenceService::TYPE_CDR, $context['cdr'])
            ->assertUnprocessable()->assertJsonValidationErrors('document_reference');
        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('chat_message_document_references', 0);
    }

    public function test_reference_is_unavailable_after_existing_preview_authorization_is_lost(): void
    {
        $context = $this->context();
        $conversation = app(ChatConversationService::class)->start($context['ib39'], $context['japic']->id);
        $response = $this->send($context['ib39'], $conversation, ChatDocumentReferenceService::TYPE_JAPIC, $context['japic_document'])
            ->assertCreated();
        $previewUrl = $response->json('message.document_reference.preview_url');

        $replacement = User::factory()->role('japic')->create();
        $context['japic_processing']->forceFill(['assigned_to' => $replacement->id])->save();

        $this->actingAs($context['japic'])->get(route('chat.conversations.show', $conversation))
            ->assertOk()->assertSee('Referenced document unavailable')->assertDontSee($previewUrl, false);
        $this->actingAs($context['japic'])->get($previewUrl)->assertForbidden();
    }

    private function send(User $sender, $conversation, string $type, $document)
    {
        return $this->actingAs($sender)->postJson(route('chat.messages.store', $conversation), [
            'body' => 'Please review this document.',
            'document_reference' => $type.':'.$document->getKey(),
        ]);
    }

    private function context(): array
    {
        $ib39 = User::factory()->role('39th_ib')->create();
        $japic = User::factory()->role('japic')->create();
        $pswdo = User::factory()->role('pswdo')->create();
        $municipality = DB::table('municipalities')->insertGetId([
            'name' => 'Chat Reference '.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Chat', 'last_name' => 'Reference',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => true,
        ], $ib39)->load('cdrProcessing', 'feaProcessing.documents');

        $cdr = Ib39CdrDocumentVersion::query()->forceCreate([
            'cdr_processing_id' => $record->cdrProcessing->id, 'version_number' => 1, 'source_type' => 'uploaded',
            'storage_path' => 'ib39/cdr/chat-reference.pdf', 'original_filename' => 'cdr.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('c', 64),
            'created_by' => $ib39->id, 'finalized_at' => now(),
        ]);
        $record->cdrProcessing->update([
            'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $ib39->id,
            'current_final_version_id' => $cdr->id,
        ]);

        $japicProcessing = JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $record->id,
            'triggering_cdr_document_version_id' => $cdr->id,
            'status' => JapicCertificationStatus::Pending, 'received_at' => now(), 'due_at' => now()->addDays(14),
            'assigned_to' => $japic->id, 'lock_version' => 0,
        ]);
        $japicDocument = JapicCertificationDocumentVersion::query()->forceCreate([
            'processing_id' => $japicProcessing->id, 'version_number' => 1,
            'storage_path' => 'japic/chat-reference.pdf', 'original_filename' => 'japic.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => str_repeat('j', 64),
            'uploaded_by' => $japic->id, 'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $japicProcessing->forceFill([
            'status' => JapicCertificationStatus::Completed, 'completed_at' => now(), 'completed_by' => $japic->id,
            'current_final_version_id' => $japicDocument->id,
        ])->save();

        $enrollment = PswdoEnrollment::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id]);
        $pswdoDocuments = collect(PswdoEnrollmentDocumentType::cases())->map(fn (PswdoEnrollmentDocumentType $type) => $enrollment->documents()->create([
            'document_type' => $type,
            'storage_path' => 'pswdo/chat-reference-'.$type->value.'.pdf', 'original_filename' => $type->value.'.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => hash('sha256', 'pswdo-'.$type->value),
            'uploaded_by' => $pswdo->id, 'correct_document_type_confirmed' => true,
            'belongs_to_fr_confirmed' => true, 'final_signed_confirmed' => true, 'uploaded_at' => now(),
        ]));
        $pswdoDocument = $pswdoDocuments->first();

        $feaVersions = $record->feaProcessing->documents->map(fn ($document) => Ib39FeaDocumentVersion::query()->forceCreate([
            'fea_processing_id' => $record->feaProcessing->id, 'fea_document_id' => $document->id,
            'slot' => Ib39FeaUploadSlot::Primary, 'version_number' => 1,
            'storage_path' => 'ib39/fea/chat-reference-'.$document->document_type->value.'.pdf',
            'original_filename' => $document->document_type->value.'.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10,
            'sha256' => hash('sha256', 'fea-'.$document->document_type->value),
            'uploaded_by' => $ib39->id,
        ])->load('document'));
        $feaVersion = $feaVersions->first(
            fn (Ib39FeaDocumentVersion $version): bool => $version->document->document_type === Ib39FeaDocumentType::Tir,
        );

        return compact('ib39', 'japic', 'pswdo', 'record', 'cdr') + [
            'japic_processing' => $japicProcessing,
            'japic_document' => $japicDocument,
            'pswdo_document' => $pswdoDocument,
            'fea_version' => $feaVersion,
            'pswdo_documents' => $pswdoDocuments,
            'fea_versions' => $feaVersions,
        ];
    }
}
