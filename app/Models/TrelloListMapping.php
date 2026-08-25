<?php

namespace App\Models;

use App\Enums\CoreStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrelloListMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'core_status',
        'trello_list_id',
        'trello_list_name',
    ];

    protected $casts = [
        'core_status' => CoreStatus::class,
    ];
}
