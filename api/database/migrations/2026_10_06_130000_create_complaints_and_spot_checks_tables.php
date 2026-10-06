<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('received_date');
            $table->string('complainant_name');
            $table->string('complainant_relationship')->nullable();
            $table->string('channel')->default('phone');
            $table->string('category');
            $table->string('severity')->default('medium');
            $table->text('description');
            $table->string('status')->default('received');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('acknowledged_date')->nullable();
            $table->date('response_due_date')->nullable();
            $table->string('outcome')->nullable();
            $table->text('findings')->nullable();
            $table->text('actions_taken')->nullable();
            $table->date('resolved_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('spot_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // The staff member observed, and who observed them.
            $table->foreignId('staff_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('check_date');
            // area => pass | fail | na — see SpotCheck::AREAS.
            $table->json('results');
            $table->string('outcome');
            $table->text('notes')->nullable();
            $table->text('actions_required')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'check_date']);
        });

        foreach (['complaints', 'spot_checks'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                USING (
                    current_setting('app.current_tenant_id', true) IS NULL
                    OR current_setting('app.current_tenant_id', true) = ''
                    OR tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint
                )
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_checks');
        Schema::dropIfExists('complaints');
    }
};
