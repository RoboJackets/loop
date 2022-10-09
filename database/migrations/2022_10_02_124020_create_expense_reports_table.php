<?php

declare(strict_types=1);

use App\Models\FiscalYear;
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
        Schema::create('expense_reports', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedMediumInteger('workday_instance_id')->unique();
            $table->string('workday_expense_report_id')->unique();
            $table->longText('memo');
            $table->date('created_date');
            $table->date('approval_date')->nullable();
            $table->unsignedSmallInteger('created_by_worker_id');
            $table->foreignIdFor(FiscalYear::class)->constrained();
            $table->string('status')->nullable();
            $table->unsignedSmallInteger('external_committee_member_id');
            $table->decimal('amount', 8, 2);
            $table->unsignedSmallInteger('expense_payment_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_reports');
    }
};
