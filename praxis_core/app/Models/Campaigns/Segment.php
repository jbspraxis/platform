<?php

namespace App\Models\Campaigns;

use App\Support\Campaigns\Audience;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** A saved audience (HQ › Campaigns › Audiences): its rules pick people when a broadcast starts sending */
class Segment extends Model
{
    use HasUuids;

    protected $table = 'broadcast_segments';

    protected $fillable = ['name', 'rules', 'created_by', 'updated_by'];

    protected $casts = ['rules' => 'array'];

    /** "Clients and leads · Industry: Banking · Language: French" */
    public function describe(): string
    {
        return Audience::describe($this->rules ?? []);
    }
}
