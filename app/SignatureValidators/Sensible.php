<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\SignatureValidators;

use App\Models\DocuSignEnvelope;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Throwable;

class Sensible implements SignatureValidator
{
    /**
     * Verifies a signature on a request from Sensible.
     *
     * Since Sensible doesn't actually sign requests, we'll just check the payload field.
     *
     * @see https://community.sensible.so/t/is-there-a-way-to-validate-a-webhook-event-really-came-from-sensible/29
     */
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        try {
            DocuSignEnvelope::fromEnvelopeUuid($request->payload);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
