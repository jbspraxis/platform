<?php

namespace App\Exceptions\Hq;

/** 404 from HQ: the article, report or record does not exist (or is not published). */
class HqNotFound extends HqApiException
{
}
