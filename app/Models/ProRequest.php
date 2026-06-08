<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProRequest extends Model
{
    protected $fillable = ['email', 'name', 'message', 'status'];
}
