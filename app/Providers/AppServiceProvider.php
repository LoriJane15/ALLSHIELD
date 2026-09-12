<?php

namespace App\Providers;

use App\Contracts\Ib39FeaReadiness;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39CdrProcessing;
use App\Models\Ib39FeaDocument;
use App\Models\Ib39FeaDocumentVersion;
use App\Models\Ib39FeaProcessing;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationPhotoVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\PswdoEnrollment;
use App\Models\PswdoEnrollmentDocument;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Policies\Ib39CdrDocumentVersionPolicy;
use App\Policies\Ib39CdrProcessingPolicy;
use App\Policies\Ib39FeaDocumentPolicy;
use App\Policies\Ib39FeaDocumentVersionPolicy;
use App\Policies\Ib39FeaProcessingPolicy;
use App\Policies\Ib39SurfacedFormerRebelPolicy;
use App\Policies\JapicCertificationDocumentVersionPolicy;
use App\Policies\JapicCertificationPhotoVersionPolicy;
use App\Policies\JapicCertificationProcessingPolicy;
use App\Policies\PswdoEnrollmentDocumentPolicy;
use App\Policies\PswdoEnrollmentPolicy;
use App\Policies\RcspBarangayPolicy;
use App\Policies\RcspFormPolicy;
use App\Services\LockedIb39FeaReadiness;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Ib39FeaReadiness::class, LockedIb39FeaReadiness::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Ib39SurfacedFormerRebel::class, Ib39SurfacedFormerRebelPolicy::class);
        Gate::policy(JapicCertificationProcessing::class, JapicCertificationProcessingPolicy::class);
        Gate::policy(JapicCertificationDocumentVersion::class, JapicCertificationDocumentVersionPolicy::class);
        Gate::policy(JapicCertificationPhotoVersion::class, JapicCertificationPhotoVersionPolicy::class);
        Gate::policy(PswdoEnrollment::class, PswdoEnrollmentPolicy::class);
        Gate::policy(PswdoEnrollmentDocument::class, PswdoEnrollmentDocumentPolicy::class);
        Gate::policy(Ib39CdrProcessing::class, Ib39CdrProcessingPolicy::class);
        Gate::policy(Ib39CdrDocumentVersion::class, Ib39CdrDocumentVersionPolicy::class);
        Gate::policy(Ib39FeaProcessing::class, Ib39FeaProcessingPolicy::class);
        Gate::policy(Ib39FeaDocument::class, Ib39FeaDocumentPolicy::class);
        Gate::policy(Ib39FeaDocumentVersion::class, Ib39FeaDocumentVersionPolicy::class);
        Gate::policy(RcspBarangay::class, RcspBarangayPolicy::class);
        Gate::policy(RcspForm::class, RcspFormPolicy::class);
        // SkyDash uses Bootstrap — render paginator links with Bootstrap markup.
        Paginator::useBootstrapFive();
    }
}
