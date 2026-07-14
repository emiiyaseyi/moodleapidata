<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->unsignedBigInteger('moodle_user_id')->nullable();
            $table->string('fullname')->nullable();
            $table->string('department')->nullable();
            $table->date('join_date');
            $table->date('neo_exam_date')->nullable();
            $table->timestamp('neo_enrolled_at')->nullable();
            $table->timestamps();

            $table->index('department');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
