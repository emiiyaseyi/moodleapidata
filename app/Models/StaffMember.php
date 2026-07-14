<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffMember extends Model
{
    protected $fillable = [
        'email',
        'moodle_user_id',
        'fullname',
        'department',
        'join_date',
        'neo_exam_date',
        'neo_enrolled_at',
    ];

    protected $casts = [
        'join_date' => 'date',
        'neo_exam_date' => 'date',
        'neo_enrolled_at' => 'datetime',
    ];
}
