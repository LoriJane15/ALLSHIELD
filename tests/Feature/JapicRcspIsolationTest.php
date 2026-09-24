<?php

namespace Tests\Feature;

use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationPhotoVersion;
use App\Models\JapicCertificationProcessing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JapicRcspIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_japic_schema_models_and_services_have_no_rcsp_dependency(): void
    {
        foreach (['japic_certification_processings', 'japic_certification_drafts',
            'japic_certification_draft_histories', 'japic_certification_histories',
            'japic_certification_document_versions', 'japic_certification_photo_versions'] as $table) {
            foreach (DB::select("PRAGMA foreign_key_list('{$table}')") as $foreignKey) {
                $this->assertFalse(str_starts_with($foreignKey->table, 'rcsp_'));
            }
        }
        $this->assertFalse(method_exists(new JapicCertificationProcessing, 'rcsp'));
        $this->assertFalse(method_exists(new JapicCertificationDocumentVersion, 'rcsp'));
        $this->assertFalse(method_exists(new JapicCertificationPhotoVersion, 'rcsp'));

        foreach (['JapicCertificationIntakeService.php', 'JapicCertificationCancellationCoordinator.php',
            'JapicCertificationDraftService.php', 'JapicCertificationWorkflowService.php', 'JapicCertificationDocumentService.php',
            'JapicCertificationPhotoService.php'] as $file) {
            $this->assertStringNotContainsString('Rcsp', file_get_contents(app_path('Services/'.$file)));
            $this->assertStringNotContainsString('rcsp_', strtolower(file_get_contents(app_path('Services/'.$file))));
        }

        foreach ([
            app_path('Http/Controllers/Japic/DashboardController.php'),
            app_path('Http/Controllers/Japic/CertificationController.php'),
            app_path('Notifications/JapicCertificationIntakeNotification.php'),
            app_path('Services/JapicCertificationIntakeNotifier.php'),
            app_path('Http/Controllers/Japic/CertificationDraftController.php'),
            app_path('Http/Controllers/Japic/CertificationDocumentController.php'),
            app_path('Http/Controllers/Japic/CertificationWorkflowController.php'),
            app_path('Http/Controllers/Japic/CertificationPhotoController.php'),
        ] as $file) {
            $source = strtolower(file_get_contents($file));
            $this->assertStringNotContainsString('app\\models\\rcsp', $source);
            $this->assertStringNotContainsString("db::table('rcsp", $source);
            $this->assertStringNotContainsString("route('rcsp", $source);
        }
        $this->assertSame(0, DB::table('sqlite_master')->where('type', 'table')->where('name', 'like', 'rcsp_%')->where('sql', 'like', '%japic%')->count());
    }
}
