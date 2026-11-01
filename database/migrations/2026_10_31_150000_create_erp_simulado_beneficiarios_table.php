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
        Schema::create('erp_simulado_beneficiarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            // De propósito SEM unique: o ERP aceita o mesmo CPF duas vezes. É o problema que a integração resolve.
            $table->char('cpf', 11)->index();
            $table->date('data_nascimento');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_simulado_beneficiarios');
    }
};
