<?php

namespace App\Models\Campaigns;

use Illuminate\Database\Eloquent\Model;

/** Someone who asked to hear from JBS Praxis without being a client's or a lead's contact */
class Subscriber extends Model
{
    protected $table = 'marketing_subscribers';

    protected $guarded = ['id'];

    protected $casts = ['unsubscribed_at' => 'datetime'];
}
