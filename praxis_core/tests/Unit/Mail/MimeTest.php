<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\Mime\Decoder;
use App\Support\Mail\Mime\Headers;
use App\Support\Mail\Mime\Text;
use App\Support\Mail\Sync\FolderRoles;
use App\Support\Mail\Sync\Threader;
use Tests\TestCase;

/** Headers, charsets and text as real mail sends them (English and French senders, Outlook, Gmail, cPanel). */
class MimeTest extends TestCase
{
    public function test_encoded_words(): void
    {
        $this->assertSame('Réunion du conseil', Headers::decode('=?UTF-8?Q?R=C3=A9union_du_conseil?='));
        $this->assertSame('Facture été', Headers::decode('=?UTF-8?B?RmFjdHVyZSDDqXTDqQ==?='));
        // A multibyte character split across two words, with folding whitespace between them
        $this->assertSame('Café', Headers::decode('=?UTF-8?B?Q2Fm?= =?UTF-8?B?w6k=?='));
        $this->assertSame('Grüße', Headers::decode('=?ISO-8859-1?Q?Gr=FC=DFe?='));
        $this->assertSame('Re: Proposal', Headers::decode('Re: Proposal'));
    }

    public function test_address_lists(): void
    {
        $list = Headers::addresses('"Okafor, Ada" <ada@client.com>, mireille@jbspraxis.com, =?UTF-8?Q?Ren=C3=A9e?= <renee@x.fr>, team: a@t.co, b@t.co;, (Ops) ops@y.ng');

        $this->assertSame([
            ['name' => 'Okafor, Ada', 'email' => 'ada@client.com'],
            ['name' => null, 'email' => 'mireille@jbspraxis.com'],
            ['name' => 'Renée', 'email' => 'renee@x.fr'],
            ['name' => null, 'email' => 'a@t.co'],
            ['name' => null, 'email' => 'b@t.co'],
            ['name' => 'Ops', 'email' => 'ops@y.ng'],
        ], $list);
        $this->assertSame([], Headers::addresses('undisclosed-recipients:;'));
        $this->assertSame([], Headers::addresses(null));
    }

    public function test_headers_unfold_and_ids(): void
    {
        $h = Headers::parse("Subject: A long\r\n subject line\r\nReferences: <a@x>\r\n\t<b@y>\r\nMessage-ID: <c@z>\r\n");
        $this->assertSame('A long subject line', Headers::first($h, 'subject'));
        $this->assertSame(['a@x', 'b@y'], Headers::messageIds($h['references'][0]));
        $this->assertSame(['c@z'], Headers::messageIds($h['message-id'][0]));
    }

    public function test_dates(): void
    {
        $this->assertSame('2026-10-30 08:51:00', Headers::date('Fri, 30 Oct 2026 09:51:00 +0100 (WAT)')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-30 08:51:00', Headers::date('30-Oct-2026 09:51:00 +0100')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-01 02:44:25', Headers::date(' 1-Jul-2026 02:44:25 +0000')->format('Y-m-d H:i:s'));
        $this->assertNull(Headers::date('not a date'));
    }

    public function test_charsets_never_break(): void
    {
        $this->assertSame('déjà vu', Decoder::decode(quoted_printable_encode(mb_convert_encoding('déjà vu', 'ISO-8859-1', 'UTF-8')), 'quoted-printable', 'iso-8859-1'));
        $this->assertSame('naïve', Decoder::decode(base64_encode('naïve'), 'base64', 'utf-8'));
        // Labelled UTF-8 but really Windows-1252
        $this->assertSame('€5 café', Decoder::toUtf8("\x805 caf\xe9", 'utf-8'));
        // Unknown charset: still valid UTF-8
        $this->assertTrue(mb_check_encoding(Decoder::toUtf8("abc\xff", 'x-unknown-charset'), 'UTF-8'));
    }

    public function test_text_and_snippets(): void
    {
        $text = Text::fromHtml('<html><head><style>p{}</style></head><body><p>Hello&nbsp;Ada,</p><p>See <b>below</b>.</p><ul><li>One</li></ul></body></html>');
        $this->assertSame("Hello Ada,\nSee below.\n\n• One", $text);

        $snippet = Text::snippet("Thanks, all good.\n\nOn Fri, 30 Oct 2026 at 09:51, Ada <ada@x.com> wrote:\n> earlier text");
        $this->assertSame('Thanks, all good.', $snippet);
        $this->assertSame('Merci.', Text::snippet("Merci.\nLe ven. 30 oct. 2026 à 09:51, Ada <ada@x.com> a écrit :\n> avant"));
    }

    public function test_subject_keys_for_threads(): void
    {
        $this->assertSame('proposal for q4', Threader::subjectKey('RE: Fwd: TR: Proposal for   Q4'));
        $this->assertSame('budget', Threader::subjectKey('Re[2]: AW: Budget'));
        $this->assertTrue(Threader::isReplySubject('Re: Budget'));
        $this->assertFalse(Threader::isReplySubject('Regarding budget'));
    }

    public function test_folder_roles_on_cpanel_dovecot(): void
    {
        $f = fn ($path, $attrs = []) => ['path' => $path, 'name' => substr($path, (int) strrpos($path, '.') + (str_contains($path, '.') ? 1 : 0)), 'delimiter' => '.', 'attributes' => $attrs];
        $roles = FolderRoles::assign([
            $f('INBOX'), $f('INBOX.Sent'), $f('INBOX.Drafts'), $f('INBOX.spam'), $f('INBOX.Trash'), $f('INBOX.Archive'), $f('INBOX.Clients'),
        ]);

        $this->assertSame(['INBOX' => 'inbox', 'INBOX.Sent' => 'sent', 'INBOX.Drafts' => 'drafts', 'INBOX.spam' => 'spam', 'INBOX.Trash' => 'trash', 'INBOX.Archive' => 'archive', 'INBOX.Clients' => 'other'], $roles);

        // Special-use flags win over names
        $roles = FolderRoles::assign([$f('INBOX'), $f('INBOX.Sent'), $f('INBOX.Sent Items', ['\\Sent'])]);
        $this->assertSame('sent', $roles['INBOX.Sent Items']);
        $this->assertSame('other', $roles['INBOX.Sent']);
    }
}
