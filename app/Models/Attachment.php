<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes;

    /**
     * Get all of the owning payable models.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo<\App\Models\DocuSignEnvelope,\App\Models\Attachment>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
