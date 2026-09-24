<?php

namespace App\Http\Controllers\Japic;

use App\Enums\JapicCertificationStatus;
use App\Http\Controllers\Controller;
use App\Models\JapicCertificationProcessing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', JapicCertificationProcessing::class);
        $user = $request->user();
        $reference = JapicCertificationProcessing::deadlineReferenceSql();
        $statuses = JapicCertificationStatus::cases();
        $statusSql = collect($statuses)->map(fn ($status) => "SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS status_{$status->name}")->implode(', ');
        $bindings = collect($statuses)->pluck('value')->all();
        $today = today()->toDateString();
        $dueSoon = today()->addDays(3)->toDateString();

        $stats = JapicCertificationProcessing::query()
            ->where(fn ($query) => $query->whereNull('assigned_to')->orWhere('assigned_to', $user->id))
            ->selectRaw("COUNT(*) AS total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN DATE({$reference}) > DATE(due_at) THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN status NOT IN (?, ?) AND DATE(due_at) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS due_soon,
                {$statusSql}", [
                JapicCertificationStatus::Completed->value,
                JapicCertificationStatus::Cancelled->value,
                JapicCertificationStatus::Completed->value,
                JapicCertificationStatus::Cancelled->value,
                now()->toDateTimeString(),
                JapicCertificationStatus::Completed->value,
                JapicCertificationStatus::Cancelled->value,
                $today,
                $dueSoon,
                ...$bindings,
            ])->first();

        $statusCounts = collect($statuses)->mapWithKeys(fn ($status) => [
            $status->value => (int) ($stats->{'status_'.$status->name} ?? 0),
        ])->filter();

        $notifications = $user->notifications()->latest()->limit(10)->get();
        $processingIds = $notifications->map(fn ($notification) => $notification->data['processing_id'] ?? null)
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($id) => (int) $id)
            ->unique();
        $viewableProcessingIds = JapicCertificationProcessing::query()
            ->whereIn('id', $processingIds)
            ->where(fn ($query) => $query->whereNull('assigned_to')->orWhere('assigned_to', $user->id))
            ->pluck('id')
            ->flip();
        $notifications = $notifications->filter(fn ($notification) => $viewableProcessingIds->has(
            (int) ($notification->data['processing_id'] ?? 0)
        ));

        return view('japic.dashboard', [
            'stats' => $stats,
            'statusCounts' => $statusCounts,
            'notifications' => $notifications,
        ]);
    }
}
