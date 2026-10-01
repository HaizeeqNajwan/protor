<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // PIC
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('discipline', 30)->nullable();          // EngineeringDiscipline
            $table->string('priority', 20)->default('medium');     // TaskPriority
            $table->string('status', 20)->default('not_started');  // TaskStatus
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('progress_logs')->default(DB::raw("'[]'::jsonb")); // [{at, by, by_name, from, to, note}]
            $table->jsonb('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('due_date');
        });

        DB::statement('ALTER TABLE project_tasks ADD CONSTRAINT project_tasks_progress_range CHECK (progress_percentage BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
