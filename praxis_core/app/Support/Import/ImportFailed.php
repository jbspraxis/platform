<?php

namespace App\Support\Import;

use RuntimeException;

/** The whole file can't be imported (not a workbook, no data sheet, a required column missing…): the message is fit for the screen */
final class ImportFailed extends RuntimeException
{
}
