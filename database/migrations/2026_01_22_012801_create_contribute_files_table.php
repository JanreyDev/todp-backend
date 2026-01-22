<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates a table to store multiple files per contribution
     */
    public function up(): void
    {
        Schema::create('contribute_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribute_id')->constrained('contributes')->onDelete('cascade');
            $table->string('file_path'); // Path to the uploaded file
            $table->string('original_name'); // Original filename
            $table->string('file_type', 10); // csv, xlsx, xls
            $table->unsignedBigInteger('file_size'); // File size in bytes
            $table->timestamps();
            
            // Index for faster queries
            $table->index('contribute_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contribute_files');
    }
};