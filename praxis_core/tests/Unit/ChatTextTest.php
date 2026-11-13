<?php

namespace Tests\Unit;

use App\Support\Chat\ProjectChat;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

/** How a message's words are shown in the conversation (docs/CHAT.md): escaped, links clickable, names as each side reads them */
class ChatTextTest extends TestCase
{
    public function test_words_are_escaped_and_links_open_in_a_new_tab(): void
    {
        $html = ProjectChat::html("See https://example.com/a?b=1&c=2.\n<script>alert(1)</script> \"https://x.io\"");

        $this->assertStringContainsString('<a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener noreferrer nofollow">https://example.com/a?b=1&amp;c=2</a>.', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&quot;<a href="https://x.io"', $html);
        $this->assertStringContainsString('<br>', $html);
        $this->assertSame('javascript:alert(1)', ProjectChat::html('javascript:alert(1)'));
    }

    public function test_a_livewire_upload_is_described_before_it_is_moved_into_the_chat(): void
    {
        // A real temporary upload (not a test fake, whose size Livewire reads from its name): storing it on the same
        // disk moves it out of livewire-tmp, so its size can't be read afterwards (live error 500 on a PDF, 13 Nov 2026)
        Storage::fake('local');
        Storage::disk('local')->put('livewire-tmp/9kQ2report.pdf', str_repeat('%PDF-1.4 ', 100));
        $upload = new TemporaryUploadedFile('9kQ2report.pdf', 'local');

        $file = ProjectChat::store($upload, 'project-1');

        $this->assertSame(900, $file['size']);
        $this->assertSame('file', $file['kind']);
        $this->assertStringStartsWith('chat/project-1/', $file['path']);
        Storage::disk('local')->assertExists($file['path']);
        Storage::disk('local')->assertMissing('livewire-tmp/9kQ2report.pdf');
    }

    public function test_a_client_reads_staff_names_short_and_hq_reads_them_whole(): void
    {
        $this->assertSame('Tom-Blake F.', ProjectChat::name('Tom-Blake', 'Fotso', 'staff', 'client'));
        $this->assertSame('Tom-Blake Fotso', ProjectChat::name('Tom-Blake', 'Fotso', 'staff', 'staff'));
        $this->assertSame('Adaeze Okafor', ProjectChat::name('Adaeze', 'Okafor', 'client', 'client'));
        $this->assertSame('JBS Praxis', ProjectChat::name('', '', 'staff', 'client'));
        $this->assertSame('AO', ProjectChat::initials('adaeze', 'okafor'));
    }
}
