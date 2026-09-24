<?php

namespace App\Policies;

use App\Models\JapicCertificationPhotoVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;

class JapicCertificationPhotoVersionPolicy
{
    public function view(User $user, JapicCertificationPhotoVersion $version): bool
    {
        $processing = $version->processing;

        return $processing instanceof JapicCertificationProcessing
            && app(JapicCertificationProcessingPolicy::class)->view($user, $processing);
    }
}
