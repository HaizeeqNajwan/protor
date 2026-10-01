<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Drops the covering-colleague field and adds AM / PM half-day leave. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('day_part', 10)->default('full')->after('half_day'); // LeaveDayPart: full | am | pm
        });

        DB::table('leave_requests')->where('half_day', true)->update(['day_part' => 'am']);

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('covering_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('covering_user_id')->nullable()->after('handover_notes')->constrained('users')->nullOnDelete();
            $table->dropColumn('day_part');
        });
    }
};
