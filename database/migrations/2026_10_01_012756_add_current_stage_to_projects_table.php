<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projects now carry an explicit, freely switchable stage (Pre-Design / Design /
 * Post-Design), and `status` is reduced to the lifecycle: active / on_hold / completed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('current_stage', 20)->default('pre_design')->after('status'); // ProjectStage
        });

        DB::table('projects')->whereIn('status', ['tender'])->update(['current_stage' => 'pre_design']);
        DB::table('projects')->whereIn('status', ['design', 'authority_submission'])->update(['current_stage' => 'design']);
        DB::table('projects')->whereIn('status', ['construction', 'completed'])->update(['current_stage' => 'post_design']);
        DB::table('projects')->whereIn('status', ['tender', 'design', 'authority_submission', 'construction'])->update(['status' => 'active']);

        Schema::table('projects', function (Blueprint $table) {
            $table->string('status', 30)->default('active')->change();
            $table->index('current_stage');
        });
    }

    public function down(): void
    {
        DB::table('projects')->where('status', 'active')->where('current_stage', 'pre_design')->update(['status' => 'tender']);
        DB::table('projects')->where('status', 'active')->where('current_stage', 'design')->update(['status' => 'design']);
        DB::table('projects')->where('status', 'active')->where('current_stage', 'post_design')->update(['status' => 'construction']);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['current_stage']);
            $table->dropColumn('current_stage');
            $table->string('status', 30)->default('design')->change();
        });
    }
};
