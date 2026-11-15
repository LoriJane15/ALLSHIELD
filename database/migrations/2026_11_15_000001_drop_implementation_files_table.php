<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('implementation_files');
    }

    public function down(): void
    {
        // Agenda File data was permanently removed; this forward-only migration cannot restore it.
    }
};
