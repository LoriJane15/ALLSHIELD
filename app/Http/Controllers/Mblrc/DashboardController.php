<?php

namespace App\Http\Controllers\Mblrc;

use App\Http\Controllers\Controller;
use App\Models\FormerRebel;
use App\Models\FrProgramStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $formerRebelSummary = FormerRebel::query()
            ->selectRaw('COUNT(*) AS registered')
            ->selectRaw("SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active")
            ->selectRaw("SUM(CASE WHEN status = 'Reintegrated' THEN 1 ELSE 0 END) AS reintegrated")
            ->selectRaw('MAX(registered_at) AS registered_at_max')
            ->selectRaw("MAX(CASE WHEN status = 'Active' THEN updated_at END) AS active_updated_at_max")
            ->selectRaw("MAX(CASE WHEN status = 'Reintegrated' THEN updated_at END) AS reintegrated_updated_at_max")
            ->first();

        $programSummary = FrProgramStatus::query()
            ->selectRaw("SUM(CASE WHEN reintegration_status = 'Completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN reintegration_status = 'On-going' THEN 1 ELSE 0 END) AS ongoing")
            ->selectRaw("SUM(CASE WHEN reintegration_status = 'Not-Started' THEN 1 ELSE 0 END) AS not_started")
            ->selectRaw("MAX(CASE WHEN reintegration_status = 'Completed' THEN reintegration_date END) AS completed_date_max")
            ->first();

        $stats = [
            'registered' => (int) $formerRebelSummary->registered,
            // Legacy counted "enrolled" as former rebels whose status is Active.
            'active' => (int) $formerRebelSummary->active,
            'reintegrated' => (int) $formerRebelSummary->reintegrated,
            'completed' => (int) $programSummary->completed,
            'ongoing' => (int) $programSummary->ongoing,
            'not_started' => (int) $programSummary->not_started,
        ];

        // "As of <date>" captions under each headline figure, as the legacy cards had.
        $fmt = fn ($d) => $d ? Carbon::parse($d)->format('M d, Y') : now()->format('M d, Y');

        $asOf = [
            'registered' => $fmt($formerRebelSummary->registered_at_max),
            'enrolled' => $fmt($formerRebelSummary->active_updated_at_max),
            'completed' => $fmt($programSummary->completed_date_max),
            'reintegrated' => $fmt($formerRebelSummary->reintegrated_updated_at_max),
        ];

        return view('mblrc.dashboard', compact('stats', 'asOf'));
    }

    /** Monthly time-series for the two dashboard line charts (last 7 months). */
    public function analytics(): JsonResponse
    {
        $months = collect(range(6, 0))->map(fn ($i) => Carbon::now()->startOfMonth()->subMonths($i));
        $labels = $months->map(fn ($m) => $m->format('M Y'));

        $formerRebelColumns = [];
        $formerRebelBindings = [];
        $programColumns = [];
        $programBindings = [];

        foreach ($months as $index => $m) {
            $end = (clone $m)->endOfMonth();

            $formerRebelColumns[] = "SUM(CASE WHEN registered_at <= ? THEN 1 ELSE 0 END) AS registered_{$index}";
            $formerRebelBindings[] = $end;
            $formerRebelColumns[] = "SUM(CASE WHEN status = 'Reintegrated' AND latitude IS NOT NULL AND updated_at <= ? THEN 1 ELSE 0 END) AS reintegrated_{$index}";
            $formerRebelBindings[] = $end;

            foreach (['Not-Started' => 'not_started', 'On-going' => 'ongoing', 'Completed' => 'completed'] as $status => $key) {
                $programColumns[] = "SUM(CASE WHEN reintegration_status = ? AND reintegration_date <= ? THEN 1 ELSE 0 END) AS {$key}_{$index}";
                $programBindings[] = $status;
                $programBindings[] = $end;
            }
        }

        $formerRebelSummary = FormerRebel::query()
            ->selectRaw(implode(', ', $formerRebelColumns), $formerRebelBindings)
            ->first();
        $programSummary = FrProgramStatus::query()
            ->selectRaw(implode(', ', $programColumns), $programBindings)
            ->first();

        $program = ['not_started' => [], 'ongoing' => [], 'completed' => []];
        $overall = ['registered' => [], 'reintegrated' => []];

        foreach ($months->keys() as $index) {
            foreach (array_keys($program) as $key) {
                $program[$key][] = (int) $programSummary->{"{$key}_{$index}"};
            }

            $overall['registered'][] = (int) $formerRebelSummary->{"registered_{$index}"};
            $overall['reintegrated'][] = (int) $formerRebelSummary->{"reintegrated_{$index}"};
        }

        return response()->json([
            'labels' => $labels,
            'program' => $program,
            'overall' => $overall,
        ]);
    }

    /** Distribution stats for pie/bar widgets. */
    public function statistics(): JsonResponse
    {
        return response()->json([
            'status' => FormerRebel::select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')->pluck('count', 'status'),
            'gender' => FormerRebel::select('gender', DB::raw('COUNT(*) as count'))
                ->whereNotNull('gender')->groupBy('gender')->pluck('count', 'gender'),
            'batch' => FormerRebel::select('batch_year', DB::raw('COUNT(*) as count'))
                ->whereNotNull('batch_year')->groupBy('batch_year')->orderBy('batch_year')->pluck('count', 'batch_year'),
        ]);
    }
}
