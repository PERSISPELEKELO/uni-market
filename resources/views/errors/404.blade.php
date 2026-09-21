@component('layouts.app', ['title' => 'Page not found - UniMarket'])
    @include('errors.minimal-page', [
        'code' => 404,
        'heading' => 'We could not find that page',
        'message' => 'The page or listing you are looking for may have been removed, sold or never existed.',
    ])
@endcomponent
