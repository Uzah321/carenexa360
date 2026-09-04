<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->decimal('regular_hours', 8, 2)->default(0);
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('generated_at')->nullable();

            $table->timestamps();

            $table->unique(['pay_period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
