<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('leave_type', 20)->default('annual');   // LeaveType
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('half_day')->default(false);
            $table->decimal('days', 4, 1)->default(0);             // working days (Mon–Fri)
            $table->text('reason')->nullable();
            $table->text('handover_notes')->nullable();
            $table->foreignId('covering_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');      // LeaveStatus
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_remarks')->nullable();
            $table->jsonb('meta')->nullable();                     // MC attachment path, clash snapshot, etc.
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        DB::statement('ALTER TABLE leave_requests ADD CONSTRAINT leave_requests_date_order CHECK (end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
