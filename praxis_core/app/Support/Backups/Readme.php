<?php

namespace App\Support\Backups;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * "READ ME FIRST – how to restore" at the top of the Drive folder (owner's decision 5): what is in the folder, what
 * opens it and how, written for the owner on a day the server is gone. resources/views/backups/readme.blade.php,
 * rendered by dompdf as the proposals are. Its version is the hash of its text, so Drive gets a new copy only when
 * the text changes.
 */
class Readme
{
    public function html(): string
    {
        return view('backups.readme', [
            'root' => (string) config('backups.drive.root'),
            'sites' => Sites::all(),
            'retention' => (array) config('backups.retention'),
            'fingerprint' => Keys::fingerprint(),
        ])->render();
    }

    public function version(): string
    {
        return substr(hash('sha256', $this->html()), 0, 16);
    }

    /** The PDF in a temporary file; the caller removes it */
    public function writePdf(): string
    {
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);
        $dompdf = new Dompdf(new Options([
            'defaultFont' => 'DejaVu Sans',
            'defaultPaperSize' => 'a4',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'fontCache' => $cache,
            'tempDir' => $cache,
            'chroot' => [$cache],
        ]));
        $dompdf->loadHtml($this->html(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $path = tempnam(sys_get_temp_dir(), 'bkreadme');
        file_put_contents($path, $dompdf->output());

        return $path;
    }
}
