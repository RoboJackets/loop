Loop completed processing email request #{{ $email->id }} sent at {{ $email->email_sent_at }}.

The following information was extracted:

Vendor: {{ $email->vendor_name }}
Amount: ${{ $email->vendor_document_amount }}
Reference number: {{ $email->vendor_document_reference }}
Document date: {{ $email->vendor_document_date?->format('Y-m-d') }}

@forelse ($validation_errors as $error)
@if($loop->first)
The following problems were detected during processing:
@endif
- {!! $error !!}
@empty
No problems were detected.
@endforelse

You may view the request at {{ route('nova.pages.detail', ['resource' => \App\Nova\EmailRequest::uriKey(), 'resourceId' => $email->id]) }}.
