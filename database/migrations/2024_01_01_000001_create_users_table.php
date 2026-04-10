<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            // Identité
            $table->string('matricule')->unique()->nullable();
            $table->string('login')->unique()->nullable();
            $table->string('email')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Noms
            $table->string('full_name_fr')->nullable();
            $table->string('full_name_ar')->nullable();
            $table->string('abbreviation_fr', 20)->nullable();
            $table->string('abbreviation_ar', 20)->nullable();

            // Profil
            $table->string('photo')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('cin', 20)->nullable();
            $table->string('rib', 30)->nullable();
            $table->date('date_entree')->nullable();
            $table->string('langue', 10)->default('fr');

            // Poste
            $table->string('fonction')->nullable();
            $table->string('grade_avocat')->nullable();
            $table->string('departement')->nullable();

            // Adresses
            $table->text('address_fr')->nullable();
            $table->text('address_ar')->nullable();

            // Flags booléens
            $table->boolean('avocat_proprietaire')->default(false);
            $table->boolean('valeur_par_defaut')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('is_admin')->default(false);

            // Apparence
            $table->string('couleur_fond', 20)->nullable();
            $table->string('couleur_texte', 20)->nullable();
            $table->unsignedSmallInteger('classement')->default(0);

            // Tarif & observation
            $table->decimal('tarif_journalier', 10, 2)->nullable();
            $table->text('observation')->nullable();

            // 2FA
            $table->boolean('tfa_enabled')->default(false);
            $table->string('tfa_secret')->nullable();

            // Statut
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};