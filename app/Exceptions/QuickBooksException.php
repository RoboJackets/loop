<?php

namespace App\Exceptions;

use Exception;
use http\Header\Parser;
use QuickBooksOnline\API\Data\IPPFault;

class QuickBooksException extends Exception
{
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
