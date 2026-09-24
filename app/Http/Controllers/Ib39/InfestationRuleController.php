<?php

namespace App\Http\Controllers\Ib39;

use App\Http\Controllers\Controller;
use App\Models\InfestationRule;
use App\Models\MapBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manage the admin-editable infestation classification rules that drive a
 * barangay's status + colour from its former-rebel count, plus the map legend.
 */
class InfestationRuleController extends Controller
{
    public function index(): View
    {
        $rules = InfestationRule::orderByDesc('min_frs')->orderBy('sort_order')->get();

        return view('ib39.infestation-rules', compact('rules'));
    }

    /** Replace the whole rule set in one save (add/edit/remove rows together). */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.min_frs' => ['required', 'integer', 'min:0'],
            'rules.*.status' => ['required', 'string', 'max:255'],
            'rules.*.color' => ['required', 'string', 'max:50'],
            'rules.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data) {
            InfestationRule::query()->delete();

            foreach (array_values($data['rules']) as $i => $r) {
                InfestationRule::create([
                    'min_frs' => (int) $r['min_frs'],
                    'status' => trim($r['status']),
                    'color' => trim($r['color']),
                    'label' => $r['label'] ?? null,
                    'sort_order' => $i + 1,
                ]);
            }
        });

        MapBarangay::forgetRuleCache();

        // Re-apply the new rules to every barangay that has an FR count, so the
        // map and Add Area list reflect the change immediately.
        $reclassified = 0;
        MapBarangay::where('frs', '>', 0)->chunkById(200, function ($rows) use (&$reclassified) {
            foreach ($rows as $row) {
                $class = MapBarangay::classify((int) $row->frs);
                if ($row->status !== $class['status'] || $row->infestation_color !== $class['color']) {
                    $row->update(['status' => $class['status'], 'infestation_color' => $class['color']]);
                    $reclassified++;
                }
            }
        });

        return back()->with('success', "Rules saved. Re-classified {$reclassified} barangay(s).");
    }
}
