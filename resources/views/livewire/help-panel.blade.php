<div>
    {{-- Desktop floating trigger. On mobile this lives inside the Profile
         tab's menu instead (layouts.app's #mobile-menu), so there's no
         second floating button competing with the bottom tab bar. --}}
    <button
        type="button"
        wire:click="openPanel"
        class="fixed bottom-6 right-6 z-40 hidden h-14 w-14 items-center justify-center rounded-full bg-brand-700 text-white shadow-lg transition-colors hover:bg-brand-800 md:flex"
        aria-label="Help"
        title="Help"
    >
        <x-app-icon name="help-circle" class="h-6 w-6" />
    </button>

    @if ($open)
        <div class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Help">
            <div class="absolute inset-0 bg-black/40" wire:click="closePanel"></div>

            <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:inset-x-auto sm:bottom-6 sm:right-6 sm:w-96 sm:rounded-2xl dark:bg-slate-200">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-ink">Help</h2>
                    <button type="button" wire:click="closePanel" class="-m-2 p-2" aria-label="Close help">
                        <x-app-icon name="x" class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-6" x-data="{ q: '', faqs: @js(collect($faqs)->values()) }">
                    <section>
                        <label for="help-faq-search" class="form-label">Search frequently asked questions</label>
                        <div class="relative">
                            <x-app-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input id="help-faq-search" type="search" x-model="q" placeholder="e.g. inspection, reserve, verified" class="form-input pl-9" />
                        </div>

                        <div class="mt-3 max-h-56 space-y-2 overflow-y-auto">
                            <template x-for="faq in faqs.filter(f => q === '' || f.question.toLowerCase().includes(q.toLowerCase()) || f.answer.toLowerCase().includes(q.toLowerCase()))" :key="faq.question">
                                <details class="card group p-3 open:bg-slate-50 dark:open:bg-slate-300">
                                    <summary class="cursor-pointer list-none text-sm font-semibold text-ink" x-text="faq.question"></summary>
                                    <p class="mt-2 text-sm text-slate-600" x-text="faq.answer"></p>
                                </details>
                            </template>
                            <p class="text-sm text-slate-500" x-show="faqs.filter(f => q === '' || f.question.toLowerCase().includes(q.toLowerCase()) || f.answer.toLowerCase().includes(q.toLowerCase())).length === 0">
                                No matching questions - try "Contact support" below.
                            </p>
                        </div>
                    </section>

                    <section class="border-t border-slate-100 pt-4">
                        <h3 class="form-label">Quick links</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($quickLinks as $link)
                                <a href="{{ route($link['route']) }}{{ $link['fragment'] ? '#'.$link['fragment'] : '' }}" class="chip">{{ $link['label'] }}</a>
                            @endforeach
                        </div>
                    </section>

                    <section class="border-t border-slate-100 pt-4">
                        <h3 class="form-label">Contact support</h3>
                        <p class="mb-2 text-xs text-slate-500">We reply by email - this isn't a live chat.</p>
                        <form wire:submit="submitContactForm" class="space-y-3">
                            <div>
                                <label for="help-subject" class="sr-only">Subject</label>
                                <input id="help-subject" type="text" wire:model="subject" placeholder="Subject" class="form-input" />
                                <x-form-error name="subject" />
                            </div>
                            <div>
                                <label for="help-message" class="sr-only">Message</label>
                                <textarea id="help-message" wire:model="message" rows="3" placeholder="How can we help?" class="form-input"></textarea>
                                <x-form-error name="message" />
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" wire:loading.attr="disabled" wire:target="submitContactForm">
                                <span wire:loading.remove wire:target="submitContactForm">Send message</span>
                                <span wire:loading wire:target="submitContactForm">Sending...</span>
                            </button>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    @endif
</div>
