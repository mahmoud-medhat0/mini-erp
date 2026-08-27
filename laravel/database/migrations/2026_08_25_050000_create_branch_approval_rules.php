<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_approval_rule', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('document_type', 60);
            $table->string('branch_match', 30)->default('document');
            $table->uuid('branch_id')->nullable();
            $table->string('required_permission', 120)->default('approvals.override');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branch')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['document_type', 'branch_match', 'is_active'], 'branch_approval_rule_lookup_index');
            $table->index(['branch_id', 'is_active'], 'branch_approval_rule_branch_index');
        });

        DB::statement('CREATE UNIQUE INDEX branch_approval_rule_global_unique ON branch_approval_rule(document_type, branch_match) WHERE branch_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX branch_approval_rule_branch_unique ON branch_approval_rule(document_type, branch_match, branch_id) WHERE branch_id IS NOT NULL');

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS branch_approval_rule_branch_unique');
        DB::statement('DROP INDEX IF EXISTS branch_approval_rule_global_unique');
        Schema::dropIfExists('branch_approval_rule');
    }

    private function addPostgresConstraints(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE branch_approval_rule ADD CONSTRAINT branch_approval_rule_document_type_check CHECK (document_type IN ('stock_transfer', 'stock_count', 'stock_adjustment'))");
        DB::statement("ALTER TABLE branch_approval_rule ADD CONSTRAINT branch_approval_rule_branch_match_check CHECK (branch_match IN ('document', 'source', 'destination', 'either'))");
    }
};
