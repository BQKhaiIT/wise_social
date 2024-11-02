<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{

    const IS_VIEW_VIEWD = 2;
    const IS_VIEW_UNVIEWD = 1;

    use HasFactory;

    protected $table = "messages";
    protected $fillable = [
        'user_id', 'friend_id', 'is_view'
    ];
}
