<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authority_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('authority', 20);                       // AuthorityName
            $table->string('submission_type');                     // e.g. Building Plan, Fire Safety, Water Reticulation
            $table->string('reference_no')->nullable();            // authority file / ref number
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');        // SubmissionStatus
            $table->date('submitted_at')->nullable();
            $table->date('resubmitted_at')->nullable();
            $table->unsignedSmallInteger('sla_days')->default(30);
            $table->date('expected_response_date')->nullable();    // derived: (resubmitted_at ?? submitted_at) + sla_days
            $table->date('approved_at')->nullable();
            $table->string('approval_ref')->nullable();
            $table->jsonb('query_logs')->default(DB::raw("'[]'::jsonb")); // [{id, query_date, officer, reference, details, reply_due, logged_by, logged_at}]
            $table->jsonb('documents')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['authority', 'status']);
            $table->index('expected_response_date');
        });

        DB::statement('CREATE INDEX authority_submissions_query_logs_gin ON authority_submissions USING GIN (query_logs)');
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_submissions');
    }
};
