<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The conversation between a client and its delivery team, one per project (docs/CHAT.md, 13 Nov 2026).
 *
 * - chat_messages: what the client and JBS Praxis write, in order (an auto-increment id: polling asks "after id N"
 *   and read receipts keep "read up to id N"). kind = text | issue (the card of a QA ticket) | event (a ticket's
 *   status changed: investigating, resolved, fix approved, task approved). is_internal: a note for JBS Praxis only.
 * - chat_attachments: photos, files and voice notes of a message, on the private disk (chat/<project id>/…).
 * - chat_readers: each person who opened the chat: read up to, last seen (presence), typing, last unread email.
 *
 * Existing QA tickets become issue cards, their comments replies to the card and their files attachments of the
 * card, all marked `imported` (never counted as unread, never emailed). qa_comments and qa_attachments stay as
 * they are (nothing reads them any more); qa_tickets stays the record of an issue's status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('project_id', 36);
            $table->string('author_type', 10)->comment('client | staff');
            $table->char('author_id', 36)->nullable();
            $table->string('kind', 10)->default('text')->comment('text | issue | event');
            $table->text('body')->nullable();
            $table->string('event', 30)->nullable()->comment('kind event: investigating | resolved | fix_approved | task_approved');
            $table->char('qa_ticket_id', 36)->nullable();
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->boolean('is_internal')->default(false)->comment('A note for JBS Praxis only, never shown to the client');
            $table->boolean('imported')->default(false)->comment('Copied from qa_tickets / qa_comments when the chat arrived');
            $table->char('legacy_id', 36)->nullable()->comment('The qa_comments row it was copied from');
            // Shown in (created_at, id) order: imported replies were written after the cards of later tickets
            $table->timestamp('created_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['project_id', 'id']);
            $table->index('qa_ticket_id');
            $table->index('created_at');
        });

        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_id');
            $table->char('project_id', 36);
            $table->string('kind', 10)->comment('image | file | voice');
            $table->string('file_name', 255);
            $table->string('file_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedInteger('duration_ms')->nullable()->comment('Voice notes');
            $table->timestamp('created_at')->nullable();
            $table->index('message_id');
            $table->index('project_id');
        });

        Schema::create('chat_readers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('project_id', 36);
            $table->string('party', 10)->comment('client | staff');
            $table->char('person_id', 36);
            $table->unsignedBigInteger('last_read_id')->default(0);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('seen_at')->nullable()->comment('Last check-in with the chat open');
            $table->timestamp('typing_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->unsignedBigInteger('emailed_up_to')->default(0);
            $table->unique(['project_id', 'party', 'person_id']);
            $table->index(['party', 'person_id']);
        });

        $this->importTickets();
    }

    /** Every QA ticket as an issue card, its comments as replies, its files on the card (idempotent per ticket). */
    private function importTickets(): void
    {
        if (! Schema::hasTable('qa_tickets')) {
            return;
        }
        $hasCommentType = Schema::hasColumn('qa_comments', 'author_type');
        $hasTicketType = Schema::hasColumn('qa_tickets', 'submitted_by_type');

        DB::table('qa_tickets')->orderBy('created_at')->orderBy('id')->chunk(200, function ($tickets) use ($hasCommentType, $hasTicketType) {
            foreach ($tickets as $ticket) {
                $created = $ticket->created_at ?? now();
                $card = DB::table('chat_messages')->insertGetId([
                    'project_id' => $ticket->project_id,
                    'author_type' => $hasTicketType ? ($ticket->submitted_by_type ?? 'client') : 'client', // corrected by 2026_11_13_400001 where a staff member raised it
                    'author_id' => $ticket->submitted_by,
                    'kind' => 'issue',
                    'body' => $ticket->description,
                    'qa_ticket_id' => $ticket->id,
                    'imported' => 1,
                    'created_at' => $created,
                    'deleted_at' => $ticket->deleted_at,
                ]);

                foreach (DB::table('qa_attachments')->where('ticket_id', $ticket->id)->orderBy('created_at')->get() as $file) {
                    DB::table('chat_attachments')->insert([
                        'message_id' => $card,
                        'project_id' => $ticket->project_id,
                        'kind' => str_starts_with((string) $file->mime_type, 'image/') ? 'image' : 'file',
                        'file_name' => $file->file_name,
                        'file_path' => $file->file_path,
                        'mime_type' => $file->mime_type,
                        'size_bytes' => max(0, (int) $file->size_bytes),
                        'created_at' => $file->created_at ?? $created,
                    ]);
                }

                foreach (DB::table('qa_comments')->where('ticket_id', $ticket->id)->orderBy('created_at')->get() as $comment) {
                    DB::table('chat_messages')->insert([
                        'project_id' => $ticket->project_id,
                        'author_type' => $hasCommentType ? ($comment->author_type ?? 'staff') : 'staff',
                        'author_id' => $comment->user_id,
                        'kind' => 'text',
                        'body' => $comment->comment_text,
                        'qa_ticket_id' => $ticket->id,
                        'reply_to_id' => $card,
                        'is_internal' => (int) $comment->is_internal_only,
                        'imported' => 1,
                        'legacy_id' => $comment->id,
                        'created_at' => $comment->created_at ?? $created,
                        'deleted_at' => $ticket->deleted_at,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_readers');
        Schema::dropIfExists('chat_attachments');
        Schema::dropIfExists('chat_messages');
    }
};
