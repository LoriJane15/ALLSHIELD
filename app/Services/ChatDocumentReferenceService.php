<?php

namespace App\Services;

use App\Enums\JapicCertificationStatus;
use App\Models\ChatConversation;
use App\Models\ChatMessageDocumentReference;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\PswdoEnrollmentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChatDocumentReferenceService
{
    public const TYPE_CDR = 'cdr_document_version';

    public const TYPE_JAPIC = 'japic_certification_document_version';

    public const TYPE_PSWDO = 'pswdo_enrollment_document';

    public const TYPE_FEA = 'fea_document_version';

    public const TYPES = [
        self::TYPE_CDR,
        self::TYPE_JAPIC,
        self::TYPE_PSWDO,
        self::TYPE_FEA,
    ];

    public const EAGER_LOADS = [
        'documentReference.cdrDocumentVersion.processing.surfacedFormerRebel.pswdoEnrollment',
        'documentReference.cdrDocumentVersion.processing.surfacedFormerRebel.japicCertificationProcessing',
        'documentReference.japicDocumentVersion.processing.surfacedFormerRebel.pswdoEnrollment',
        'documentReference.pswdoEnrollmentDocument.enrollment.surfacedFormerRebel.japicCertificationProcessing',
        'documentReference.feaDocumentVersion.document',
        'documentReference.feaDocumentVersion.processing.surfacedFormerRebel.pswdoEnrollment',
        'documentReference.feaDocumentVersion.processing.surfacedFormerRebel.japicCertificationProcessing',
    ];

    public function availableFor(ChatConversation $conversation, User $actor): Collection
    {
        $receiver = $this->receiver($conversation, $actor);
        if (! $receiver?->is_active) {
            return collect();
        }

        $targets = collect()
            ->merge(Ib39CdrDocumentVersion::query()
                ->with(['processing.surfacedFormerRebel.pswdoEnrollment', 'processing.surfacedFormerRebel.japicCertificationProcessing'])
                ->where(function ($query) use ($receiver): void {
                    $query->where('created_by', $receiver->getKey())
                        ->orWhereHas('processing', fn ($processing) => $processing->where('completed_by', $receiver->getKey()));
                })->get())
            ->merge(JapicCertificationDocumentVersion::query()
                ->with(['processing.surfacedFormerRebel.pswdoEnrollment'])
                ->where(function ($query) use ($receiver): void {
                    $query->where('uploaded_by', $receiver->getKey())
                        ->orWhereHas('processing', fn ($processing) => $processing
                            ->where('assigned_to', $receiver->getKey())
                            ->orWhere('completed_by', $receiver->getKey()));
                })->get())
            ->merge(PswdoEnrollmentDocument::query()
                ->with(['enrollment.surfacedFormerRebel.japicCertificationProcessing'])
                ->where('uploaded_by', $receiver->getKey())->get())
            ->merge(Ib39FeaDocumentVersion::query()
                ->with([
                    'document',
                    'processing.surfacedFormerRebel.pswdoEnrollment',
                    'processing.surfacedFormerRebel.japicCertificationProcessing',
                ])
                ->where('uploaded_by', $receiver->getKey())->get());

        return $targets
            ->filter(fn (Model $target): bool => $this->isCanonical($target)
                && $this->responsible($target, $receiver)
                && $this->canPreview($actor, $target)
                && $this->canPreview($receiver, $target))
            ->map(fn (Model $target): array => [
                'value' => $this->typeFor($target).':'.$target->getKey(),
                'label' => $this->label($target),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function resolveForSend(
        ChatConversation $conversation,
        User $actor,
        ?string $selection,
        Collection $participants,
    ): ?array {
        if ($selection === null) {
            return null;
        }

        [$type, $id] = array_pad(explode(':', $selection, 2), 2, null);
        if (! in_array($type, self::TYPES, true) || ! ctype_digit((string) $id) || (int) $id < 1) {
            $this->unavailable();
        }

        $receiverId = $conversation->user_one_id === $actor->getKey()
            ? $conversation->user_two_id
            : $conversation->user_one_id;
        $receiver = $participants->get($receiverId);
        if (! $receiver instanceof User || ! $receiver->is_active) {
            $this->unavailable();
        }

        $target = $this->find($type, (int) $id);
        if (! $target
            || ! $this->isCanonical($target)
            || ! $this->responsible($target, $receiver)
            || ! $this->canPreview($actor, $target)
            || ! $this->canPreview($receiver, $target)) {
            $this->unavailable();
        }

        return [$this->columnFor($type) => $target->getKey()];
    }

    public function present(?ChatMessageDocumentReference $reference, User $viewer): ?array
    {
        if (! $reference) {
            return null;
        }

        $target = $this->target($reference);
        if (! $target || ! $this->isCanonical($target) || ! $this->canPreview($viewer, $target)) {
            return [
                'available' => false,
                'label' => 'Referenced document unavailable',
                'preview_url' => null,
            ];
        }

        $url = $this->previewUrl($target, $viewer);
        if ($url === null) {
            return [
                'available' => false,
                'label' => 'Referenced document unavailable',
                'preview_url' => null,
            ];
        }

        return [
            'available' => true,
            'label' => $this->label($target),
            'preview_url' => $url,
        ];
    }

    private function find(string $type, int $id): ?Model
    {
        return match ($type) {
            self::TYPE_CDR => Ib39CdrDocumentVersion::query()->with([
                'processing.surfacedFormerRebel.pswdoEnrollment',
                'processing.surfacedFormerRebel.japicCertificationProcessing',
            ])->find($id),
            self::TYPE_JAPIC => JapicCertificationDocumentVersion::query()
                ->with('processing.surfacedFormerRebel.pswdoEnrollment')->find($id),
            self::TYPE_PSWDO => PswdoEnrollmentDocument::query()
                ->with('enrollment.surfacedFormerRebel.japicCertificationProcessing')->find($id),
            self::TYPE_FEA => Ib39FeaDocumentVersion::query()->with([
                'document',
                'processing.surfacedFormerRebel.pswdoEnrollment',
                'processing.surfacedFormerRebel.japicCertificationProcessing',
            ])->find($id),
        };
    }

    private function receiver(ChatConversation $conversation, User $actor): ?User
    {
        $conversation->loadMissing(['userOne', 'userTwo']);

        return $conversation->user_one_id === $actor->getKey()
            ? $conversation->userTwo
            : ($conversation->user_two_id === $actor->getKey() ? $conversation->userOne : null);
    }

    private function responsible(Model $target, User $receiver): bool
    {
        $id = $receiver->getKey();

        return match (true) {
            $target instanceof Ib39CdrDocumentVersion => $target->created_by === $id
                || $target->processing?->completed_by === $id,
            $target instanceof JapicCertificationDocumentVersion => $target->uploaded_by === $id
                || $target->processing?->assigned_to === $id
                || $target->processing?->completed_by === $id,
            $target instanceof PswdoEnrollmentDocument => $target->uploaded_by === $id,
            $target instanceof Ib39FeaDocumentVersion => $target->uploaded_by === $id,
            default => false,
        };
    }

    private function isCanonical(Model $target): bool
    {
        return match (true) {
            $target instanceof Ib39CdrDocumentVersion => $target->processing !== null,
            $target instanceof JapicCertificationDocumentVersion => $target->processing !== null
                && $target->processing->status === JapicCertificationStatus::Completed
                && $target->processing->current_final_version_id === $target->getKey(),
            $target instanceof PswdoEnrollmentDocument => $target->enrollment !== null,
            $target instanceof Ib39FeaDocumentVersion => $target->processing !== null
                && $target->document !== null
                && $target->fea_processing_id === $target->document->fea_processing_id,
            default => false,
        };
    }

    private function canPreview(User $user, Model $target): bool
    {
        return $user->is_active && Gate::forUser($user)->allows('preview', $target);
    }

    private function target(ChatMessageDocumentReference $reference): ?Model
    {
        return match (true) {
            $reference->ib39_cdr_document_version_id !== null => $reference->cdrDocumentVersion,
            $reference->japic_certification_document_version_id !== null => $reference->japicDocumentVersion,
            $reference->pswdo_enrollment_document_id !== null => $reference->pswdoEnrollmentDocument,
            $reference->ib39_fea_document_version_id !== null => $reference->feaDocumentVersion,
            default => null,
        };
    }

    private function typeFor(Model $target): string
    {
        return match (true) {
            $target instanceof Ib39CdrDocumentVersion => self::TYPE_CDR,
            $target instanceof JapicCertificationDocumentVersion => self::TYPE_JAPIC,
            $target instanceof PswdoEnrollmentDocument => self::TYPE_PSWDO,
            $target instanceof Ib39FeaDocumentVersion => self::TYPE_FEA,
        };
    }

    private function columnFor(string $type): string
    {
        return match ($type) {
            self::TYPE_CDR => 'ib39_cdr_document_version_id',
            self::TYPE_JAPIC => 'japic_certification_document_version_id',
            self::TYPE_PSWDO => 'pswdo_enrollment_document_id',
            self::TYPE_FEA => 'ib39_fea_document_version_id',
        };
    }

    private function label(Model $target): string
    {
        return match (true) {
            $target instanceof Ib39CdrDocumentVersion => $target->processing->surfacedFormerRebel->reference_number.' CDR',
            $target instanceof JapicCertificationDocumentVersion => $target->processing->surfacedFormerRebel->reference_number.' JAPIC Certification',
            $target instanceof PswdoEnrollmentDocument => $target->enrollment->surfacedFormerRebel->reference_number.' '.$target->document_type->label(),
            $target instanceof Ib39FeaDocumentVersion => $target->processing->surfacedFormerRebel->reference_number.' '.$target->document->document_type->label(),
        };
    }

    private function previewUrl(Model $target, User $viewer): ?string
    {
        $role = $viewer->role;

        return match (true) {
            $target instanceof Ib39CdrDocumentVersion => match ($role) {
                '39th_ib' => route('ib39.cdr.documents.preview', [$target->processing, $target]),
                'japic' => route('japic.cdr.documents.preview', [$target->processing, $target]),
                'pswdo' => route('pswdo.cdr.documents.preview', [$target->processing, $target]),
                default => null,
            },
            $target instanceof JapicCertificationDocumentVersion => match ($role) {
                '39th_ib' => route('ib39.japic-certifications.document-versions.preview', [$target->processing, $target]),
                'japic' => route('japic.certifications.document-versions.preview', [$target->processing, $target]),
                'pswdo' => route('pswdo.japic.document-versions.preview', [$target->processing, $target]),
                default => null,
            },
            $target instanceof PswdoEnrollmentDocument => match ($role) {
                '39th_ib' => route('ib39.pswdo-enrollment-documents.preview', [$target->enrollment, $target]),
                'japic' => route('japic.pswdo-enrollment-documents.preview', [$target->enrollment, $target]),
                'pswdo' => route('pswdo.enrollments.documents.preview', [$target->enrollment, $target]),
                default => null,
            },
            $target instanceof Ib39FeaDocumentVersion => match ($role) {
                '39th_ib' => route('ib39.fea.documents.versions.preview', [$target->processing, $target->document, $target]),
                'japic' => route('japic.fea.documents.versions.preview', [$target->processing, $target->document, $target]),
                'pswdo' => route('pswdo.fea.documents.versions.preview', [$target->processing, $target->document, $target]),
                default => null,
            },
            default => null,
        };
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages([
            'document_reference' => 'The selected document reference is unavailable.',
        ]);
    }
}
