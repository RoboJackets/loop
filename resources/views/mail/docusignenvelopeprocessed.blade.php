Loop completed processing envelope {{ $envelope->id }} submitted at {{ $envelope->submitted_at }}.

@forelse ($validation_errors as $error)
@if($loop->first)
The following problems were detected during processing.
@endif
- {!! $error !!}
@empty
No problems were detected.
@endforelse

You may view the envelope at {{ route('nova.pages.detail', ['resource' => \App\Nova\DocuSignEnvelope::uriKey(), 'resourceId' => $envelope->id]) }}.
