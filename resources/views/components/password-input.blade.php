@php
    $model = $attributes->whereStartsWith('wire:model')->first();
    $hasError = $model && $errors->has($model);
@endphp

<div class="relative" x-data="{ show: false }">
    <input
        {{ $attributes->merge(['class' => 'form-input pr-12']) }}
        x-bind:type="show ? 'text' : 'password'"
        type="password"
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $model }}-error" @endif
    />
    <button
        type="button"
        class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-r-lg text-sm font-semibold text-brand-800 hover:text-brand-900"
        x-on:click="show = !show"
        x-bind:aria-pressed="show"
        x-bind:aria-label="show ? 'Hide password' : 'Show password'"
        x-text="show ? 'Hide' : 'Show'"
    >Show</button>
</div>
