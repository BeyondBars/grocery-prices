<?php

namespace App\Models\User;

use App\Support\Enums\UserRole;
use Silber\Bouncer\Database\Ability as BouncerAbility;

class Ability extends BouncerAbility
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'title', 'group', 'excludes'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'int',
        'entity_id' => 'int',
        'only_owned' => 'boolean',
        'excludes' => 'json',
    ];
}
