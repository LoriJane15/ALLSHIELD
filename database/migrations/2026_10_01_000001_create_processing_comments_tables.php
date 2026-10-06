<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'japic_certification_comments' => ['japic_certification_processing_id', 'japic_certification_processings'],
            'ib39_fea_processing_comments' => ['ib39_fea_processing_id', 'ib39_fea_processings'],
            'pswdo_enrollment_comments' => ['pswdo_enrollment_id', 'pswdo_enrollments'],
        ] as $name => [$parentKey, $parentTable]) {
            Schema::create($name, function (Blueprint $table) use ($name, $parentKey, $parentTable): void {
                $table->id();
                $table->foreignId($parentKey);
                $table->foreign($parentKey, $name.'_parent_fk')->references('id')->on($parentTable)->restrictOnDelete();
                $table->foreignId('user_id');
                $table->foreign('user_id', $name.'_user_fk')->references('id')->on('users')->restrictOnDelete();
                $table->string('author_role', 50);
                $table->text('text');
                $table->timestamps();
                $table->index([$parentKey, 'created_at', 'id'], $name.'_chronological_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pswdo_enrollment_comments');
        Schema::dropIfExists('ib39_fea_processing_comments');
        Schema::dropIfExists('japic_certification_comments');
    }
};
