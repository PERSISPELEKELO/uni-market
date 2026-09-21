@component('layouts.app', ['title' => 'Back soon - UniMarket'])
    @include('errors.minimal-page', [
        'code' => 503,
        'heading' => 'UniMarket is taking a short break',
        'message' => 'We are doing some maintenance and will be back shortly. Thanks for your patience.',
    ])
@endcomponent
