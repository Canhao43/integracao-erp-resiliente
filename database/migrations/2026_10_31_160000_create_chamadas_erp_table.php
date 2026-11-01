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
        // Auditoria: só metadados da chamada. Sem corpo, sem query string, sem token.
        Schema::create('chamadas_erp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiario_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metodo', 10);
            $table->string('caminho', 100);
            $table->unsignedSmallInteger('status_http')->nullable();
            $table->unsignedInteger('duracao_ms');
            $table->string('erro', 300)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chamadas_erp');
    }
};
