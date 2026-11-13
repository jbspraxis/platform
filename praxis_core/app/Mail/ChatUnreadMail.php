<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

/**
 * Messages in a project's conversation (docs/CHAT.md) that nobody on the other side read within an hour (owner,
 * 13 Nov 2026): to the client's accounts in their language, or to the project's delivery team in English. At most
 * one an hour per person and conversation (php artisan chat:email-unread, every five minutes).
 */
class ChatUnreadMail extends BrandedMail
{
    /**
     * @param  array<int, array{name: string, time: string, text: string}>  $lines  the latest messages, oldest first
     */
    public function __construct(
        public ?string $firstName,
        public bool $forClient,
        public string $client,
        public string $project,
        public array $lines,
        public int $count,
        public string $url,
    ) {
    }

    protected function subjectLine(): string
    {
        return $this->forClient
            ? trans_choice('emails.chat.client_subject', $this->count, ['count' => $this->count])
            : __('emails.chat.staff_subject', ['client' => $this->client]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.chat-unread');
    }

    public static function samples(): array
    {
        $lines = [
            ['name' => 'Adaeze O.', 'time' => '09:42 WAT', 'text' => 'The payment page shows the old price for VIP tickets. Can you check before the launch?'],
            ['name' => 'Adaeze O.', 'time' => '09:44 WAT', 'text' => 'Photo'],
        ];

        return [
            'client' => new static('Chidi', true, 'Fleet Logistics Ltd', 'Fleet tracking portal', [
                ['name' => 'Tom-Blake F.', 'time' => '10:05 WAT', 'text' => 'The VIP price is fixed on staging. Can you check it before we publish it?'],
            ], 1, 'https://portal.jbspraxis.com/?chat=sample'),
            'staff-en' => new static('Tom-Blake', false, 'Fleet Logistics Ltd', 'Fleet tracking portal', $lines, 2, 'https://hq.jbspraxis.com/operations/client-messages?project=sample'),
        ];
    }
}
