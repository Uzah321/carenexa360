<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per item in a client's medical history — a condition, an
        // allergy, an operation, an admission… Which optional columns apply
        // depends on the category: see MedicalHistoryEntry::FIELDS.
        Schema::create('medical_history_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            // The condition, allergen, procedure, vaccine or reason for admission.
            $table->string('name');
            $table->string('status')->nullable();
            $table->string('severity')->nullable();
            $table->string('allergy_type')->nullable();
            $table->text('reaction')->nullable();
            // Family history: who in the family had it.
            $table->string('relationship')->nullable();
            // Diagnosed / identified / performed / admitted / given.
            $table->date('started_on')->nullable();
            // Resolved / discharged.
            $table->date('ended_on')->nullable();
            // Who or where: diagnosing clinician, hospital, vaccination site.
            $table->string('provider')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'service_user_id']);
        });

        DB::statement('ALTER TABLE medical_history_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE medical_history_entries FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON medical_history_entries
            USING (
                current_setting('app.current_tenant_id', true) IS NULL
                OR current_setting('app.current_tenant_id', true) = ''
                OR tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint
            )
        ");

        Schema::table('service_users', function (Blueprint $table) {
            // Confirmed as having none — distinct from allergies simply not
            // having been asked about yet.
            $table->boolean('no_known_allergies')->default(false)->after('disabilities');
            $table->string('smoking_status')->nullable()->after('no_known_allergies');
            $table->string('alcohol_intake')->nullable()->after('smoking_status');
            $table->string('resuscitation_status')->nullable()->after('alcohol_intake');
            $table->text('resuscitation_notes')->nullable()->after('resuscitation_status');
            $table->text('medical_history_summary')->nullable()->after('resuscitation_notes');
            $table->timestamp('medical_history_reviewed_at')->nullable()->after('medical_history_summary');
            $table->foreignId('medical_history_reviewed_by')->nullable()->after('medical_history_reviewed_at')
                ->constrained('users')->nullOnDelete();
        });

        $this->backfillFromSummaryTags();
    }

    /**
     * Clients already have allergies, diagnoses, conditions and disabilities
     * as plain tag lists. Those lists are now built from the history, so turn
     * each tag into an entry — nothing recorded so far is lost — and rewrite
     * the lists from those entries, as MedicalSummary would. Diagnoses and
     * medical conditions become one list of conditions, as the history keeps
     * them.
     */
    private function backfillFromSummaryTags(): void
    {
        $now = now();

        DB::table('service_users')
            ->select(['id', 'tenant_id', 'allergies', 'diagnoses', 'medical_conditions', 'disabilities'])
            ->orderBy('id')
            ->chunkById(200, function ($serviceUsers) use ($now) {
                foreach ($serviceUsers as $su) {
                    $tags = fn (?string $json) => array_values(array_filter(
                        array_map(fn ($tag) => trim((string) $tag), json_decode($json ?? '[]', true) ?: []),
                        fn ($tag) => $tag !== '',
                    ));
                    $unique = fn (array $names) => array_values(array_intersect_key(
                        $names,
                        array_unique(array_map('mb_strtolower', $names)),
                    ));

                    $conditions = $unique([...$tags($su->diagnoses), ...$tags($su->medical_conditions)]);
                    $byCategory = [
                        'allergy' => $unique($tags($su->allergies)),
                        'condition' => $conditions,
                        'disability' => $unique($tags($su->disabilities)),
                    ];

                    $rows = [];
                    foreach ($byCategory as $category => $names) {
                        foreach ($names as $name) {
                            $rows[] = [
                                'tenant_id' => $su->tenant_id,
                                'service_user_id' => $su->id,
                                'category' => $category,
                                'name' => mb_substr($name, 0, 255),
                                'status' => $category === 'disability' ? null : 'active',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    if ($rows) {
                        DB::table('medical_history_entries')->insert($rows);
                    }

                    DB::table('service_users')->where('id', $su->id)->update([
                        'allergies' => json_encode($byCategory['allergy']),
                        'diagnoses' => json_encode($conditions),
                        'medical_conditions' => json_encode($conditions),
                        'disabilities' => json_encode($byCategory['disability']),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('service_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medical_history_reviewed_by');
            $table->dropColumn([
                'no_known_allergies',
                'smoking_status',
                'alcohol_intake',
                'resuscitation_status',
                'resuscitation_notes',
                'medical_history_summary',
                'medical_history_reviewed_at',
            ]);
        });

        Schema::dropIfExists('medical_history_entries');
    }
};
