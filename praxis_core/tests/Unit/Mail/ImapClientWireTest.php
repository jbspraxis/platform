<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\Imap\ImapClient;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\Imap\SocketImapSession;
use PHPUnit\Framework\TestCase;

/**
 * The IMAP client over a real socket, against a scripted server (tests/Support/Mail/scripted_imap_server.php)
 * that answers the way cPanel's Dovecot does: literals, LITERAL+, SASL-IR, CONDSTORE, APPENDUID, NO answers.
 */
class ImapClientWireTest extends TestCase
{
    private $process = null;

    private array $pipes = [];

    protected function tearDown(): void
    {
        if ($this->process) {
            $err = stream_get_contents($this->pipes[2]);
            foreach ($this->pipes as $p) {
                @fclose($p);
            }
            proc_close($this->process);
            if (str_contains((string) $err, 'MISMATCH')) {
                $this->fail('The scripted server got an unexpected command: ' . $err);
            }
        }
        parent::tearDown();
    }

    public function test_session_against_a_dovecot_like_server(): void
    {
        $header = "From: =?UTF-8?Q?Ren=C3=A9e?= <renee@x.fr>\r\nSubject: Hello\r\n\r\n";
        $port = $this->serve([
            'greeting' => '* OK [CAPABILITY IMAP4rev1 LITERAL+ SASL-IR AUTH=PLAIN] Dovecot ready.',
            'steps' => [
                ['expect' => '^AUTHENTICATE PLAIN ' . preg_quote(base64_encode("\0me@jbspraxis.com\0s3cret"), '#') . '$',
                    'reply' => ['TAG OK [CAPABILITY IMAP4rev1 LITERAL+ SASL-IR ENABLE CONDSTORE MOVE UIDPLUS SPECIAL-USE] Logged in']],
                ['expect' => '^ENABLE CONDSTORE$', 'reply' => ['* ENABLED CONDSTORE', 'TAG OK Enabled.']],
                ['expect' => '^LIST "" "\*" RETURN \(SPECIAL-USE\)$', 'reply' => [
                    '* LIST (\HasChildren) "." INBOX',
                    '* LIST (\HasNoChildren \Sent) "." INBOX.Sent',
                    '* LIST (\HasNoChildren) "." "INBOX.&AMk-l&AOk-ments"',
                    "* LIST (\\HasNoChildren \\Trash) \".\" {11}\r\nINBOX.Trash\r\n",
                    'TAG OK List completed.',
                ]],
                ['expect' => '^EXAMINE "INBOX" \(CONDSTORE\)$', 'reply' => [
                    '* FLAGS (\Answered \Flagged \Deleted \Seen \Draft)',
                    '* OK [PERMANENTFLAGS ()] Read-only mailbox.',
                    '* 3 EXISTS', '* 0 RECENT',
                    '* OK [UIDVALIDITY 1700000000] UIDs valid',
                    '* OK [UIDNEXT 9] Predicted next UID',
                    '* OK [HIGHESTMODSEQ 55] Highest',
                    'TAG OK [READ-ONLY] Examine completed.',
                ]],
                ['expect' => '^UID SEARCH SINCE 1-Oct-2025$', 'reply' => ['* SEARCH 5 7 8', 'TAG OK Search completed.']],
                ['expect' => '^UID FETCH 7 \(UID FLAGS INTERNALDATE RFC822\.SIZE BODYSTRUCTURE BODY\.PEEK\[HEADER\.FIELDS \(FROM .*\)\] MODSEQ\)$', 'reply' => [
                    '* 2 FETCH (UID 7 MODSEQ (54) FLAGS (\Seen) INTERNALDATE "30-Oct-2026 09:51:00 +0100" RFC822.SIZE 1234 BODYSTRUCTURE ("TEXT" "PLAIN" ("CHARSET" "utf-8") NIL NIL "7BIT" 5 1 NIL NIL NIL NIL) BODY[HEADER.FIELDS (FROM SUBJECT)] {' . strlen($header) . "}\r\n" . $header . ")\r\n",
                    'TAG OK Fetch completed.',
                ]],
                ['expect' => '^UID FETCH 7:8 \(UID BODY\.PEEK\[1\]\)$', 'reply' => [
                    "* 2 FETCH (UID 7 BODY[1] {5}\r\nHello)\r\n",
                    "* 3 FETCH (UID 8 BODY[1] \"Bye\")\r\n",
                    'TAG OK Fetch completed.',
                ]],
                ['expect' => '^UID FETCH 1:\* \(UID FLAGS MODSEQ\) \(CHANGEDSINCE 50\)$', 'reply' => [
                    '* 3 FETCH (UID 8 FLAGS (\Seen \Flagged) MODSEQ (55))', 'TAG OK Fetch completed.',
                ]],
                ['expect' => '^APPEND "INBOX\.Sent" \(\\\\Seen\) \{20\+\}\r\nFrom: me\r\n\r\nHi there$', 'reply' => ['TAG OK [APPENDUID 1700000001 12] Append completed.']],
                ['expect' => '^SELECT "INBOX" \(CONDSTORE\)$', 'reply' => ['* 3 EXISTS', '* OK [UIDVALIDITY 1700000000] ok', 'TAG OK [READ-WRITE] Select completed.']],
                ['expect' => '^UID MOVE 7 "INBOX\.Trash"$', 'reply' => ['* OK [COPYUID 1700000002 7 31] Moved UIDs.', '* 2 EXPUNGE', 'TAG OK Move completed.']],
                ['expect' => '^UID STORE 5 \+FLAGS\.SILENT \(\\\\Bogus\)$', 'reply' => ['TAG NO Flag not allowed']],
                ['expect' => '^LOGOUT$', 'reply' => ['* BYE Logging out', 'TAG OK Logout completed.']],
            ],
        ]);

        $client = new ImapClient('127.0.0.1', $port, 'none', 5, false);
        $client->connect();
        $client->login('me@jbspraxis.com', 's3cret');
        $session = new SocketImapSession($client);

        $this->assertTrue($session->hasCapability('CONDSTORE'));
        $this->assertTrue($session->hasCapability('MOVE'));

        $folders = $session->folders();
        $this->assertSame(['INBOX', 'INBOX.Sent', 'INBOX.&AMk-l&AOk-ments', 'INBOX.Trash'], array_column($folders, 'path'));
        $this->assertSame('Éléments', $folders[2]['name']);
        $this->assertSame(['\\HasNoChildren', '\\Sent'], $folders[1]['attributes']);
        $this->assertSame('Trash', $folders[3]['name']);

        $box = $session->examine('INBOX');
        $this->assertSame(['uidvalidity' => 1700000000, 'uidnext' => 9, 'exists' => 3, 'highestmodseq' => 55], $box);

        $this->assertSame([5, 7, 8], $session->search('SINCE 1-Oct-2025'));

        $summary = $session->fetchSummaries([7])[0];
        $this->assertSame(7, $summary['uid']);
        $this->assertSame(['\\Seen'], $summary['flags']);
        $this->assertSame(1234, $summary['size']);
        $this->assertSame(54, $summary['modseq']);
        $this->assertSame($header, $summary['headers']);
        $this->assertSame('TEXT', $summary['structure'][0]);

        $this->assertSame([7 => ['1' => 'Hello'], 8 => ['1' => 'Bye']], $session->fetchSectionsBatch([7, 8], ['1']));
        $this->assertSame([8 => ['flags' => ['\\Seen', '\\Flagged'], 'modseq' => 55]], $session->fetchFlags([], 50));
        $this->assertSame(12, $session->append('INBOX.Sent', "From: me\n\nHi there", ['\\Seen']));

        $session->select('INBOX');
        $this->assertSame([7 => 31], $session->move([7], 'INBOX.Trash'));

        try {
            $session->addFlags([5], ['\\Bogus']);
            $this->fail('A NO answer must throw');
        } catch (ImapException $e) {
            $this->assertSame('command', $e->reason);
            $this->assertStringContainsString('Flag not allowed', $e->getMessage());
        }

        $session->logout();
    }

    public function test_login_with_synchronising_literals_and_a_refused_password(): void
    {
        $port = $this->serve([
            'greeting' => '* OK [CAPABILITY IMAP4rev1 AUTH=LOGIN] ready',
            'steps' => [
                ['expect' => '^LOGIN "me@x\.com" \{10\}\r\npässwörd$', 'reply' => ['TAG NO [AUTHENTICATIONFAILED] Authentication failed.']],
            ],
        ]);

        $client = new ImapClient('127.0.0.1', $port, 'none', 5, false);
        $client->connect();
        try {
            $client->login('me@x.com', 'pässwörd');
            $this->fail('A refused password must throw');
        } catch (ImapException $e) {
            $this->assertSame('auth', $e->reason);
            $this->assertStringNotContainsString('pässwörd', $e->getMessage());
        }
    }

    public function test_unreachable_server(): void
    {
        $client = new ImapClient('127.0.0.1', 1, 'none', 2, false);
        $this->expectException(ImapException::class);
        $client->connect();
    }

    private function serve(array $script): int
    {
        $file = tempnam(sys_get_temp_dir(), 'imap');
        file_put_contents($file, json_encode($script));
        $php = PHP_BINARY;
        $this->process = proc_open([$php, __DIR__ . '/../../Support/Mail/scripted_imap_server.php', $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this->pipes);
        $port = (int) trim((string) fgets($this->pipes[1]));
        $this->assertGreaterThan(0, $port, 'The scripted server did not start');

        return $port;
    }
}
