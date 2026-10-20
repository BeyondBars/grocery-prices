<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A receipt wording that isn't a product ("BAG 15C" at Coles), remembered per store.
 */
class IgnoredWording extends Model
{
    protected $fillable = ['store_id', 'normalized_text', 'confirmed_by'];
}
