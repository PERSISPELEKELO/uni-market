@component('layouts.app', ['title' => 'Session expired - UniMarket'])
    @include('errors.minimal-page', [
        'code' => 419,
        'heading' => 'Your session has expired',
        'message' => 'For your security, the page timed out. Please go back, refresh the page and try again.',
    ])
@endcomponent
