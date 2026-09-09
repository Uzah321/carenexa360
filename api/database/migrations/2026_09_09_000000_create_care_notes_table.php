<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_user_id')->constrained('service_users')->cascadeOnDelete();
            // Optional — set when a carer records the note from inside a visit,
            // so the note carries that context. Null for one recorded from the
            // service user's own page, outside any specific visit.
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('caption')->nullable();
            $table->string('audio_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'service_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_notes');
    }
};
