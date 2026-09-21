@component('layouts.app', ['title' => 'Not allowed - UniMarket'])
    @include('errors.minimal-page', [
        'code' => 403,
        'heading' => 'You are not allowed to do that',
        'message' => $exception->getMessage() ?: 'You do not have permission to view this page or perform this action.',
    ])
@endcomponent
