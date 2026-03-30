<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comments', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('document_comments')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['document_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
