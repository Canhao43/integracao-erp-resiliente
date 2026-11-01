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
        Schema::create('beneficiarios', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 150);
            // Só dígitos, e único: do nosso lado a duplicata é barrada pelo banco,
            // mesmo que o ERP do outro lado não tenha essa garantia.
            $table->char('cpf', 11)->unique();
            $table->date('data_nascimento');
            $table->string('email', 150)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiarios');
    }
};
