<?php

namespace App\Support\CompanyProfile;

/**
 * There is no company profile to read: nothing has been published yet, or the version asked for is not there.
 * The HQ screens and the download route turn this into something the reader can act on, rather than a 500.
 */
class MissingProfile extends \RuntimeException {}
