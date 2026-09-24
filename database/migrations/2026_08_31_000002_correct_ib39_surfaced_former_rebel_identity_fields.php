<?php

use App\Enums\Ib39ForwardingOrganization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('ib39_surfaced_former_rebels')->exists()) {
            throw new RuntimeException(
                'Cannot add required surfaced FR name fields while records exist without approved names.'
            );
        }

        Schema::table('ib39_surfaced_former_rebels', function (Blueprint $table) {
            $table->dropIndex(['forwarded']);
            $table->dropIndex(['forwarding_organization']);
        });

        Schema::table('ib39_surfaced_former_rebels', function (Blueprint $table) {
            $table->dropColumn([
                'forwarded',
                'forwarding_organization',
                'other_organization_specification',
            ]);
        });

        Schema::table('ib39_surfaced_former_rebels', function (Blueprint $table) {
            $table->string('first_name', 100);
            $table->string('last_name', 100);
        });
    }

    public function down(): void
    {
        Schema::table('ib39_surfaced_former_rebels', function (Blueprint $table) {
            $table->boolean('forwarded')->default(false)->index();
            $table->enum('forwarding_organization', array_column(Ib39ForwardingOrganization::cases(), 'value'))->nullable()->index();
            $table->string('other_organization_specification')->nullable();
        });

        Schema::table('ib39_surfaced_former_rebels', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
