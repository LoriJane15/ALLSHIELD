<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ib39_cdr_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cdr_processing_id')
                ->constrained('ib39_cdr_processings')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('author_role', 50);
            $table->text('text');
            $table->timestamps();

            $table->index(['cdr_processing_id', 'created_at'], 'ib39_cdr_comments_processing_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ib39_cdr_comments');
    }
};
