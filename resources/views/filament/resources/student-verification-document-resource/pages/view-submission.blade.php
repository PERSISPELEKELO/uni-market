@php
    $history = $this->history();
@endphp

<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section
            x-data="{ rotation: 0, zoom: 1 }"
            class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-gray-700"
        >
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" x-on:click="rotation -= 90" class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="h-4 w-4" /> Rotate
                </button>
                <button type="button" x-on:click="rotation += 90" class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <x-filament::icon icon="heroicon-o-arrow-uturn-right" class="h-4 w-4" /> Rotate
                </button>
                <button type="button" x-on:click="zoom = Math.min(zoom + 0.25, 3)" class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <x-filament::icon icon="heroicon-o-magnifying-glass-plus" class="h-4 w-4" /> Zoom in
                </button>
                <button type="button" x-on:click="zoom = Math.max(zoom - 0.25, 1)" class="fi-btn fi-btn-size-sm inline-flex items-center gap-1 rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                    <x-filament::icon icon="heroicon-o-magnifying-glass-minus" class="h-4 w-4" /> Zoom out
                </button>
                <button type="button" x-on:click="rotation = 0; zoom = 1" class="fi-btn fi-btn-size-sm rounded-lg px-3 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">
                    Reset
                </button>
                <a href="{{ route('verification.documents.show', $record) }}" target="_blank" class="ml-auto inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                    <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="h-4 w-4" /> Full size
                </a>
            </div>

            <div class="flex max-h-[70vh] items-center justify-center overflow-auto rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                @if ($record->isFileDeleted())
                    <p class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        This image was deleted 30 days after the decision was made. Only the decision below is kept.
                    </p>
                @else
                    <img
                        src="{{ route('verification.documents.show', $record) }}"
                        alt="Student ID document submitted by {{ $record->user->name }}"
                        class="max-w-full transition-transform duration-150"
                        x-bind:style="`transform: rotate(${rotation}deg) scale(${zoom})`"
                    />
                @endif
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Registered details</h3>
                <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Name</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $record->user->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Student number</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $record->user->student_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $record->user->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Submitted</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $record->submitted_at->format('F j, Y \a\t g:i A') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Previous submissions</h3>
                @if ($history->isEmpty())
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This is the student's first submission.</p>
                @else
                    <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($history as $attempt)
                            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                <span class="text-gray-600 dark:text-gray-400">{{ $attempt->submitted_at->format('M j, Y') }}</span>
                                <x-document-status-badge :status="$attempt->status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <a href="{{ $this->getResource()::getUrl('index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:underline dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" /> Back to the queue to approve, reject or request resubmission
            </a>
        </section>
    </div>
</x-filament-panels::page>
