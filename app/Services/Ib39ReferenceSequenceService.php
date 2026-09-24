<?php

namespace App\Services;

use App\Models\Ib39ReferenceSequence;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class Ib39ReferenceSequenceService
{
    public function reserveSurfacedFormerRebelReference(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An FR reference must be reserved inside the record-creation transaction.');
        }

        $updated = DB::table('ib39_reference_sequences')
            ->where('sequence_name', Ib39ReferenceSequence::SURFACED_FORMER_REBELS)
            ->increment('current_value', 1, ['updated_at' => now()]);

        if ($updated !== 1) {
            throw new RuntimeException('The 39th IB surfaced FR reference sequence is unavailable.');
        }

        $number = (int) DB::table('ib39_reference_sequences')
            ->where('sequence_name', Ib39ReferenceSequence::SURFACED_FORMER_REBELS)
            ->value('current_value');

        return 'FR'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
