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
        Schema::create('erp_simulado_tokens', function (Blueprint $table) {
            $table->id();
            // O simulador guarda só o hash: nem o "outro lado" mantém o token legível.
            $table->char('hash', 64)->unique();
            $table->timestamp('expira_em');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_simulado_tokens');
    }
};
