<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->string('stage', 20)->default('design')->after('discipline'); // ProjectStage

            $table->index(['project_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'stage']);
            $table->dropColumn('stage');
        });
    }
};
