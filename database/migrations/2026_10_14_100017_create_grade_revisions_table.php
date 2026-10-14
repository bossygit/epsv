<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_value', 6, 2)->nullable();
            $table->decimal('new_value', 6, 2)->nullable();
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('reason');
            $table->foreignId('revised_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index('grade_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_revisions');
    }
};
