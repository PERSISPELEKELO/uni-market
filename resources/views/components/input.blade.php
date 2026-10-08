@props([
    'label' => null,
    'name',
    'icon' => null,
    'type' => 'text',
    'required' => false,
    'hint' => null,
])

@php
    $id = $attributes->get('id', $name);
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-danger-700 dark:text-danger-400" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                <x-app-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            {{ $attributes->class(['form-input', 'pl-11' => $icon]) }}
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        />
    </div>

    @if ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif

    <x-form-error :name="$name" />
</div>
