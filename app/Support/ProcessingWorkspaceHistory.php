<?php

namespace App\Support;

use App\Models\Ib39CdrProcessing;
use App\Models\Ib39FeaProcessing;
use App\Models\JapicCertificationProcessing;
use App\Models\PswdoEnrollment;
use Illuminate\Support\Collection;

class ProcessingWorkspaceHistory
{
    public static function cdr(Ib39CdrProcessing $processing): Collection
    {
        return self::ordered($processing->statusHistories->map(fn ($history): array => [
            'title' => match ($history->event) {
                'surfaced_fr_created' => 'Draft Created',
                'processing_started', 'draft_processing_started' => 'Drafting Started',
                'draft_saved' => 'Saved as Draft',
                'draft_updated' => 'Draft Updated',
                'direct_final_uploaded', 'final_document_uploaded' => 'Final CDR Uploaded',
                'system_authored_finalized' => 'Completed',
                default => str($history->event)->replace('_', ' ')->title()->toString(),
            },
            'actor' => $history->user?->name ?? 'System',
            'role' => $history->user?->role,
            'at' => $history->created_at,
            'context' => in_array($history->event, ['direct_final_uploaded', 'final_document_uploaded'], true)
                ? $processing->finalDocument?->original_filename
                : null,
            'id' => $history->id,
        ]));
    }

    public static function japic(JapicCertificationProcessing $processing): Collection
    {
        return self::ordered($processing->histories->map(fn ($history): array => [
            'title' => match ($history->event->value) {
                'started' => 'Draft Created',
                'draft_saved' => 'Draft Updated',
                'marked_for_signing' => 'Submitted for Signing',
                'final_uploaded' => 'Final File Uploaded',
                default => str($history->event->value)->replace('_', ' ')->title()->toString(),
            },
            'actor' => $history->actor?->name ?? 'System',
            'role' => $history->actor?->role,
            'at' => $history->occurred_at,
            'context' => match ($history->event->value) {
                'final_uploaded' => collect([
                    $history->documentVersion?->original_filename,
                    $history->documentVersion ? 'Version '.$history->documentVersion->version_number : null,
                ])->filter()->join(' · ') ?: null,
                default => isset($history->metadata['revision'])
                    ? 'Revision '.$history->metadata['revision']
                    : (isset($history->metadata['draft_revision']) ? 'Revision '.$history->metadata['draft_revision'] : null),
            },
            'id' => $history->id,
        ]));
    }

    public static function fea(Ib39FeaProcessing $processing): Collection
    {
        $events = $processing->histories->map(fn ($history): array => [
            'title' => str($history->event)->replace('_', ' ')->title()->toString(),
            'actor' => $history->actor?->name ?? 'System',
            'role' => $history->actor?->role,
            'at' => $history->created_at,
            'context' => null,
            'id' => $history->id,
        ]);

        foreach ($processing->documents as $document) {
            foreach ($document->draftHistories as $history) {
                $events->push([
                    'title' => $history->revision === 1 ? 'Draft Created' : 'Draft Updated',
                    'actor' => $history->actor?->name ?? 'User unavailable',
                    'role' => $history->actor?->role,
                    'at' => $history->created_at,
                    'context' => $document->document_type->label().' · Revision '.$history->revision,
                    'id' => $history->id,
                ]);
            }
            foreach ($document->histories as $history) {
                $events->push([
                    'title' => $history->event->label(),
                    'actor' => $history->actor?->name ?? 'System',
                    'role' => $history->actor?->role,
                    'at' => $history->created_at,
                    'context' => $document->document_type->label(),
                    'id' => $history->id,
                ]);
            }
            foreach ($document->uploadHistories as $history) {
                $events->push([
                    'title' => match ($history->event) {
                        'draft_uploaded' => 'Draft File Uploaded',
                        'draft_replaced' => 'Draft File Replaced',
                        'final_uploaded' => 'Final File Uploaded',
                        default => str($history->event)->replace('_', ' ')->title()->toString(),
                    },
                    'actor' => $history->actor?->name ?? 'System',
                    'role' => $history->actor?->role,
                    'at' => $history->created_at,
                    'context' => $document->document_type->label().' · Version '.$history->version_number,
                    'id' => $history->id,
                ]);
            }
        }

        return self::ordered($events);
    }

    public static function pswdo(PswdoEnrollment $enrollment): Collection
    {
        $events = collect([[
            'title' => 'Enrollment Created',
            'actor' => 'System',
            'role' => null,
            'at' => $enrollment->created_at,
            'context' => null,
            'id' => 0,
        ]]);

        foreach ($enrollment->documents->filter->isConfirmedFinal() as $document) {
            $events->push([
                'title' => 'Final File Uploaded',
                'actor' => $document->uploader?->name ?? 'User unavailable',
                'role' => $document->uploader?->role,
                'at' => $document->uploaded_at,
                'context' => $document->document_type->label(),
                'id' => $document->id,
            ]);
        }

        return self::ordered($events);
    }

    private static function ordered(Collection $events): Collection
    {
        return $events->sort(fn (array $a, array $b): int => ($a['at']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['at']?->getTimestamp() ?? PHP_INT_MAX)
            ?: $a['id'] <=> $b['id'])
            ->values();
    }
}
