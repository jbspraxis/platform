<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HQ Mail (docs/MAIL.md, "Tables"). Every table the four Mail PRs use is created here (PR 1), so the
 * parallel PRs build on one fixed schema. A PR that needs more adds its own migration in its reserved
 * range (docs/MAIL.md, "Files and owners").
 *
 * Mail stays on the mail server: these tables hold a copy of the last 12 months (headers and text),
 * the people who share a mailbox, and HQ's own teamwork on conversations. Removing a mailbox deletes
 * its copy here (cascades), never the mail on the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->enum('kind', ['personal', 'shared']);
            $table->string('email', 190)->unique();
            $table->string('display_name', 120);
            // personal: the person whose mailbox it is; shared: who added it
            $table->char('owner_user_id', 36)->nullable()->index();
            $table->string('username', 190);
            $table->text('secret'); // the password, encrypted with APP_KEY (App\Support\Mail\MailCredentials)
            $table->string('imap_host', 190);
            $table->unsignedSmallInteger('imap_port');
            $table->enum('imap_encryption', ['ssl', 'starttls', 'none']);
            $table->string('smtp_host', 190);
            $table->unsignedSmallInteger('smtp_port');
            $table->enum('smtp_encryption', ['ssl', 'starttls', 'none']);
            $table->enum('status', ['active', 'needs_reconnect', 'paused'])->default('active');
            $table->string('last_error', 500)->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamp('folders_synced_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_account_members', function (Blueprint $table) {
            $table->id();
            $table->char('account_id', 36);
            $table->char('user_id', 36)->index();
            $table->enum('level', ['read', 'send', 'manage']);
            $table->char('added_by', 36)->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
            $table->foreign('account_id')->references('id')->on('mail_accounts')->cascadeOnDelete();
        });

        Schema::create('mail_folders', function (Blueprint $table) {
            $table->id();
            $table->char('account_id', 36);
            $table->string('path', 255);        // the server's name (modified UTF-7), used in IMAP commands
            $table->string('name', 255);        // readable name (UTF-8), last part of the path
            $table->string('delimiter', 4)->nullable();
            $table->enum('role', ['inbox', 'sent', 'drafts', 'archive', 'spam', 'trash', 'other'])->default('other');
            $table->json('attributes')->nullable();
            $table->boolean('selectable')->default(true);
            $table->unsignedBigInteger('uidvalidity')->nullable();
            $table->unsignedBigInteger('uidnext')->nullable();
            $table->unsignedBigInteger('highestmodseq')->nullable();
            $table->unsignedInteger('unread_count')->default(0);   // in HQ's copy (last 12 months)
            $table->unsignedInteger('total_count')->default(0);    // in HQ's copy
            $table->unsignedInteger('server_unseen')->nullable();  // on the server, all dates
            $table->unsignedInteger('server_total')->nullable();
            $table->timestamp('initial_synced_at')->nullable();    // first full copy finished
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'path']);
            $table->index(['account_id', 'role']);
            $table->foreign('account_id')->references('id')->on('mail_accounts')->cascadeOnDelete();
        });

        Schema::create('mail_threads', function (Blueprint $table) {
            $table->id();
            $table->char('account_id', 36);
            $table->string('subject', 500)->nullable();
            $table->string('subject_key', 191)->nullable(); // lower case, no Re:/Fwd: (App\Support\Mail\Sync\Threader)
            $table->string('snippet', 300)->nullable();
            $table->json('participants')->nullable();       // [{name, email}], first sender first
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('unread_count')->default(0);
            $table->boolean('has_attachments')->default(false);
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable(); // last message not sent from this mailbox
            // Teamwork (PR 4)
            $table->enum('status', ['open', 'done'])->default('open');
            $table->char('assigned_to', 36)->nullable()->index();
            $table->char('assigned_by', 36)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->char('done_by', 36)->nullable();
            $table->timestamp('done_at')->nullable();
            // Praxis AI summary (PR 4), kept until a new message arrives
            $table->json('ai_summary')->nullable();
            $table->timestamp('ai_summary_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'last_message_at']);
            $table->index(['account_id', 'subject_key']);
            $table->foreign('account_id')->references('id')->on('mail_accounts')->cascadeOnDelete();
        });

        Schema::create('mail_messages', function (Blueprint $table) {
            $table->id();
            $table->char('account_id', 36);
            $table->unsignedBigInteger('folder_id');
            $table->unsignedBigInteger('thread_id')->nullable();
            $table->unsignedBigInteger('uid');
            $table->string('message_id', 255)->nullable();
            $table->char('message_id_hash', 40)->nullable();
            $table->string('in_reply_to', 255)->nullable();
            $table->text('references')->nullable();
            $table->string('subject', 500)->nullable();
            $table->string('from_name', 255)->nullable();
            $table->string('from_email', 255)->nullable();
            $table->json('to')->nullable();       // [{name, email}]
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->json('reply_to')->nullable();
            $table->text('to_text')->nullable();  // names and addresses of To and Cc, for search
            $table->timestamp('sent_at')->nullable();      // Date header
            $table->timestamp('received_at')->nullable();  // the server's arrival time (INTERNALDATE)
            $table->unsignedInteger('size')->default(0);
            $table->boolean('is_seen')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_answered')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->boolean('is_outbound')->default(false); // sent from this mailbox (From is the mailbox)
            $table->string('snippet', 300)->nullable();
            $table->mediumText('body_text')->nullable();
            $table->mediumText('body_html')->nullable();
            // pending: not read yet; cached: in body_text/body_html; remote: too large, read from the server when opened; error
            $table->enum('body_state', ['pending', 'cached', 'remote', 'error'])->default('pending');
            $table->json('parts')->nullable();    // where the text and HTML are on the server (section, encoding, charset)
            $table->string('list_unsubscribe', 500)->nullable();
            $table->char('seen_by', 36)->nullable();  // first person to open it in HQ
            $table->timestamp('seen_at')->nullable();
            $table->char('sent_by', 36)->nullable();  // the person who sent it from HQ (PR 3)
            $table->timestamps();
            $table->unique(['folder_id', 'uid']);
            $table->index(['thread_id', 'received_at']);
            $table->index(['account_id', 'message_id_hash']);
            $table->index(['folder_id', 'received_at']);
            $table->foreign('account_id')->references('id')->on('mail_accounts')->cascadeOnDelete();
            $table->foreign('folder_id')->references('id')->on('mail_folders')->cascadeOnDelete();
            $table->foreign('thread_id')->references('id')->on('mail_threads')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('mail_messages', function (Blueprint $table) {
                $table->fullText(['subject', 'from_name', 'from_email', 'to_text', 'body_text'], 'mail_messages_search');
            });
        }

        Schema::create('mail_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id');
            $table->string('section', 32);        // IMAP body part, e.g. "2" or "1.2"
            $table->string('filename', 255);
            $table->string('mime', 127);
            $table->string('encoding', 32)->nullable();
            $table->unsignedInteger('size')->default(0);  // decoded size, estimated
            $table->string('content_id', 255)->nullable();
            $table->boolean('is_inline')->default(false);
            $table->foreign('message_id')->references('id')->on('mail_messages')->cascadeOnDelete();
        });

        // Outgoing mail and drafts written in HQ (PR 3)
        Schema::create('mail_outbox', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('account_id', 36);
            $table->char('user_id', 36)->index();
            $table->enum('kind', ['new', 'reply', 'reply_all', 'forward'])->default('new');
            $table->enum('status', ['draft', 'queued', 'sending', 'sent', 'failed', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('thread_id')->nullable();
            $table->unsignedBigInteger('reply_to_message_id')->nullable(); // mail_messages.id answered or forwarded
            $table->json('payload');
            $table->string('message_id', 255)->nullable();
            $table->char('message_id_hash', 40)->nullable()->index();
            $table->timestamp('send_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();
            $table->index(['status', 'send_at']);
            $table->foreign('account_id')->references('id')->on('mail_accounts')->cascadeOnDelete();
        });

        // Teamwork on shared mailboxes (PR 4)
        Schema::create('mail_thread_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->char('user_id', 36);
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['thread_id', 'created_at']);
            $table->foreign('thread_id')->references('id')->on('mail_threads')->cascadeOnDelete();
        });

        Schema::create('mail_thread_activity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id');
            $table->char('user_id', 36)->nullable();
            $table->string('type', 40);  // assigned, unassigned, done, reopened, note, sent, …
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['thread_id', 'created_at']);
            $table->foreign('thread_id')->references('id')->on('mail_threads')->cascadeOnDelete();
        });

        Schema::create('mail_presence', function (Blueprint $table) {
            $table->unsignedBigInteger('thread_id');
            $table->char('user_id', 36);
            $table->enum('state', ['viewing', 'replying']);
            $table->timestamp('expires_at');
            $table->primary(['thread_id', 'user_id']);
            $table->foreign('thread_id')->references('id')->on('mail_threads')->cascadeOnDelete();
        });

        Schema::create('mail_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->json('value')->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['mail_settings', 'mail_presence', 'mail_thread_activity', 'mail_thread_notes', 'mail_outbox',
            'mail_attachments', 'mail_messages', 'mail_threads', 'mail_folders', 'mail_account_members', 'mail_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
