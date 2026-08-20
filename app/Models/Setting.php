<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Setting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];
}
