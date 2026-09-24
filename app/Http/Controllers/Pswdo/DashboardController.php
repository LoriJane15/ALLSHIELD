<?php

namespace App\Http\Controllers\Pswdo;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Http\Controllers\Controller;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\PswdoEnrollment;
use App\Services\PswdoEligibilityService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(PswdoEligibilityService $eligibility): View
    {
        Gate::authorize('viewAny', PswdoEnrollment::class);

        $eligible = PswdoEnrollment::query()
            ->whereHas('surfacedFormerRebel', fn ($query) => $eligibility->apply($query));
        $requiredDocuments = count(PswdoEnrollmentDocumentType::cases());
        $enrollments = (clone $eligible)->with([
            'documents.uploader:id,name',
            'surfacedFormerRebel.municipality',
            'surfacedFormerRebel.barangay',
        ])->latest('updated_at')->get();
        $total = $enrollments->count();
        $completed = $enrollments->filter->isCompleted()->count();
        $documents = $enrollments->flatMap->documents;

        $documentBreakdown = collect(PswdoEnrollmentDocumentType::cases())->map(function (PswdoEnrollmentDocumentType $type) use ($documents): array {
            return [
                'label' => $type->label(),
                'count' => $documents->where('document_type', $type)->count(),
            ];
        });
        $actionQueue = $enrollments->reject->isCompleted()
            ->sortBy(fn (PswdoEnrollment $enrollment) => $enrollment->completedDocumentCount())
            ->take(5)->values();
        $recentDocuments = $enrollments->flatMap(fn (PswdoEnrollment $enrollment) => $enrollment->documents
            ->map(fn ($document) => ['enrollment' => $enrollment, 'document' => $document]))
            ->sortByDesc(fn (array $item) => $item['document']->uploaded_at)
            ->take(5)->values();
        $areaSummary = $enrollments->groupBy(fn (PswdoEnrollment $enrollment) => $enrollment->surfacedFormerRebel->municipality?->name ?? 'Unassigned')
            ->map(fn ($items, string $area): array => [
                'name' => $area,
                'total' => $items->count(),
                'completed' => $items->filter->isCompleted()->count(),
            ])->sortByDesc('total')->take(5)->values();
        $cdrReady = Ib39SurfacedFormerRebel::query()->whereHas('cdrProcessing', fn ($query) => $query
            ->where('status', 'Completed')->whereNotNull('current_final_version_id'))->count();

        return view('pswdo.dashboard', [
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
            'requiredDocuments' => $requiredDocuments,
            'documentBreakdown' => $documentBreakdown,
            'actionQueue' => $actionQueue,
            'recentDocuments' => $recentDocuments,
            'areaSummary' => $areaSummary,
            'pipeline' => [
                ['label' => 'Surfaced FRs', 'count' => Ib39SurfacedFormerRebel::count(), 'icon' => 'mdi-account-search-outline'],
                ['label' => 'CDR completed', 'count' => $cdrReady, 'icon' => 'mdi-file-check-outline'],
                ['label' => 'Eligible for PSWDO', 'count' => $total, 'icon' => 'mdi-account-check-outline'],
                ['label' => 'Enrollment complete', 'count' => $completed, 'icon' => 'mdi-check-decagram'],
            ],
        ]);
    }
}