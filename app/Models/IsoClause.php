<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An ISO 45001:2018 clause (§4–§10) that documents and audits are tagged with.
 *
 * @property int $id
 * @property string $number
 * @property string $title
 */
class IsoClause extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
