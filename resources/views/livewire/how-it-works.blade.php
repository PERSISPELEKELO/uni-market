<div class="mx-auto max-w-3xl">
    <x-page-header title="How UniMarket works" description="A plain-language guide to buying, selling and staying safe on the campus marketplace." />

    <div class="space-y-8">
        <x-section title="Buying an item">
            <x-steps :items="[
                ['title' => 'Find an item', 'description' => 'Search or browse by category on the marketplace home page.'],
                ['title' => 'Reserve it', 'description' => 'Reserving tells the seller you want it. If more than one student reserves, the seller chooses who to sell to.'],
                ['title' => 'Complete the purchase', 'description' => 'Some categories (like food and clothing) complete right away. Others (like electronics) use an inspection window - see below.'],
            ]" />
        </x-section>

        <x-section title="Selling an item">
            <x-steps :items="[
                ['title' => 'Create a listing', 'description' => 'Add photos, a description, a condition and a price.'],
                ['title' => 'Choose a buyer', 'description' => 'When students reserve your item, pick who to sell to from \'My Activity\'.'],
                ['title' => 'Hand it over and get paid', 'description' => 'Meet on campus to complete the sale. Your rating and reputation build up with every completed sale.'],
            ]" />
        </x-section>

        <x-section title="Direct purchase vs. inspection-protected purchase" description="Which one applies depends on the item's category, and is shown on every listing.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="card space-y-2 p-4">
                    <p class="flex items-center gap-1.5 font-semibold text-ink"><x-app-icon name="bag" class="h-4 w-4 text-accent-700" /> Direct purchase</p>
                    <p class="text-sm text-slate-600">For low-risk items like food, clothing and stationery. The purchase completes as soon as the seller selects you - no waiting period.</p>
                </div>
                <div class="card space-y-2 p-4">
                    <p class="flex items-center gap-1.5 font-semibold text-ink"><x-app-icon name="shield" class="h-4 w-4 text-accent-700" /> Inspection-protected purchase</p>
                    <p class="text-sm text-slate-600">For higher-value items like electronics and furniture. You get a 48-hour window after handover to confirm the item is as described, or raise a dispute.</p>
                </div>
            </div>
        </x-section>

        <x-section title="Student verification">
            <p class="text-sm text-slate-600">Upload your student ID from your account to become a Verified Student. Verified sellers show a green badge, which helps buyers trust your listings.</p>
        </x-section>

        <x-section title="If something goes wrong: disputes">
            <p class="text-sm text-slate-600">
                If an inspection-protected purchase isn't as described, you can raise a dispute directly from the transaction tracker. An administrator reviews it, assisted by an AI tool that summarises the evidence - the final decision is always made by a human administrator, never the AI alone. If you disagree with a moderation decision, you can appeal it from "My appeals".
            </p>
        </x-section>

        <x-section title="Your reputation">
            <p class="text-sm text-slate-600">
                Every completed transaction can be rated by the other student involved. Your rating and transaction history build your reputation on UniMarket - and you can export a digitally signed copy of it at any time from your account, to keep as a record even after you graduate.
            </p>
        </x-section>
    </div>
</div>
