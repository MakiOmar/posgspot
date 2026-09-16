<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_support_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('contact_id')->nullable();
            $table->uuid('guest_token')->nullable();
            $table->string('locale', 8)->default('en');
            $table->string('status', 20)->default('active');
            $table->string('title')->nullable();
            $table->unsignedBigInteger('escalation_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('set null');
            $table->index(['business_id', 'contact_id', 'last_message_at'], 'ssc_biz_contact_last_idx');
            $table->index(['business_id', 'guest_token'], 'ssc_biz_guest_idx');
            $table->index('escalation_id');
        });

        Schema::create('storefront_support_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('conversation_id');
            $table->string('role', 20);
            $table->text('content');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('storefront_support_conversations')
                ->onDelete('cascade');
            $table->index(['conversation_id', 'id'], 'ssm_conv_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_support_messages');
        Schema::dropIfExists('storefront_support_conversations');
    }
};
