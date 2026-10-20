{{-- Shared by the create and edit listing screens. Expects $categories and the Livewire $form object. --}}
@php
    $photoCount = count($form->existing_images) + count($form->images);
    $previewCategory = $categories->firstWhere('id', (int) $form->category_id);

    // One lookup keyed by the same stable tokens photoOrder uses, so the
    // ordered list below (and the live preview's main photo) can resolve
    // either an existing stored photo or a newly-picked, not-yet-saved one
    // the same way.
    $photoMeta = [];
    foreach ($form->existing_images as $index => $path) {
        $photoMeta[$path] = ['url' => asset('storage/'.$path), 'type' => 'existing', 'index' => $index];
    }
    foreach ($form->images as $index => $image) {
        $photoMeta['new:'.$image->getFilename()] = [
            'url' => $image->isPreviewable() ? $image->temporaryUrl() : null,
            'type' => 'new',
            'index' => $index,
        ];
    }

    $mainToken = $form->photoOrder[0] ?? null;
    $previewPhotoUrl = $mainToken ? ($photoMeta[$mainToken]['url'] ?? null) : null;
@endphp

<div class="space-y-8">

    <x-section title="Basics" description="What is it, and how much?">
        <div class="space-y-6">
            <div>
                <label for="title" class="form-label">Title <span class="text-danger-700 dark:text-danger-400" aria-hidden="true">*</span></label>
                <input
                    id="title"
                    type="text"
                    wire:model.live.debounce.500ms="form.title"
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
                    <label for="category" class="form-label">Category <span class="text-danger-700 dark:text-danger-400" aria-hidden="true">*</span></label>
                    <select
                        id="category"
                        wire:model.live="form.category_id"
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
                    <label for="price" class="form-label">Price (ZMW) <span class="text-danger-700 dark:text-danger-400" aria-hidden="true">*</span></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm font-semibold text-slate-600" aria-hidden="true">K</span>
                        <input
                            id="price"
                            type="number"
                            inputmode="decimal"
                            step="0.01"
                            min="0.5"
                            wire:model.live.debounce.500ms="form.price"
                            required
                            placeholder="0.00"
                            class="form-input pl-8"
                            @error('form.price') aria-invalid="true" aria-describedby="form-price-error" @enderror
                        />
                    </div>
                    <x-form-error name="form.price" />
                </div>
            </div>
        </div>
    </x-section>

    <x-section title="Condition &amp; description">
        <div class="space-y-6">
            <fieldset>
                <legend class="form-label">Condition <span class="text-danger-700 dark:text-danger-400" aria-hidden="true">*</span></legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (\App\Models\Listing::CONDITIONS as $value => $label)
                        <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-center text-sm font-medium text-slate-800 transition-colors hover:bg-slate-50 has-[:checked]:border-brand-700 has-[:checked]:bg-brand-700 has-[:checked]:text-white has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-700 dark:bg-slate-200">
                            <input type="radio" wire:model.live="form.condition" value="{{ $value }}" class="sr-only" />
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <x-form-error name="form.condition" />
            </fieldset>

            <div>
                <label for="description" class="form-label">Description <span class="text-danger-700 dark:text-danger-400" aria-hidden="true">*</span></label>
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
        </div>
    </x-section>

    <x-section title="Photos">
    <div>
        <p class="form-label" id="photos-label">
            Photos <span class="text-danger-700 dark:text-danger-400" aria-hidden="true">*</span>
            <span class="ml-1 font-normal text-slate-600">({{ $photoCount }} of {{ \App\Livewire\Forms\ListingForm::MAX_IMAGES }})</span>
        </p>

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
                class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center transition-colors hover:border-brand-400 hover:bg-brand-50 dark:hover:bg-brand-500/10 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-700"
                x-bind:class="{ 'border-brand-600 bg-brand-50 dark:bg-brand-500/10': isDropping }"
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

        <div wire:loading wire:target="form.images" class="mt-2 flex items-center gap-2 text-sm font-medium text-brand-800 dark:text-brand-300" role="status">
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            Uploading photos...
        </div>

        <x-form-error name="form.images" />
        <x-form-error name="form.images.*" />

        @if (count($form->photoOrder) > 0)
            <p class="mt-4 text-xs text-slate-600">Drag to reorder. The first photo is your main photo and appears on the listing card.</p>

            <ul class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4" x-data="{ dragToken: null }">
                @foreach ($form->photoOrder as $position => $token)
                    @continue(! isset($photoMeta[$token]))
                    @php $meta = $photoMeta[$token]; @endphp
                    <li
                        wire:key="photo-{{ $token }}"
                        data-token="{{ $token }}"
                        draggable="true"
                        x-on:dragstart="dragToken = $el.dataset.token"
                        x-on:dragover.prevent
                        x-on:drop.prevent="
                            if (dragToken && dragToken !== $el.dataset.token) {
                                const tokens = Array.from($el.parentElement.children).map(li => li.dataset.token);
                                const from = tokens.indexOf(dragToken);
                                const to = tokens.indexOf($el.dataset.token);
                                tokens.splice(from, 1);
                                tokens.splice(to, 0, dragToken);
                                $wire.reorderPhotos(tokens);
                            }
                            dragToken = null;
                        "
                        class="relative cursor-move"
                    >
                        @if ($meta['url'])
                            <x-listing-image :src="$meta['url']" :lazy="false" :alt="($position === 0 ? 'Main photo' : 'Photo '.($position + 1))" class="aspect-square w-full rounded-lg border border-slate-200" />
                        @else
                            <x-listing-image class="aspect-square w-full rounded-lg border border-slate-200" />
                        @endif

                        @if ($position === 0)
                            <span class="badge badge-brand absolute left-1 top-1 shadow-sm">Main photo</span>
                        @endif

                        <button
                            type="button"
                            wire:click="{{ $meta['type'] === 'existing' ? 'removeExistingPhoto('.$meta['index'].')' : 'removeNewPhoto('.$meta['index'].')' }}"
                            class="btn btn-danger btn-sm absolute right-1 top-1 min-h-8 min-w-8 rounded-full px-1.5 py-1.5"
                            title="Remove this photo"
                        >
                            <x-app-icon name="x" class="h-4 w-4" /><span class="sr-only">Remove this photo</span>
                        </button>

                        <div class="absolute inset-x-1 bottom-1 flex items-center justify-between gap-1">
                            <div class="flex gap-1">
                                <button type="button" wire:click="movePhotoLeft('{{ $token }}')" @if($position === 0) disabled @endif class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white disabled:opacity-30" title="Move left" aria-label="Move photo left">
                                    <x-app-icon name="arrow-left" class="h-3.5 w-3.5" />
                                </button>
                                <button type="button" wire:click="movePhotoRight('{{ $token }}')" @if($position === count($form->photoOrder) - 1) disabled @endif class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white disabled:opacity-30" title="Move right" aria-label="Move photo right">
                                    <x-app-icon name="arrow-right" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                            @if ($position !== 0)
                                <button type="button" wire:click="setMainPhoto('{{ $token }}')" class="rounded-full bg-black/60 px-2 py-1 text-[10px] font-semibold text-white" title="Set as main photo">
                                    Set as main
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    </x-section>

    <x-section title="Preview" description="This is roughly how your listing will appear to buyers.">
        <div class="max-w-xs">
            <div class="card flex flex-col overflow-hidden">
                <div class="relative aspect-[4/3] w-full overflow-hidden bg-slate-100 dark:bg-slate-300">
                    @if ($previewPhotoUrl)
                        <img src="{{ $previewPhotoUrl }}" alt="" class="h-full w-full object-cover" />
                    @else
                        <div class="flex h-full w-full flex-col items-center justify-center gap-1 text-slate-500">
                            <x-app-icon name="photo" class="h-8 w-8" />
                            <span class="text-xs font-medium">No photo yet</span>
                        </div>
                    @endif
                    <span class="badge badge-neutral absolute left-3 top-3 bg-white/95 shadow-sm dark:bg-slate-200/95">
                        {{ \App\Models\Listing::CONDITIONS[$form->condition] ?? '' }}
                    </span>
                </div>
                <div class="flex flex-1 flex-col p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $previewCategory->name ?? 'Category' }}</p>
                    <h3 class="mt-1 line-clamp-2 break-words text-base font-semibold text-ink">{{ $form->title !== '' ? $form->title : 'Your listing title' }}</h3>
                    <p class="mt-3 text-lg font-bold text-ink">K{{ number_format((float) ($form->price ?: 0), 2) }}</p>
                </div>
            </div>
        </div>
    </x-section>
</div>
