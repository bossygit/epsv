<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('sequence');
            $table->string('type')->default('trimestre'); // trimestre | semestre
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('weight', 5, 2)->default(1);
            $table->string('status')->default('open'); // open | submitted | validated | locked
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_periods');
    }
};
