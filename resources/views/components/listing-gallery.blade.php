@props(['images', 'title'])

{{--
    Facebook Marketplace-style photo display and full-screen viewer.

    wire:ignore on the outer element is load-bearing, not decorative: this
    whole block manages its own DOM (which photo is showing, whether the
    viewer is open) entirely in Alpine. Without wire:ignore, any other
    wire:click on this same Livewire component elsewhere on the page
    (reserving, messaging, choosing a buyer) would re-render and could
    reset which photo is active or close an open viewer out from under the
    student using it - which is the exact "the viewer only works once" bug
    this replaces. See resources/js/app.js's listingGallery() for the
    Alpine component behind this.

    The slot is the desktop (>=1024px) viewer's details panel. It's
    rendered server-side like everything else on the page, but once it's
    inside this wire:ignore'd subtree a later Livewire re-render won't
    update it live - so its action buttons close the viewer immediately
    on click (x-on:click="closeViewer()" alongside the real wire:click),
    letting the real, reactive card elsewhere on the page show whatever
    happened next.
--}}
<div wire:ignore x-data="listingGallery({ images: @js($images) })">
    <div class="space-y-3">
        <div class="photo-frame card relative aspect-[4/3] w-full overflow-hidden sm:aspect-[4/3]" tabindex="0" x-on:keydown.arrow-left="prev()" x-on:keydown.arrow-right="next()">
            <template x-if="current">
                <div class="absolute inset-0 scale-110 bg-cover bg-center blur-2xl brightness-50" x-bind:style="`background-image: url('${current}')`" aria-hidden="true"></div>
            </template>

            <button type="button" class="relative flex h-full w-full cursor-zoom-in items-center justify-center" x-on:click="openViewer(activeIndex)" aria-label="View photo full screen">
                <template x-if="current">
                    <img x-bind:src="current" x-bind:alt="`{{ $title }} - photo ${activeIndex + 1}`" class="max-h-full max-w-full object-contain" draggable="false" />
                </template>
                <template x-if="!current">
                    <div class="flex flex-col items-center gap-1 text-slate-300">
                        <x-app-icon name="photo" class="h-10 w-10" />
                        <span class="text-xs font-medium">No photo</span>
                    </div>
                </template>
            </button>

            <template x-if="images.length > 1">
                <button type="button" x-on:click="prev()" class="absolute left-2 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition-colors hover:bg-black/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:left-3" aria-label="Previous photo">
                    <x-app-icon name="arrow-left" class="h-5 w-5" />
                </button>
            </template>

            <template x-if="images.length > 1">
                <button type="button" x-on:click="next()" class="absolute right-2 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition-colors hover:bg-black/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:right-3" aria-label="Next photo">
                    <x-app-icon name="arrow-right" class="h-5 w-5" />
                </button>
            </template>

            <template x-if="images.length > 1">
                <span class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs font-medium text-white" x-text="`${activeIndex + 1} / ${images.length}`"></span>
            </template>
        </div>

        <template x-if="images.length > 1">
            <ul class="flex gap-3 overflow-x-auto pb-1" aria-label="Photo thumbnails">
                <template x-for="(image, index) in images" x-bind:key="index">
                    <li class="flex-shrink-0">
                        <button
                            type="button"
                            x-on:click="show(index)"
                            x-bind:aria-current="activeIndex === index ? 'true' : 'false'"
                            x-bind:aria-label="`Show photo ${index + 1} of ${images.length}`"
                            class="block h-16 w-16 overflow-hidden rounded-lg border-2 sm:h-20 sm:w-20"
                            x-bind:class="activeIndex === index ? 'border-brand-700 ring-2 ring-brand-200' : 'border-slate-200 opacity-80 hover:opacity-100'"
                        >
                            <img x-bind:src="image" alt="" loading="lazy" class="h-full w-full object-cover" />
                        </button>
                    </li>
                </template>
            </ul>
        </template>
    </div>

    {{-- Full-screen viewer --}}
    <div
        x-show="viewerOpen"
        x-cloak
        x-ref="viewer"
        x-transition.opacity
        x-on:keydown.escape.window="viewerOpen && closeViewer()"
        x-on:keydown.arrow-left.window="viewerOpen && prev()"
        x-on:keydown.arrow-right.window="viewerOpen && next()"
        x-on:keydown.tab="trapFocus($event)"
        x-on:touchstart="onTouchStart($event)"
        x-on:touchmove="onTouchMove($event)"
        x-on:touchend="onTouchEnd($event)"
        class="fixed inset-0 z-[70] flex bg-black/95"
        role="dialog"
        aria-modal="true"
        x-bind:aria-label="`${'{{ $title }}'} photo viewer`"
    >
        {{-- Clicking the backdrop (outside the photo itself) closes the viewer. --}}
        <div class="relative flex min-w-0 flex-1 items-center justify-center overflow-hidden" x-on:click="closeViewer()">
            <template x-if="current">
                <div class="absolute inset-0 scale-110 bg-cover bg-center blur-2xl brightness-50" x-bind:style="`background-image: url('${current}')`" aria-hidden="true"></div>
            </template>

            <button
                type="button"
                x-ref="viewerClose"
                x-on:click.stop="closeViewer()"
                class="absolute left-3 top-3 z-10 flex h-11 w-11 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                aria-label="Close photo viewer"
                title="Close"
            >
                <x-app-icon name="x" class="h-6 w-6" />
            </button>

            <template x-if="images.length > 1">
                <button
                    type="button"
                    x-on:click.stop="prev()"
                    class="absolute left-2 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:left-4"
                    aria-label="Previous photo"
                >
                    <x-app-icon name="arrow-left" class="h-6 w-6" />
                </button>
            </template>

            <template x-if="current">
                <img
                    x-on:click.stop="toggleZoom()"
                    x-bind:src="current"
                    x-bind:alt="`{{ $title }} - photo ${activeIndex + 1}`"
                    class="relative max-h-full max-w-full select-none object-contain transition-transform duration-200"
                    x-bind:class="zoomed ? 'scale-150 cursor-zoom-out' : 'cursor-zoom-in'"
                    draggable="false"
                />
            </template>

            <template x-if="images.length > 1">
                <button
                    type="button"
                    x-on:click.stop="next()"
                    class="absolute right-2 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:right-4"
                    aria-label="Next photo"
                >
                    <x-app-icon name="arrow-right" class="h-6 w-6" />
                </button>
            </template>

            <template x-if="images.length > 1">
                <span class="absolute bottom-20 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs font-medium text-white sm:bottom-3" x-text="`${activeIndex + 1} / ${images.length}`"></span>
            </template>

            <template x-if="images.length > 1">
                <ul class="absolute inset-x-0 bottom-3 flex justify-center gap-2 overflow-x-auto px-3 sm:hidden" aria-label="Photo thumbnails" x-on:click.stop="">
                    <template x-for="(image, index) in images" x-bind:key="index">
                        <li class="flex-shrink-0">
                            <button type="button" x-on:click="show(index)" class="block h-12 w-12 overflow-hidden rounded-lg border-2" x-bind:class="activeIndex === index ? 'border-white' : 'border-white/30 opacity-70'">
                                <img x-bind:src="image" alt="" loading="lazy" class="h-full w-full object-cover" />
                            </button>
                        </li>
                    </template>
                </ul>
            </template>
        </div>

        @isset($slot)
            {{-- Desktop-only details panel, >=1024px (Facebook keeps the
                 viewer itself image-only on smaller screens). --}}
            <div class="hidden w-96 flex-shrink-0 overflow-y-auto border-l border-slate-800 bg-[#18191a] p-6 lg:block" x-on:click.stop="">
                {{ $slot }}
            </div>
        @endisset
    </div>
</div>
