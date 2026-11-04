<?php

namespace App\Support\CompanyDocuments;

use RuntimeException;

/** The file can't go to Claude (a photo over 5 MB, a Word file without text); the message is a lang key */
final class CannotRead extends RuntimeException {}
