<?php

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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('order_comment_id')->nullable()->constrained('order_comments')->nullOnDelete();
            $table->string('kind')->default('file');
            $table->string('drive_file_id')->nullable()->unique();
            $table->string('drive_folder_id')->nullable();
            $table->text('external_url')->nullable();
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('source')->default('upload');
            $table->string('trello_attachment_id')->nullable()->unique();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploader_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'created_at']);
            $table->index(['client_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
