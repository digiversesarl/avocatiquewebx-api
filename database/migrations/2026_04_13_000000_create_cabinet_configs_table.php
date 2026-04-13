<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabinet_configs', function (Blueprint $table) {
            $table->id();
            $table->string('firm_name_fr')->nullable();
            $table->string('firm_name_ar')->nullable();
            $table->string('firm_name_en')->nullable();
            $table->text('firm_address_fr')->nullable();
            $table->text('firm_address_ar')->nullable();
            $table->text('firm_address_en')->nullable();
            $table->string('city_fr')->nullable();
            $table->string('city_ar')->nullable();
            $table->string('city_en')->nullable();
            $table->string('firm_email')->nullable();
            $table->string('firm_phone')->nullable();
            $table->string('firm_fax')->nullable();
            $table->string('firm_site_web')->nullable();
            $table->string('firm_code')->nullable();
            $table->string('firm_barreau')->nullable();
            $table->string('firm_sms_number')->nullable();
            $table->string('firm_patente')->nullable();
            $table->string('firm_ice')->nullable();
            $table->string('firm_cnss')->nullable();
            $table->string('firm_banque')->nullable();
            $table->string('firm_compte_bancaire')->nullable();
            $table->string('firm_agence')->nullable();
            $table->string('firm_logo_url')->nullable();
            $table->string('firm_header_url')->nullable();
            $table->string('firm_footer_url')->nullable();
            $table->string('firm_signature_url')->nullable();
            $table->string('color_theme')->default('green');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_configs');
    }
};
