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
        Schema::table('attachments', static function (Blueprint $table): void {
            $table->unsignedSmallInteger('workday_instance_id')->unique()->nullable();
            $table->unsignedSmallInteger('workday_uploaded_by_worker_id')->nullable();
            $table->timestamp('workday_uploaded_at')->nullable();
            $table->string('workday_comment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', static function (Blueprint $table): void {
            $table->dropColumn([
                'workday_instance_id',
                'workday_uploaded_by_worker_id',
                'workday_uploaded_at',
                'workday_comment',
            ]);
        });
    }
};
