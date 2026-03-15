<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->uuid('message_id');
            $table->uuid('trace_id');
            $table->string('event');
            $table->string('consumer_name');
            $table->string('status_from')->nullable();
            $table->string('status_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'message_id', 'consumer_name']);
            $table->index('trace_id');
            $table->index(['tenant_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_events');
    }
};
