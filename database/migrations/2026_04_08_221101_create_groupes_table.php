<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('groupes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique()->nullable();
            $table->string('label_fr');
            $table->string('label_ar')->nullable();
            $table->string('label_en')->nullable();
            $table->unsignedSmallInteger('classement')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('bg_color', 20)->nullable();
            $table->string('text_color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('user_groupe', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('groupe_id')->constrained()->onDelete('cascade');
            $table->primary(['user_id', 'groupe_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_groupe');
        Schema::dropIfExists('groupes');
    }
};
