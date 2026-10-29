<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KaizenTelemetry extends Model
{
    use HasFactory;

    // Allow Livewire to mass-assign these fields
    protected $fillable = [
        'identity',
        'email',
        'category',
        'severity',
        'description',
    ];
}