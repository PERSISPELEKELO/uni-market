{{-- Shared by the create and edit listing screens. Expects $categories and the Livewire $form object. --}}
@php
    $photoCount = count($form->existing_images) + count($form->images);
@endphp

<div class="space-y-6">

    <div>
        <label for="title" class="form-label">Title <span class="text-danger-700" aria-hidden="true">*</span></label>
        <input
            id="title"
            type="text"
            wire:model="form.title"
            maxlength="150"
            autocomplete="off"
            required
            placeholder="e.g. Calculus 9th Edition textbook (Stewart)"
            class="form-input"
            @error('form.title') aria-invalid="true" aria-describedby="form-title-error" @enderror
        />
        <x-form-error name="form.title" />
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <label for="category" class="form-label">Category <span class="text-danger-700" aria-hidden="true">*</span></label>
            <select
                id="category"
                wire:model="form.category_id"
                required
                class="form-input"
                @error('form.category_id') aria-invalid="true" aria-describedby="form-category_id-error" @enderror
            >
                <option value="">Choose a category</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <x-form-error name="form.category_id" />
        </div>

        <div>
            <label for="price" class="form-label">Price (ZMW) <span class="text-danger-700" aria-hidden="true">*</span></label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-semibold text-slate-600" aria-hidden="true">K</span>
                <input
                    id="price"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0.5"
                    wire:model="form.price"
                    required
                    placeholder="0.00"
                    class="form-input pl-8"
                    @error('form.price') aria-invalid="true" aria-describedby="form-price-error" @enderror
                />
            </div>
            <x-form-error name="form.price" />
        </div>
    </div>

    <fieldset>
        <legend class="form-label">Condition <span class="text-danger-700" aria-hidden="true">*</span></legend>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (\App\Models\Listing::CONDITIONS as $value => $label)
                <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-center text-sm font-medium text-slate-800 transition-colors hover:bg-slate-50 has-[:checked]:border-brand-700 has-[:checked]:bg-brand-700 has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-700">
                    <input type="radio" wire:model="form.condition" value="{{ $value }}" class="sr-only" />
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <x-form-error name="form.condition" />
    </fieldset>

    <div>
        <label for="description" class="form-label">Description <span class="text-danger-700" aria-hidden="true">*</span></label>
        <textarea
            id="description"
            wire:model="form.description"
            rows="5"
            maxlength="5000"
            required
            placeholder="Describe the item, its condition, anything included, and where on campus you can meet."
            class="form-input"
            @error('form.description') aria-invalid="true" aria-describedby="form-description-error" @enderror
        ></textarea>
        <x-form-error name="form.description" />
    </div>

    <div>
        <p class="form-label" id="photos-label">
            Photos <span class="text-danger-700" aria-hidden="true">*</span>
            <span class="ml-1 font-normal text-slate-600">({{ $photoCount }} of {{ \App\Livewire\Forms\ListingForm::MAX_IMAGES }})</span>
        </p>

        @if (count($form->existing_images) > 0)
            <ul class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($form->existing_images as $index => $path)
                    <li class="relative">
                        <x-listing-image :src="asset('storage/'.$path)" :alt="'Current photo '.($index + 1)" class="aspect-square w-full rounded-lg border border-slate-200" />
                        <button type="button" wire:click="removeExistingPhoto({{ $index }})" class="btn btn-danger btn-sm absolute right-1 top-1 min-h-8 min-w-8 rounded-full px-1.5 py-1.5">
                            <x-app-icon name="x" class="h-4 w-4" />
                            <span class="sr-only">Remove current photo {{ $index + 1 }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($photoCount < \App\Livewire\Forms\ListingForm::MAX_IMAGES)
            <label
                for="photos"
                x-data="{ isDropping: false }"
                x-on:dragover.prevent="isDropping = true"
                x-on:dragleave.prevent="isDropping = false"
                x-on:drop.prevent="
                    isDropping = false;
                    $refs.photoInput.files = $event.dataTransfer.files;
                    $refs.photoInput.dispatchEvent(new Event('change', { bubbles: true }));
                "
                class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition-colors hover:border-brand-400 hover:bg-brand-50 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-700"
                x-bind:class="{ 'border-brand-600 bg-brand-50': isDropping }"
            >
                <x-app-icon name="upload" class="h-8 w-8 text-brand-700" />
                <span class="text-sm font-semibold text-ink">Tap to add photos, or drag and drop them here</span>
                <span class="text-xs text-slate-600">JPG, PNG or WebP, up to 3 MB each</span>
                <input
                    id="photos"
                    x-ref="photoInput"
                    type="file"
                    wire:model="form.images"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                    aria-labelledby="photos-label"
                    @error('form.images') aria-invalid="true" aria-describedby="form-images-error" @enderror
                />
            </label>
        @else
            <x-alert type="info">You have added the maximum of {{ \App\Livewire\Forms\ListingForm::MAX_IMAGES }} photos. Remove one to add another.</x-alert>
        @endif

        <div wire:loading wire:target="form.images" class="mt-2 flex items-center gap-2 text-sm font-medium text-brand-800" role="status">
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            Uploading photos...
        </div>

        <x-form-error name="form.images" />
        <x-form-error name="form.images.*" />

        @if (count($form->images) > 0)
            <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($form->images as $index => $image)
                    <li class="relative">
                        @if ($image->isPreviewable())
                            <x-listing-image :src="$image->temporaryUrl()" :lazy="false" :alt="'New photo '.($index + 1)" class="aspect-square w-full rounded-lg border border-slate-200" />
                        @else
                            <x-listing-image class="aspect-square w-full rounded-lg border border-slate-200" />
                        @endif
                        <button type="button" wire:click="removeNewPhoto({{ $index }})" class="btn btn-danger btn-sm absolute right-1 top-1 min-h-8 min-w-8 rounded-full px-1.5 py-1.5">
                            <x-app-icon name="x" class="h-4 w-4" />
                            <span class="sr-only">Remove new photo {{ $index + 1 }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
