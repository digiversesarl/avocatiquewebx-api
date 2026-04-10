<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pays', function (Blueprint $table) {
            $table->id();

            $table->string('code', 3)->unique();
            $table->string('label_fr')->nullable(false);
            $table->string('label_ar')->nullable(false);
            $table->string('label_en')->nullable(false);
            $table->unsignedSmallInteger('classement')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('bg_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villes');
        Schema::dropIfExists('pays');
    }
};