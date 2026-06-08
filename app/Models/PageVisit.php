<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageVisit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ip', 'country_code', 'city', 'url', 'page_type', 'user_agent', 'referer', 'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function scopeSince($query, $date)
    {
        return $query->where('visited_at', '>=', $date);
    }
}
