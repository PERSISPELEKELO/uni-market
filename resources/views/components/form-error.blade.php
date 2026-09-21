@props(['name'])

@error($name)
    <p {{ $attributes->class(['form-error']) }} id="{{ str_replace('.', '-', $name) }}-error">
        <x-app-icon name="error" class="mt-0.5 h-4 w-4 flex-shrink-0" />
        <span>{{ $message }}</span>
    </p>
@enderror
