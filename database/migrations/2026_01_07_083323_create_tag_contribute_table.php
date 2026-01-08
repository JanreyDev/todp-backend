<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribute_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribute_id')->constrained()->onDelete('cascade'); // Add this
            $table->foreignId('tag_id')->constrained()->onDelete('cascade'); // Add this
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribute_tag');
    }
};