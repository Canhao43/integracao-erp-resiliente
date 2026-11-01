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
        Schema::table('beneficiarios', function (Blueprint $table) {
            $table->string('status_erp', 20)->default('pendente')->index();
            $table->string('codigo_erp', 20)->nullable();
            $table->text('erro_erp')->nullable();
            $table->timestamp('enviado_ao_erp_em')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beneficiarios', function (Blueprint $table) {
            $table->dropIndex(['status_erp']);
            $table->dropColumn(['status_erp', 'codigo_erp', 'erro_erp', 'enviado_ao_erp_em']);
        });
    }
};
