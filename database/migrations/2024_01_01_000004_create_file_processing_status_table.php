<?php

declare(strict_types=1);

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
        Schema::create('file_processing_status', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_id')->unique()->index();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('processed_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->string('processing_type', 50)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100)->nullable();
            $table->integer('progress')->default(0);
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['processing_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_processing_status');
    }
};