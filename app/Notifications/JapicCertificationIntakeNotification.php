<?php

namespace App\Notifications;

use App\Models\JapicCertificationProcessing;
use App\Models\User;
use Illuminate\Notifications\Notification;

class JapicCertificationIntakeNotification extends Notification
{
    public function __construct(private readonly JapicCertificationProcessing $processing) {}

    public function toDatabase(User $notifiable): array
    {
        return [
            'fr_reference' => $this->processing->surfacedFormerRebel->reference_number,
            'processing_id' => $this->processing->id,
            'received_date' => $this->processing->received_at->toDateString(),
            'due_date' => $this->processing->due_at->toDateString(),
            'route' => 'japic.certifications.show',
            'route_parameter' => $this->processing->id,
        ];
    }
}
