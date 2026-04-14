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
        Schema::table('fonctions', function (Blueprint $table) {
            $table->string('code', 20)->change();
            $table->string('bg_color', 20)->nullable()->change();
            $table->string('text_color', 20)->nullable()->change();
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->string('code', 20)->change();
            $table->string('bg_color', 20)->nullable()->change();
            $table->string('text_color', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fonctions', function (Blueprint $table) {
            $table->string('code', 255)->change();
            $table->string('bg_color', 255)->nullable()->change();
            $table->string('text_color', 255)->nullable()->change();
        });

        Schema::table('grades', function (Blueprint $table) {
            $table->string('code', 255)->change();
            $table->string('bg_color', 255)->nullable()->change();
            $table->string('text_color', 255)->nullable()->change();
        });
    }
};
