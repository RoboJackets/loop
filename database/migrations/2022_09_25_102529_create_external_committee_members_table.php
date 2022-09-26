<?php

declare(strict_types=1);

use App\Models\User;
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
        Schema::create('external_committee_members', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('workday_instance_id')->unique();
            $table->string('workday_external_committee_member_id');
            $table->string('name');
            $table->boolean('active');
            $table->foreignIdFor(User::class)->nullable()->constrained();
            $table->timestamps();

            $table->unique('workday_external_committee_member_id', 'external_committee_members_workday_ecm_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_committee_members');
    }
};
