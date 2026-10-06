<?php

namespace App\Policies;

use App\Contracts\Ib39FeaReadiness;
use App\Enums\Ib39FeaDocumentStatus;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaProcessing;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class Ib39FeaDocumentPolicy
{
    public function __construct(private readonly Ib39FeaReadiness $readiness) {}

    public function start(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $this->hasAccess($user, $document, $processing)
            && $document->status !== Ib39FeaDocumentStatus::Completed;
    }

    public function updatePreliminary(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $this->start($user, $document, $processing);
    }

    public function viewHistory(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $this->canView($user, $document, $processing);
    }

    public function editDraft(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $document->document_type->hasDraftEditor()
            && $this->start($user, $document, $processing);
    }

    public function viewDraft(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $document->document_type->hasDraftEditor()
            && $this->canView($user, $document, $processing)
            && ($user->hasRole('39th_ib') || $document->status === Ib39FeaDocumentStatus::Completed);
    }

    public function downloadGeneratedDraft(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $user->hasRole('39th_ib') && $this->viewDraft($user, $document, $processing);
    }

    public function uploadDraft(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $this->start($user, $document, $processing)
            && $this->hasFirearms($processing);
    }

    public function uploadFinal(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $this->hasAccess($user, $document, $processing)
            && $this->hasFinalVersionColumn()
            && $document->status !== Ib39FeaDocumentStatus::Completed
            && $document->current_final_version_id === null
            && $this->hasFirearms($processing);
    }

    public function viewUploads(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        return $this->canView($user, $document, $processing);
    }

    private function hasAccess(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        if (! $user->is_active
            || ! $user->hasRole('39th_ib')
            || $document->fea_processing_id !== $processing->id) {
            return false;
        }

        if ($this->isWorkspaceView()
            && $processing->relationLoaded('surfacedFormerRebel')
            && $processing->surfacedFormerRebel?->relationLoaded('cancellation')) {
            $record = $processing->surfacedFormerRebel?->cancellation === null
                ? $processing->surfacedFormerRebel
                : null;
        } else {
            $record = $processing->surfacedFormerRebel()->whereDoesntHave('cancellation')->first();
        }

        return $record instanceof Ib39SurfacedFormerRebel
            && $this->readiness->isReady($record);
    }

    private function canView(User $user, Ib39FeaDocument $document, Ib39FeaProcessing $processing): bool
    {
        if (! $user->is_active || $document->fea_processing_id !== $processing->id) {
            return false;
        }

        if ($user->hasRole('39th_ib')) {
            if ($this->isWorkspaceView() && $processing->relationLoaded('surfacedFormerRebel')) {
                return $processing->surfacedFormerRebel !== null;
            }

            return $processing->surfacedFormerRebel()->exists();
        }

        if ($user->hasRole('pswdo')) {
            $processing->loadMissing('surfacedFormerRebel.pswdoEnrollment');
            $record = $processing->surfacedFormerRebel;

            return $record?->pswdoEnrollment !== null
                && $user->can('view', $record->pswdoEnrollment);
        }

        if (! $user->hasRole('japic')) {
            return false;
        }

        $processing->loadMissing('surfacedFormerRebel.japicCertificationProcessing');
        $certification = $processing->surfacedFormerRebel?->japicCertificationProcessing;

        return $certification instanceof JapicCertificationProcessing
            && $user->can('view', $certification);
    }

    private function hasFirearms(Ib39FeaProcessing $processing): bool
    {
        if ($this->isWorkspaceView() && $processing->relationLoaded('surfacedFormerRebel')) {
            return (bool) $processing->surfacedFormerRebel?->possessed_firearms;
        }

        return (bool) $processing->surfacedFormerRebel()->value('possessed_firearms');
    }

    private function hasFinalVersionColumn(): bool
    {
        if (! $this->isWorkspaceView()) {
            return Schema::hasColumn('ib39_fea_documents', 'current_final_version_id');
        }

        $request = request();
        if (! $request->attributes->has('fea_workspace_has_final_version_column')) {
            $request->attributes->set('fea_workspace_has_final_version_column', Schema::hasColumn('ib39_fea_documents', 'current_final_version_id'));
        }

        return $request->attributes->get('fea_workspace_has_final_version_column');
    }

    private function isWorkspaceView(): bool
    {
        return request()->isMethod('GET') && request()->routeIs('ib39.fea.show');
    }
}
