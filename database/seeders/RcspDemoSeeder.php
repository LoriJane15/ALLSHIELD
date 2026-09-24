<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RcspDemoSeeder extends Seeder
{
    public const CATALOG_KEY = 'rcsp-demo-v1';

    private const CATALOG = [
        0 => ['DEMO Pre-Shaping', ['DEMO: Organize initial coordination meeting', 'DEMO: Prepare barangay baseline information', 'DEMO: Identify participating offices']],
        1 => ['DEMO Shape', ['DEMO: Conduct community orientation', 'DEMO: Record stakeholder participation', 'DEMO: Prepare initial action plan']],
        2 => ['DEMO Access', ['DEMO: Conduct community needs assessment', 'DEMO: Document identified concerns', 'DEMO: Coordinate access to required services']],
        3 => ['DEMO Transform', ['DEMO: Implement approved barangay activity', 'DEMO: Record participation and outputs', 'DEMO: Document implementation results']],
        4 => ['DEMO Sustain', ['DEMO: Prepare sustainability arrangement', 'DEMO: Assign responsible monitoring personnel', 'DEMO: Record follow-up commitments']],
        5 => ['DEMO Monitor', ['DEMO: Conduct monitoring visit', 'DEMO: Record progress and remaining issues', 'DEMO: Prepare completion assessment']],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('RCSP demo seeding is permitted only in local or testing environments.');
        }
        $password = (string) env('RCSP_DEMO_PASSWORD', '');
        if (strlen($password) < 12 || ! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password)) {
            throw new RuntimeException('Set RCSP_DEMO_PASSWORD to at least 12 characters with uppercase, lowercase, and a number, then rerun RcspDemoSeeder.');
        }
        $requiredColumns = ['rcsp_phases' => ['catalog_key'], 'rcsp_barangays' => ['catalog_key'],
            'rcsp_forms' => ['reviewed_by_user_id', 'reviewed_at']];
        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException('The RCSP hardening migration must be applied before running RcspDemoSeeder.');
                }
            }
        }

        DB::transaction(function () use ($password): void {
            $municipality = Municipality::firstOrCreate(['name' => 'DEMO Municipality']);
            $barangays = collect(['Pending', 'Submitted', 'In-Progress', 'Completed', 'Manual Workflow'])
                ->mapWithKeys(fn ($state) => [$state => Barangay::firstOrCreate([
                    'municipality_id' => $municipality->id, 'name' => "DEMO {$state} Barangay",
                ])]);

            $reviewer = $this->demoUser('katuparan_demo', 'Demo Katuparan Reviewer', 'admin', null, $password);
            $lgu = $this->demoUser('rcsp_lgu_demo', 'Demo LGU RCSP User', 'lgu', $municipality->id, $password);
            $phases = $this->catalog();

            $this->barangayState($barangays['Pending'], 'Pending', 0, [], $lgu, $reviewer, $phases);
            $this->barangayState($barangays['Submitted'], 'Ongoing', 0, [], $lgu, $reviewer, $phases, [
                0 => ['submitted', 'submitted', 'submitted'],
            ]);
            $this->barangayState($barangays['In-Progress'], 'Ongoing', 3, [0, 1, 2], $lgu, $reviewer, $phases, [
                0 => ['approved', 'approved', 'approved'], 1 => ['approved', 'approved', 'approved'],
                2 => ['approved', 'approved', 'approved'], 3 => ['approved', 'to be complied', 'to be conducted'],
            ]);
            $this->barangayState($barangays['Completed'], 'Completed', 5, range(0, 5), $lgu, $reviewer, $phases,
                collect(range(0, 5))->mapWithKeys(fn ($n) => [$n => ['approved', 'approved', 'approved']])->all());
        });
    }

    private function demoUser(string $username, string $name, string $role, ?int $municipalityId, string $password): User
    {
        $user = User::where('username', $username)->first();
        if ($user && ($user->name !== $name || $user->role !== $role || $user->municipality_id !== $municipalityId)) {
            throw new RuntimeException("Demo username {$username} conflicts with an existing non-demo account.");
        }

        return $user ?? User::create(['username' => $username, 'name' => $name, 'email' => null,
            'password' => Hash::make($password), 'role' => $role, 'municipality_id' => $municipalityId]);
    }

    private function catalog(): array
    {
        $result = [];
        foreach (self::CATALOG as $number => [$name, $descriptions]) {
            $phase = RcspPhase::where('catalog_key', self::CATALOG_KEY)->where('number', $number)->first();
            if ($phase && $phase->name !== $name) {
                throw new RuntimeException("Demo phase {$number} is contradictory; no destructive repair was attempted.");
            }
            $phase ??= RcspPhase::create(['catalog_key' => self::CATALOG_KEY, 'number' => $number, 'name' => $name]);
            foreach ($descriptions as $description) {
                RcspActivity::firstOrCreate(['rcsp_phase_id' => $phase->id, 'description' => $description]);
            }
            $actual = $phase->activities()->pluck('description')->sort()->values()->all();
            $expected = collect($descriptions)->sort()->values()->all();
            if ($actual !== $expected) {
                throw new RuntimeException("Demo activities for phase {$number} are contradictory.");
            }
            $result[$number] = $phase;
        }

        return $result;
    }

    private function barangayState(Barangay $barangay, string $status, int $currentPhase, array $completed,
        User $lgu, User $reviewer, array $phases, array $forms = []): void
    {
        $record = RcspBarangay::where('barangay_id', $barangay->id)->first();
        if ($record && ($record->catalog_key !== self::CATALOG_KEY || $record->status !== $status || $record->current_phase !== $currentPhase)) {
            throw new RuntimeException("{$barangay->name} has a contradictory state; no destructive repair was attempted.");
        }
        $record ??= RcspBarangay::create(['barangay_id' => $barangay->id, 'municipality_id' => $barangay->municipality_id,
            'catalog_key' => self::CATALOG_KEY, 'status' => $status, 'current_phase' => $currentPhase]);
        $flags = collect(range(0, 5))->mapWithKeys(fn ($n) => ["phase{$n}_completed" => in_array($n, $completed, true)])->all();
        $phaseStatus = $record->phaseStatus()->firstOrCreate([], $flags);
        foreach ($flags as $key => $value) {
            if ((bool) $phaseStatus->{$key} !== $value) {
                throw new RuntimeException("{$barangay->name} has contradictory completion flags.");
            }
        }

        $base = now()->subDays(30);
        foreach ($forms as $number => $statuses) {
            $activities = $phases[$number]->activities()->orderBy('id')->get();
            foreach ($activities as $index => $activity) {
                $formStatus = $statuses[$index];
                $remark = match ($formStatus) {
                    'to be complied' => 'DEMO: Provide corrected non-sensitive supporting information.',
                    'to be conducted' => 'DEMO: Complete this test activity before approval.',
                    'approved' => 'DEMO: Approved for workflow demonstration.',
                    default => null,
                };
                $reviewed = $formStatus !== 'submitted';
                $submittedAt = $base->copy()->addDays($number * 4)->addHours($index * 2);
                $attributes = ['rcsp_barangay_id' => $record->id, 'rcsp_phase_id' => $phases[$number]->id,
                    'rcsp_activity_id' => $activity->id];
                $values = ['lgu_user_id' => $lgu->id, 'conduct' => 'yes', 'file' => null, 'status' => $formStatus,
                    'remarks' => $remark, 'reviewed_by_user_id' => $reviewed ? $reviewer->id : null,
                    'reviewed_at' => $reviewed ? $submittedAt->copy()->addHour() : null,
                    'created_at' => $submittedAt, 'updated_at' => $reviewed ? $submittedAt->copy()->addHour() : $submittedAt];
                $form = RcspForm::where($attributes)->first();
                if ($form) {
                    foreach (['lgu_user_id', 'conduct', 'file', 'status', 'remarks', 'reviewed_by_user_id'] as $key) {
                        if ($form->{$key} !== $values[$key]) {
                            throw new RuntimeException("A demo form for {$barangay->name} is contradictory.");
                        }
                    }
                } else {
                    $form = RcspForm::create($attributes + $values);
                    $form->timestamps = false;
                    $form->forceFill(['created_at' => $values['created_at'], 'updated_at' => $values['updated_at']])->saveQuietly();
                }
            }
        }
    }
}
