<?php

namespace App\Enums;

enum PswdoEnrollmentDocumentType: string
{
    case EclipEnrollmentForm = 'eclip_enrollment_form';
    case InitialInterviewForm = 'initial_interview_form';
    case ProfilingInterviewForm = 'profiling_interview_form';
    case EndorsementLetter = 'endorsement_letter';

    public function label(): string
    {
        return match ($this) {
            self::EclipEnrollmentForm => 'E-CLIP Enrollment Form',
            self::InitialInterviewForm => 'Initial Interview Form',
            self::ProfilingInterviewForm => 'Profiling Interview Form',
            self::EndorsementLetter => 'Endorsement Letter',
        };
    }

    public function isEndorsementLetter(): bool
    {
        return $this === self::EndorsementLetter;
    }

    /** @return array<int, self> */
    public static function prerequisites(): array
    {
        return [self::EclipEnrollmentForm, self::InitialInterviewForm, self::ProfilingInterviewForm];
    }
}
