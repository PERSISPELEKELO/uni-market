@component('layouts.app', ['title' => 'Something went wrong - UniMarket'])
    @include('errors.minimal-page', [
        'code' => 500,
        'heading' => 'Something went wrong on our side',
        'message' => 'We hit an unexpected problem. Please try again in a moment. If it keeps happening, let us know.',
    ])
@endcomponent
