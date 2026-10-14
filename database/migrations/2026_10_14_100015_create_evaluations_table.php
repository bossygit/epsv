<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_period_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('devoir'); // devoir | composition | interrogation | autre
            $table->date('evaluation_date');
            $table->decimal('max_score', 5, 2)->default(20); // barème de l'évaluation
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->string('status')->default('draft'); // draft | published | cancelled
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_period_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
