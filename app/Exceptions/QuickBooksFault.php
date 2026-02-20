<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use QuickBooksOnline\API\Data\IPPFault;

class QuickBooksFault extends Exception
{
    /**
     * Construct a new instance.
     *
     * @psalm-mutation-free
     */
    public function __construct(IPPFault $fault)
    {
        if ($fault->Error?->Detail !== null) {
            parent::__construct($fault->Error->Detail);
        } elseif ($fault->Error?->Message !== null) {
            parent::__construct($fault->Error->Message);
        } else {
            parent::__construct(json_encode($fault));
        }
    }
}
