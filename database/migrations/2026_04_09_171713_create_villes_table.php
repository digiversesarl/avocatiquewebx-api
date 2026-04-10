<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villes', function (Blueprint $table) {
            $table->id();

            $table->string('label_fr');
            $table->string('label_ar')->nullable();
            $table->string('label_en')->nullable();
            $table->string('abbreviation', 10)->nullable();

            $table->foreignId('pays_id')
                  ->constrained('pays')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->integer('classement')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->string('bg_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();

            $table->timestamps();
           
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villes');
    }
};