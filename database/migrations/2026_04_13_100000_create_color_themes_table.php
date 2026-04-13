<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();         // ex: "violet", "my-custom"
            $table->string('label', 100);                  // ex: "Violet moderne"
            $table->string('color1', 7);                   // hex #6D28D9
            $table->string('color2', 7);                   // hex #A855F7
            $table->string('color3', 7);                   // hex #F472B6
            $table->boolean('is_default')->default(false);  // thèmes livrés par défaut (non supprimables)
            $table->unsignedInteger('classement')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('color_themes');
    }
};
