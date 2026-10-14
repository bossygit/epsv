<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft'); // draft | generated | validated | locked
            $table->string('file_path')->nullable();
            $table->decimal('average', 6, 2)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedInteger('class_size')->nullable();
            $table->string('decision')->nullable(); // admis | redouble | en_attente
            $table->json('metadata')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'school_period_id', 'version'], 'report_cards_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};
