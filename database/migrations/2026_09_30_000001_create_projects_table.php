<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_code', 30)->unique();          // e.g. PRT-2026-014
            $table->string('title');
            $table->string('client_name');
            $table->string('client_contact')->nullable();
            $table->string('location')->nullable();                // e.g. Jalan Stutong, Kuching
            $table->string('discipline', 30);                      // EngineeringDiscipline
            $table->string('status', 30)->default('design');       // ProjectStatus
            $table->decimal('contract_sum', 15, 2)->default(0);    // construction contract value (RM)
            $table->decimal('consultancy_fee', 15, 2)->default(0); // firm's agreed fee (RM)
            $table->date('start_date')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->date('actual_completion_date')->nullable();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->jsonb('metadata')->nullable();                 // lot no., title no., SPA ref, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'discipline']);
            $table->index('target_completion_date');
        });

        DB::statement('CREATE INDEX projects_metadata_gin ON projects USING GIN (metadata)');

        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();                    // e.g. C&S Engineer, M&E Engineer, Draughtsman
            $table->timestamps();

            $table->primary(['project_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
    }
};
