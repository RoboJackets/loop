<?php

declare(strict_types=1);

namespace App\Util;

use App\Exceptions\QuickBooksFault;
use App\Models\EmailRequest;
use App\Models\EngagePurchaseRequest;
use App\Models\User;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use QuickBooksOnline\API\Data\IPPAttachable;
use QuickBooksOnline\API\Data\IPPAttachableRef;
use QuickBooksOnline\API\Data\IPPAttachableResponse;
use QuickBooksOnline\API\Data\IPPReferenceType;
use QuickBooksOnline\API\DataService\DataService;

class QuickBooks
{
    public static function getDataService(?User $user = null): DataService
    {
        $data_service = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => config('quickbooks.client.id'),
            'ClientSecret' => config('quickbooks.client.secret'),
            'RedirectURI' => route('quickbooks.complete'),
            'scope' => 'com.intuit.quickbooks.accounting',
            'baseUrl' => config('quickbooks.environment'),
            'QBORealmID' => config('quickbooks.company.id'),
        ])->throwExceptionOnError(true);

        if ($user !== null) {
            $data_service->updateOAuth2Token($user->quickbooks_tokens);
        }

        return $data_service;
    }

    /**
     * Attach a file to a QuickBooks invoice.
     *
     * @phan-suppress PhanTypeMismatchProperty
     */
    public static function uploadAttachmentToInvoice(
        DataService $data_service,
        EngagePurchaseRequest|EmailRequest $request,
        string $filename
    ): void {
        if (! Storage::disk('local')->exists($filename)) {
            throw new FileNotFoundException('File \''.$filename.'\' does not exist');
        }

        $entity_reference = new IPPReferenceType();
        $entity_reference->type = 'Invoice';
        $entity_reference->value = $request->quickbooks_invoice_id;

        $attachable_reference = new IPPAttachableRef();
        $attachable_reference->EntityRef = $entity_reference;

        $attachable = new IPPAttachable();
        $attachable->AttachableRef = $attachable_reference;
        $attachable->FileName = basename($filename);

        $response = Sentry::wrapWithChildSpan(
            'quickbooks.upload_attachment_to_invoice',
            // @phan-suppress-next-line PhanTypeMismatchReturn
            static fn (): IPPAttachableResponse => $data_service->Upload(
                // @phan-suppress-next-line PhanPossiblyNullTypeArgumentInternal
                base64_encode(Storage::disk('local')->get($filename)),
                basename($filename),
                'application/pdf',
                $attachable
            )
        );

        if ($response->Fault !== null) {
            // @phan-suppress-next-line PhanTypeMismatchArgument
            throw new QuickBooksFault($response->Fault);
        }
    }
}
