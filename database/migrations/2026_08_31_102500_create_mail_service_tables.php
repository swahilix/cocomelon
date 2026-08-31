<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('mail_accounts')) {
            Schema::create('mail_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email', 191)->unique();
                $table->string('provider', 80)->nullable();
                $table->string('imap_host');
                $table->unsignedSmallInteger('imap_port')->default(993);
                $table->string('imap_encryption', 20)->default('ssl');
                $table->string('imap_username', 191);
                $table->text('imap_password_encrypted');
                $table->string('smtp_host');
                $table->unsignedSmallInteger('smtp_port')->default(465);
                $table->string('smtp_encryption', 20)->default('ssl');
                $table->string('smtp_username', 191);
                $table->text('smtp_password_encrypted');
                $table->string('from_name')->nullable();
                $table->string('reply_to', 191)->nullable();
                $table->longText('signature_html')->nullable();
                $table->boolean('sync_enabled')->default(true)->index();
                $table->boolean('send_enabled')->default(true)->index();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('last_connection_at')->nullable();
                $table->text('last_error')->nullable();
                $table->string('status', 30)->default('active')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mail_account_user')) {
            Schema::create('mail_account_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->boolean('can_read')->default(true);
                $table->boolean('can_send')->default(true);
                $table->boolean('can_delete')->default(false);
                $table->boolean('can_manage')->default(false);
                $table->timestamps();

                $table->unique(['mail_account_id', 'user_id']);
                $table->index(['user_id', 'can_read']);
            });
        }

        if (! Schema::hasTable('mail_folders')) {
            Schema::create('mail_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
                $table->string('remote_name');
                $table->string('display_name');
                $table->string('type', 30)->default('custom')->index();
                $table->string('delimiter', 10)->nullable();
                $table->unsignedBigInteger('uid_validity')->nullable();
                $table->unsignedBigInteger('last_uid')->default(0);
                $table->text('sync_token')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['mail_account_id', 'remote_name']);
                $table->index(['mail_account_id', 'type']);
            });
        }

        if (! Schema::hasTable('mail_messages')) {
            Schema::create('mail_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
                $table->foreignId('mail_folder_id')->constrained('mail_folders')->cascadeOnDelete();
                $table->unsignedBigInteger('remote_uid')->nullable();
                $table->string('message_id')->nullable()->index();
                $table->string('in_reply_to')->nullable()->index();
                $table->string('thread_key', 191)->nullable()->index();
                $table->string('from_name')->nullable();
                $table->string('from_email', 191)->nullable()->index();
                $table->string('reply_to_email', 191)->nullable();
                $table->string('subject', 191)->nullable()->index();
                $table->text('preview_text')->nullable();
                $table->longText('body_text')->nullable();
                $table->longText('body_html')->nullable();
                $table->timestamp('received_at')->nullable()->index();
                $table->timestamp('sent_at')->nullable()->index();
                $table->unsignedBigInteger('size')->default(0);
                $table->boolean('has_attachments')->default(false)->index();
                $table->boolean('is_read')->default(false)->index();
                $table->boolean('is_starred')->default(false)->index();
                $table->boolean('is_important')->default(false)->index();
                $table->boolean('is_draft')->default(false)->index();
                $table->boolean('is_deleted')->default(false)->index();
                $table->json('headers_json')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['mail_account_id', 'mail_folder_id', 'remote_uid'],
                    'mail_messages_account_folder_uid_unique'
                );
                $table->index(['mail_account_id', 'mail_folder_id', 'received_at'], 'mail_messages_folder_received_index');
                $table->index(['mail_account_id', 'is_read', 'received_at'], 'mail_messages_unread_index');
                $table->index(['mail_account_id', 'thread_key', 'received_at'], 'mail_messages_thread_index');
            });
        }

        if (! Schema::hasTable('mail_recipients')) {
            Schema::create('mail_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_message_id')->constrained('mail_messages')->cascadeOnDelete();
                $table->string('type', 10)->index();
                $table->string('name')->nullable();
                $table->string('email', 191)->index();
                $table->timestamps();

                $table->index(['mail_message_id', 'type']);
            });
        }

        if (! Schema::hasTable('mail_attachments')) {
            Schema::create('mail_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_message_id')->constrained('mail_messages')->cascadeOnDelete();
                $table->string('remote_attachment_id')->nullable();
                $table->string('part_number', 100)->nullable();
                $table->string('original_name');
                $table->string('mime_type', 191)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('storage_disk', 50)->nullable();
                $table->text('storage_path')->nullable();
                $table->string('content_id')->nullable()->index();
                $table->boolean('is_inline')->default(false)->index();
                $table->timestamps();

                $table->index(['mail_message_id', 'is_inline']);
            });
        }

        if (! Schema::hasTable('mail_drafts')) {
            Schema::create('mail_drafts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('reply_to_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
                $table->foreignId('forward_of_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
                $table->string('subject', 191)->nullable();
                $table->longText('body_html')->nullable();
                $table->longText('body_text')->nullable();
                $table->json('to_json')->nullable();
                $table->json('cc_json')->nullable();
                $table->json('bcc_json')->nullable();
                $table->timestamp('scheduled_at')->nullable()->index();
                $table->timestamp('last_saved_at')->nullable()->index();
                $table->timestamps();

                $table->index(['mail_account_id', 'user_id', 'updated_at'], 'mail_drafts_account_user_updated_index');
            });
        }

        if (! Schema::hasTable('mail_outbox')) {
            Schema::create('mail_outbox', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('draft_id')->nullable()->constrained('mail_drafts')->nullOnDelete();
                $table->string('status', 30)->default('queued')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('available_at')->nullable()->index();
                $table->timestamp('processing_at')->nullable();
                $table->timestamp('sent_at')->nullable()->index();
                $table->timestamp('failed_at')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->string('smtp_message_id')->nullable()->index();
                $table->json('payload_json');
                $table->timestamps();

                $table->index(['mail_account_id', 'status', 'available_at'], 'mail_outbox_queue_index');
                $table->index(['user_id', 'status', 'created_at'], 'mail_outbox_user_status_index');
            });
        }

        if (! Schema::hasTable('mail_contacts')) {
            Schema::create('mail_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('mail_account_id')->nullable()->constrained('mail_accounts')->nullOnDelete();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('company')->nullable()->index();
                $table->string('job_title')->nullable();
                $table->string('email', 191)->index();
                $table->string('secondary_email', 191)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('secondary_phone', 50)->nullable();
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->text('avatar_path')->nullable();
                $table->string('source', 30)->default('manual')->index();
                $table->boolean('is_favorite')->default(false)->index();
                $table->timestamps();

                $table->index(['owner_user_id', 'is_favorite']);
                $table->index(['mail_account_id', 'email']);
                $table->index(['last_name', 'first_name']);
            });
        }

        if (! Schema::hasTable('mail_contact_groups')) {
            Schema::create('mail_contact_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['owner_user_id', 'name']);
            });
        }

        if (! Schema::hasTable('mail_contact_group_contact')) {
            Schema::create('mail_contact_group_contact', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mail_contact_group_id')->constrained('mail_contact_groups')->cascadeOnDelete();
                $table->foreignId('mail_contact_id')->constrained('mail_contacts')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(
                    ['mail_contact_group_id', 'mail_contact_id'],
                    'mail_contact_group_contact_unique'
                );
            });
        }

        if (! Schema::hasTable('mail_audit_logs')) {
            Schema::create('mail_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('mail_account_id')->nullable()->constrained('mail_accounts')->nullOnDelete();
                $table->foreignId('mail_message_id')->nullable()->constrained('mail_messages')->nullOnDelete();
                $table->string('action', 80)->index();
                $table->json('metadata_json')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['mail_account_id', 'created_at'], 'mail_audit_account_created_index');
                $table->index(['user_id', 'created_at'], 'mail_audit_user_created_index');
                $table->index(['mail_message_id', 'created_at'], 'mail_audit_message_created_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_audit_logs');
        Schema::dropIfExists('mail_contact_group_contact');
        Schema::dropIfExists('mail_contact_groups');
        Schema::dropIfExists('mail_contacts');
        Schema::dropIfExists('mail_outbox');
        Schema::dropIfExists('mail_drafts');
        Schema::dropIfExists('mail_attachments');
        Schema::dropIfExists('mail_recipients');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_folders');
        Schema::dropIfExists('mail_account_user');
        Schema::dropIfExists('mail_accounts');
    }
};
