<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MotorInsight extends Model
{
    use HasFactory;

    protected $table = 'motor_insights';

    protected $fillable = [
        'summary',
        'recommendation',
        'top_motor',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
    ];
}
