<?php

declare(strict_types=1);

namespace App\Nova;

use Laravel\Nova\Fields\Currency;

class DocuSignFundingSourceFields
{
    /**
     * Get the pivot fields for the relationship.
     *
     * @return array<\Laravel\Nova\Fields\Field>
     */
    public function __invoke(): array
    {
        return [
            Currency::make('This Envelope', 'amount'),
        ];
    }
}
