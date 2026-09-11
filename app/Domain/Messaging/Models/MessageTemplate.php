<?php

namespace App\Domain\Messaging\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An SMS or e-mail text the trainers can edit. Placeholders stay in Polish, e.g. `{imie}`.
 */
#[Fillable(['key', 'body'])]
class MessageTemplate extends Model {}
