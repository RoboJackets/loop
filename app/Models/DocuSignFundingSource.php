<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DocuSignFundingSource extends Pivot
{
    /**
     * The name of the database table for this model.
     *
     * @var string
     */
    public $table = 'docusign_funding_sources';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'amount' => 'float',
    ];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
}
