<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Works no longer go through a manager review: status now follows progress
 * (0% not started, 1–99% in progress, 100% completed).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_tasks')
            ->where('status', 'review_pending')
            ->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible data simplification.
    }
};
