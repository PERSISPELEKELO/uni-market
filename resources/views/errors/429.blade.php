@component('layouts.app', ['title' => 'Too many requests - UniMarket'])
    @include('errors.minimal-page', [
        'code' => 429,
        'heading' => 'Slow down a little',
        'message' => 'You have made too many requests in a short time. Please wait a moment and try again.',
    ])
@endcomponent
