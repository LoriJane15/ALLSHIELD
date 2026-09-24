<?php

namespace App\Services;

use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Notifications\JapicCertificationIntakeNotification;
use Illuminate\Support\Facades\DB;

class JapicCertificationIntakeNotifier
{
    public function notify(JapicCertificationProcessing $processing): void
    {
        $processing->loadMissing('surfacedFormerRebel');
        User::query()->where('role', 'japic')->where('is_active', true)->orderBy('id')->eachById(
            function (User $user) use ($processing): void {
                $notification = new JapicCertificationIntakeNotification($processing);
                DB::table('notifications')->insertOrIgnore([
                    'id' => $this->deterministicId($processing->id, $user->id),
                    'type' => $notification::class,
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => $user->getKey(),
                    'data' => json_encode($notification->toDatabase($user), JSON_THROW_ON_ERROR),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        );
    }

    private function deterministicId(int $processingId, int $userId): string
    {
        $hex = hash('sha256', "japic-intake:{$processingId}:{$userId}");
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
