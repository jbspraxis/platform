<?php

namespace App\Exceptions\Hq;

/** HQ could not be reached, timed out, refused the signature or answered with a server error. */
class HqUnavailable extends HqApiException
{
}
