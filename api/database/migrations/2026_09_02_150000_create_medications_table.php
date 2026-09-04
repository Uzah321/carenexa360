<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('strength')->nullable();
            $table->string('form')->nullable();
            $table->string('dose')->nullable();
            $table->string('route')->nullable();
            $table->string('frequency')->nullable();
            $table->jsonb('schedule')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('prescriber')->nullable();
            $table->string('pharmacy')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_prn')->default(false);
            $table->text('prn_instructions')->nullable();
            $table->boolean('is_controlled_drug')->default(false);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
