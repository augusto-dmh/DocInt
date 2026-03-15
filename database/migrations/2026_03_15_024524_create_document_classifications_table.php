<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('simulated');
            $table->string('type');
            $table->decimal('confidence', 5, 4)->unsigned()->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('document_id');
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_classifications');
    }
};
