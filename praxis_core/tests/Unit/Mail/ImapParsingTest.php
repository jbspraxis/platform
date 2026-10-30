<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\Imap\BodyStructure;
use App\Support\Mail\Imap\ImapClient;
use App\Support\Mail\Imap\ResponseParser;
use App\Support\Mail\Imap\Utf7;
use PHPUnit\Framework\TestCase;

/** IMAP responses as Dovecot sends them (cPanel's server), read into PHP values. */
class ImapParsingTest extends TestCase
{
    public function test_tokens_lists_strings_nil_and_literals(): void
    {
        $tokens = ResponseParser::tokens('12 FETCH (UID 345 FLAGS (\\Seen \\Answered) INTERNALDATE "30-Oct-2026 09:51:00 +0100" BODY[HEADER.FIELDS (FROM SUBJECT)] ' . "\0L0\0" . ' X NIL)', ["From: a@b.c\r\n\r\n"]);

        $this->assertSame('12', $tokens[0]);
        $this->assertSame('FETCH', $tokens[1]);
        $items = ResponseParser::fetchItems($tokens[2]);
        $this->assertSame('345', $items['UID']);
        $this->assertSame(['\\Seen', '\\Answered'], $items['FLAGS']);
        $this->assertSame('30-Oct-2026 09:51:00 +0100', $items['INTERNALDATE']);
        $this->assertSame("From: a@b.c\r\n\r\n", $items['HEADER']);
        $this->assertNull($items['X']);
    }

    public function test_quoted_strings_keep_escapes_and_partial_bodies_lose_their_origin(): void
    {
        $tokens = ResponseParser::tokens('(BODY[1]<0> "say \\"hi\\" \\\\ ok")');
        $items = ResponseParser::fetchItems($tokens[0]);

        $this->assertSame('say "hi" \\ ok', $items['BODY[1]']);
    }

    public function test_untagged_status_codes_search_and_capability(): void
    {
        $ok = ResponseParser::untagged('OK [UIDVALIDITY 3857529045] UIDs valid');
        $this->assertSame(['type' => 'OK', 'code' => 'UIDVALIDITY', 'args' => '3857529045', 'text' => 'UIDs valid'], $ok);

        $flags = ResponseParser::untagged('OK [PERMANENTFLAGS (\\Answered \\Flagged \\Deleted \\Seen \\Draft \\*)] Flags permitted.');
        $this->assertSame('PERMANENTFLAGS', $flags['code']);

        $this->assertSame(['type' => 'SEARCH', 'data' => [2, 84, 882]], ResponseParser::untagged('SEARCH 2 84 882 (MODSEQ 917162500)'));
        $this->assertSame(['type' => 'SEARCH', 'data' => []], ResponseParser::untagged('SEARCH'));
        $this->assertContains('CONDSTORE', ResponseParser::untagged('CAPABILITY IMAP4rev1 condstore MOVE')['data']);

        $exists = ResponseParser::untagged('23 EXISTS');
        $this->assertSame(['type' => 'EXISTS', 'num' => 23, 'data' => []], $exists);

        $list = ResponseParser::untagged('LIST (\\HasNoChildren \\Sent) "." INBOX.Sent');
        $this->assertSame([['\\HasNoChildren', '\\Sent'], '.', 'INBOX.Sent'], $list['data']);
    }

    public function test_uid_sets_are_compact(): void
    {
        $this->assertSame('1:3,7,9:10', ImapClient::uidSet([10, 3, 1, 2, 9, 7, 7]));
        $this->assertSame('5', ImapClient::uidSet([5]));
        $this->assertSame('', ImapClient::uidSet([]));
    }

    public function test_astring_quotes_simple_values_and_uses_literals_otherwise(): void
    {
        $this->assertSame('"INBOX.Sent"', ImapClient::astring('INBOX.Sent'));
        $this->assertSame('"pa\\"ss"', ImapClient::astring('pa"ss'));
        $this->assertSame(['literal' => "pässwörd"], ImapClient::astring('pässwörd'));
    }

    public function test_modified_utf7_folder_names(): void
    {
        $this->assertSame('Éléments envoyés', Utf7::decode('&AMk-l&AOk-ments envoy&AOk-s'));
        $this->assertSame('&AMk-l&AOk-ments envoy&AOk-s', Utf7::encode('Éléments envoyés'));
        $this->assertSame('R&D', Utf7::decode('R&-D'));
        $this->assertSame('R&-D', Utf7::encode('R&D'));
        $this->assertSame('台北', Utf7::decode(Utf7::encode('台北')));
    }

    public function test_bodystructure_alternative_with_attachment(): void
    {
        // A typical message from Outlook: text and HTML, plus a PDF
        $raw = '(((“TEXT” “PLAIN” (“CHARSET” “utf-8”) NIL NIL “QUOTED-PRINTABLE” 1520 40 NIL NIL NIL NIL)(“TEXT” “HTML” (“CHARSET” “utf-8”) NIL NIL “QUOTED-PRINTABLE” 9800 120 NIL NIL NIL NIL) “ALTERNATIVE” (“BOUNDARY” “b1”) NIL NIL NIL)(“APPLICATION” “PDF” (“NAME” “Proposal Q4.pdf”) NIL NIL “BASE64” 400000 NIL (“ATTACHMENT” (“FILENAME” “Proposal Q4.pdf” “SIZE” “292000”)) NIL NIL) “MIXED” (“BOUNDARY” “b0”) NIL NIL NIL)';
        $structure = ResponseParser::tokens(str_replace(['“', '”'], '"', $raw))[0];

        $s = BodyStructure::summarise($structure);

        $this->assertSame(['section' => '1.1', 'encoding' => 'quoted-printable', 'charset' => 'utf-8', 'size' => 1520], $s['text']);
        $this->assertSame('1.2', $s['html']['section']);
        $this->assertCount(1, $s['attachments']);
        $this->assertSame('2', $s['attachments'][0]['section']);
        $this->assertSame('Proposal Q4.pdf', $s['attachments'][0]['filename']);
        $this->assertSame('application/pdf', $s['attachments'][0]['mime']);
        $this->assertSame(300000, $s['attachments'][0]['size']);
        $this->assertFalse($s['attachments'][0]['is_inline']);
    }

    public function test_bodystructure_single_part_and_related_inline_picture(): void
    {
        $single = ResponseParser::tokens('("TEXT" "PLAIN" ("CHARSET" "iso-8859-1") NIL NIL "7BIT" 120 4 NIL NIL NIL NIL)')[0];
        $s = BodyStructure::summarise($single);
        $this->assertSame('1', $s['text']['section']);
        $this->assertSame('iso-8859-1', $s['text']['charset']);
        $this->assertNull($s['html']);

        $related = ResponseParser::tokens('(("TEXT" "HTML" ("CHARSET" "utf-8") NIL NIL "BASE64" 2000 30 NIL NIL NIL NIL)("IMAGE" "PNG" ("NAME" "logo.png") "<logo@sig>" NIL "BASE64" 4000 NIL ("INLINE" ("FILENAME" "logo.png")) NIL NIL) "RELATED" ("BOUNDARY" "r") NIL NIL NIL)')[0];
        $s = BodyStructure::summarise($related);
        $this->assertSame('1', $s['html']['section']);
        $this->assertSame('logo@sig', $s['attachments'][0]['content_id']);
        $this->assertTrue($s['attachments'][0]['is_inline']);
    }

    public function test_bodystructure_rfc2231_and_encoded_word_filenames(): void
    {
        $structure = ResponseParser::tokens('(("TEXT" "PLAIN" ("CHARSET" "utf-8") NIL NIL "7BIT" 10 1 NIL NIL NIL NIL)("APPLICATION" "OCTET-STREAM" NIL NIL NIL "BASE64" 100 NIL ("ATTACHMENT" ("FILENAME*" "utf-8\'\'Re%CC%81sume%CC%81%20%E2%82%AC.pdf")) NIL NIL)("APPLICATION" "PDF" ("NAME" "=?UTF-8?B?RmFjdHVyZSDDqXTDqS5wZGY=?=") NIL NIL "BASE64" 100 NIL NIL NIL NIL) "MIXED" NIL NIL NIL NIL)')[0];
        $s = BodyStructure::summarise($structure);

        $this->assertSame("Re\u{0301}sume\u{0301} €.pdf", $s['attachments'][0]['filename']);
        $this->assertSame('Facture été.pdf', $s['attachments'][1]['filename']);
    }

    public function test_attached_message_is_one_attachment(): void
    {
        $structure = ResponseParser::tokens('(("TEXT" "PLAIN" ("CHARSET" "utf-8") NIL NIL "7BIT" 10 1 NIL NIL NIL NIL)("MESSAGE" "RFC822" NIL NIL NIL "7BIT" 500 ("date" "subj" NIL NIL NIL NIL NIL NIL NIL NIL) ("TEXT" "PLAIN" ("CHARSET" "utf-8") NIL NIL "7BIT" 20 1 NIL NIL NIL NIL) 12 NIL NIL NIL NIL) "MIXED" NIL NIL NIL NIL)')[0];
        $s = BodyStructure::summarise($structure);

        $this->assertSame('1', $s['text']['section']);
        $this->assertCount(1, $s['attachments']);
        $this->assertSame('message/rfc822', $s['attachments'][0]['mime']);
        $this->assertSame('message-2.eml', $s['attachments'][0]['filename']);
    }
}
