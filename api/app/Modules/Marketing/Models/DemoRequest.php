<?php

namespace App\Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'organization_name',
        'phone',
        'message',
    ];
}
