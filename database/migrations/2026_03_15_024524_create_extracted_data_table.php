<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extracted_data', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('simulated');
            $table->longText('extracted_text')->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('document_id');
            $table->index(['tenant_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracted_data');
    }
};
