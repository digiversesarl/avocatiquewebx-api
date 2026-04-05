<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('label_fr');
            $table->string('label_ar')->nullable();
            $table->string('label_en')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('route')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('module', 100)->nullable();
            $table->timestamps();

            $table->foreign('parent_id')
                  ->references('id')
                  ->on('menu_items')
                  ->onDelete('cascade');

            $table->index(['parent_id', 'ordre']);
            $table->index('is_visible');
        });

        Schema::create('menu_item_role', function (Blueprint $table): void {
            $table->foreignId('menu_item_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('role_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->primary(['menu_item_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_role');
        Schema::dropIfExists('menu_items');
    }
};
