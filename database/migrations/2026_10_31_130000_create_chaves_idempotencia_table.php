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
        Schema::create('chaves_idempotencia', function (Blueprint $table) {
            $table->id();
            // O índice único é o que impede dois pedidos com a mesma chave de executarem juntos.
            $table->string('chave', 255)->unique();
            $table->char('impressao', 64);
            $table->unsignedSmallInteger('status_http')->nullable();
            $table->longText('corpo')->nullable();
            $table->json('cabecalhos')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->timestamp('expira_em')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chaves_idempotencia');
    }
};
