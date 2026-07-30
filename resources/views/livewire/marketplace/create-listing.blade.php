<div class="max-w-3xl mx-auto">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[#0F172A] tracking-tight">Post a New Campus Listing</h1>
        <p class="text-sm font-regular text-slate-500 mt-1">
            Reach verified students across campus instantly with your listing.
        </p>
    </div>

    <!-- Form Container -->
    <form wire:submit.prevent="save" class="bg-white border border-slate-200 rounded-lg p-6 sm:p-8 shadow-sm space-y-6">

        <!-- Title -->
        <div>
            <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-2">
                Listing Title *
            </label>
            <input
                type="text"
                wire:model="title"
                placeholder="e.g. Calculus 9th Edition Textbook (Stewart)"
                class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg py-2.5 px-3.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669] focus:border-transparent transition"
            />
            @error('title') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Grid: Category & Price -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Category Dropdown -->
            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-2">
                    Category *
                </label>
                <select
                    wire:model="category_id"
                    class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg py-2.5 px-3.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669] focus:border-transparent transition"
                >
                    <option value="">Select Category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Price Input -->
            <div>
                <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-2">
                    Price (ZMW K) *
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 text-sm font-semibold">K</span>
                    <input
                        type="number"
                        step="0.01"
                        wire:model="price"
                        placeholder="0.00"
                        class="w-full pl-8 bg-[#F8FAFC] border border-slate-200 rounded-lg py-2.5 px-3.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669] focus:border-transparent transition"
                    />
                </div>
                @error('price') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- Condition Selector Radio Chips -->
        <div>
            <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-2">
                Item Condition *
            </label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach(['new' => 'Brand New', 'like_new' => 'Like New', 'good' => 'Good Condition', 'fair' => 'Fair / Used'] as $val => $label)
                    <label class="border border-slate-200 rounded-lg p-3 text-center cursor-pointer transition flex flex-col items-center {{ $condition === $val ? 'bg-[#1E293B] border-[#1E293B] text-white' : 'bg-[#F8FAFC] text-[#0F172A] hover:bg-slate-100' }}">
                        <input type="radio" wire:model="condition" value="{{ $val }}" class="sr-only" />
                        <span class="text-xs font-medium">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('condition') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Description -->
        <div>
            <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-2">
                Description *
            </label>
            <textarea
                wire:model="description"
                rows="4"
                placeholder="Describe item details, condition notes, included accessories, or pickup location options on campus..."
                class="w-full bg-[#F8FAFC] border border-slate-200 rounded-lg py-2.5 px-3.5 text-sm text-[#0F172A] focus:bg-white focus:ring-2 focus:ring-[#059669] focus:border-transparent transition"
            ></textarea>
            @error('description') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
        </div>

        <!-- Drag-and-Drop Image Uploader (Max 4 images) -->
        <div>
            <label class="block text-xs font-semibold text-[#0F172A] uppercase tracking-wider mb-2">
                Item Photos (Max 4 images) *
            </label>

            <!-- Dropzone container -->
            <div
                x-data="{ isDropping: false }"
                x-on:dragover.prevent="isDropping = true"
                x-on:dragleave.prevent="isDropping = false"
                x-on:drop.prevent="isDropping = false"
                class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center transition bg-[#F8FAFC]"
                :class="{ 'border-[#059669] bg-[#059669]/5': isDropping }"
            >
                <svg class="w-8 h-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>

                <p class="text-xs font-medium text-[#0F172A]">
                    Drag & drop images here, or <label class="text-[#312E81] underline cursor-pointer">browse files<input type="file" wire:model="images" multiple accept="image/*" class="hidden"></label>
                </p>
                <p class="text-[11px] text-slate-400 font-regular mt-1">PNG, JPG, JPEG up to 3MB each</p>
            </div>
            @error('images') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
            @error('images.*') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror

            <!-- Image Upload Previews -->
            @if(!empty($images))
                <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($images as $idx => $img)
                        <div class="relative h-24 rounded-lg overflow-hidden border border-slate-200 group bg-slate-100">
                            <img src="{{ $img->temporaryUrl() }}" class="w-full h-full object-cover" />
                            <button
                                type="button"
                                wire:click="removeImage({{ $idx }})"
                                class="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs opacity-90 hover:opacity-100 transition shadow-sm"
                            >
                                &times;
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Submit Button -->
        <div class="pt-4 border-t border-slate-100 flex justify-end">
            <button
                type="submit"
                class="bg-[#1E293B] hover:bg-[#312E81] text-white font-medium py-3 px-6 rounded-lg text-sm transition shadow-sm flex items-center space-x-2"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove>Publish Campus Listing</span>
                <span wire:loading class="flex items-center space-x-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Uploading...</span>
                </span>
            </button>
        </div>

    </form>
</div>
