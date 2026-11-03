<?php

namespace App\Support\Campaigns\Outreach;

use RuntimeException;

/** A website address HQ won't open (Website::read); the message is for the screen */
class UnsafeAddress extends RuntimeException
{
}
