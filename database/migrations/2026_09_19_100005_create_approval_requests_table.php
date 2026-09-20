<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_step_id')->constrained()->cascadeOnDelete();
            $table->string('risk_level')->default('medium');
            $table->text('summary');
            $table->string('status')->default('pending');
            $table->string('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('risk_level');
            $table->index(['status', 'risk_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
