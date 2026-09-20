<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('step_name');
            $table->string('step_type');
            $table->string('status')->default('completed');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->decimal('cost_usd', 10, 4)->nullable();
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('step_type');
            $table->index(['workflow_run_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('run_steps');
    }
};
