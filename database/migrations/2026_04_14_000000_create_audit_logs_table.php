<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();          // snapshot du nom au moment de l'action
            $table->string('action', 50);                      // login_success, record_created, record_updated, …
            $table->string('category', 30)->default('data');   // auth, data, roles, security, settings
            $table->string('auditable_type')->nullable();      // App\Models\Pays, etc.
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('auditable_label')->nullable();     // label lisible de l'enregistrement
            $table->json('old_values')->nullable();            // valeurs avant modification
            $table->json('new_values')->nullable();            // valeurs après modification
            $table->string('result', 10)->default('success');  // success | failure
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('description')->nullable();           // description libre
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
