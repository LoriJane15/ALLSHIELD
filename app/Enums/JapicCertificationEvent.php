<?php

namespace App\Enums;

enum JapicCertificationEvent: string
{
    case IntakeCreated = 'intake_created';
    case IntakeBackfilled = 'intake_backfilled';
    case Started = 'started';
    case DraftSaved = 'draft_saved';
    case MarkedForSigning = 'marked_for_signing';
    case SignaturesSecured = 'signatures_secured';
    case FinalUploaded = 'final_uploaded';
    case FinalReplaced = 'final_replaced';
    case DelayRecorded = 'delay_recorded';
    case FrCancelled = 'fr_cancelled';
    case FrCancelledAfterCompletion = 'fr_cancelled_after_completion';
}
