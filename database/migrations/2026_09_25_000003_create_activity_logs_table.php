<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('activity_logs',function(Blueprint $t){$t->uuid('id')->primary();$t->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();$t->string('invoice_no')->index();$t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();$t->string('actor_username')->nullable();$t->string('actor_name')->nullable();$t->string('role',20)->nullable();$t->string('from_status',60)->nullable();$t->string('to_status',60)->nullable()->index();$t->decimal('duration_hours',12,2)->default(0);$t->text('note')->nullable();$t->timestamps();$t->index(['invoice_id','created_at']);}); } public function down(): void { Schema::dropIfExists('activity_logs'); } };
