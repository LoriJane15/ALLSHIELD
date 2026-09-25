<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\Municipality;
use App\Models\RcspBarangay;
use App\Services\ImplanWorkbookExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImplanController extends Controller
{
    public function index(): View
    {
        $counts = DB::table('implementations')
            ->join('users', 'users.id', '=', 'implementations.lgu_user_id')
            ->where('implementations.status', '!=', 'not yet started')
            ->whereNotNull('users.municipality_id')
            ->groupBy('users.municipality_id')
            ->selectRaw('users.municipality_id, COUNT(*) as total')
            ->pluck('total', 'municipality_id');

        $municipalities = Municipality::orderBy('name')->get();

        return view('admin.implan.index-monitoring', compact('municipalities', 'counts'));
    }

    public function show(Implementation $implan): View
    {
        abort_if($implan->status === 'not yet started', 404);
        $municipality = $implan->lguUser?->municipality;
        abort_unless($municipality, 404, 'The IMPLAN has no municipality.');

        return $this->municipality($municipality);
    }

    public function municipality(Municipality $municipality): View
    {
        $implans = $this->submittedMunicipalityImplans($municipality);

        return view('admin.implan.municipality', $this->officialViewData($municipality, $implans));
    }

    public function download(Municipality $municipality, ImplanWorkbookExporter $exporter): StreamedResponse
    {
        return $exporter->download($municipality, $this->submittedMunicipalityImplans($municipality));
    }

    public function verify(Implementation $implan): RedirectResponse
    {
        abort(403, 'Katuparan IMPLAN monitoring is read-only.');
    }

    public function reassign(Implementation $implan): RedirectResponse
    {
        abort(403, 'Katuparan IMPLAN monitoring is read-only.');
    }

    private function submittedMunicipalityImplans(Municipality $municipality)
    {
        return Implementation::query()
            ->whereHas('lguUser', fn ($query) => $query->where('municipality_id', $municipality->id))
            ->where('status', '!=', 'not yet started')
            ->with([
                'lguUser.municipality', 'originalFiles', 'originalPhotos',
                'responses.govAgency', 'responses.files', 'responses.photos',
            ])
            ->oldest('uploaded_at')
            ->oldest('id')
            ->get();
    }

    private function officialViewData(Municipality $municipality, $implans): array
    {
        return [
            'municipality' => $municipality,
            'implans' => $implans,
            'agenciesById' => GovAgency::all()->keyBy('id'),
            'areaNamesByImplan' => $implans->mapWithKeys(fn (Implementation $implan) => [
                $implan->id => RcspBarangay::with('barangay')->whereIn('id', $implan->target_areas ?? [])
                    ->get()->map(fn ($area) => $area->barangay?->name ?? "Barangay #{$area->barangay_id}"),
            ]),
        ];
    }
}
