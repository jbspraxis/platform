<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
use App\Mail\SystemAlert;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every email HQ and the Client Portal send shows "JBS Praxis" as the sender name (docs/BRAND.md section 13),
 * even on a server whose .env still names the old sender in MAIL_FROM_NAME.
 */
class SenderNameTest extends TestCase
{
    public function test_every_branded_email_is_sent_by_jbs_praxis(): void
    {
        config(['mail.from.name' => 'JBS Praxis Intelligence Desk', 'mail.from.address' => 'system@jbspraxis.com']);

        $checked = 0;
        foreach (File::files(app_path('Mail')) as $file) {
            $class = 'App\\Mail\\' . $file->getFilenameWithoutExtension();
            if (! is_subclass_of($class, BrandedMail::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            foreach ($class::samples() as $name => $mail) {
                $from = $mail->envelope()->from;
                $this->assertSame('JBS Praxis', $from->name, "{$class} ({$name})");
                $this->assertSame('system@jbspraxis.com', $from->address, "{$class} ({$name})");
                $checked++;
            }
        }
        $this->assertGreaterThan(10, $checked);
    }

    public function test_the_from_header_on_the_wire(): void
    {
        config(['mail.from.name' => 'JBS Praxis Intelligence Desk', 'mail.from.address' => 'system@jbspraxis.com', 'mail.default' => 'array']);

        Mail::to('ada@example.test')->send(collect(SystemAlert::samples())->first());

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('JBS Praxis <system@jbspraxis.com>', $sent->getHeaders()->get('From')->getBodyAsString());
    }
}
