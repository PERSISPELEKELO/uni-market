<?php

// Static content for the floating Help panel (see App\Livewire\HelpPanel) -
// a searchable FAQ and quick links, never a live chat. Route names are
// resolved in the view, not stored here, so renaming a route still works.
return [
    'faqs' => [
        [
            'question' => 'How do I reserve an item?',
            'answer' => 'Open the listing and tap "Reserve this item". Reserving tells the seller you\'re interested - it doesn\'t charge you or guarantee the sale. If more than one student reserves, the seller chooses who to sell to.',
        ],
        [
            'question' => 'What is the 48-hour inspection window?',
            'answer' => 'For higher-value categories like electronics and furniture, you get 48 hours after handover to confirm the item matches the listing before the sale finalises. If something\'s wrong, raise a dispute from the transaction tracker instead of confirming.',
        ],
        [
            'question' => 'How do I become a Verified Student?',
            'answer' => 'Upload your student ID from your account page. Verified sellers show a green badge, which helps buyers trust your listings.',
        ],
        [
            'question' => 'How do I get paid, and is it safe?',
            'answer' => 'UniMarket doesn\'t move money for you - you arrange payment and handover with the other student on campus. The handover code and inspection window exist to protect both sides during that meet-up.',
        ],
        [
            'question' => 'Someone sold me something that was not as described. What do I do?',
            'answer' => 'If the purchase is still within its inspection window, raise a dispute from the transaction tracker rather than confirming acceptance. An administrator reviews every dispute - the final decision is always made by a human, never an AI alone.',
        ],
        [
            'question' => 'How do I stop seeing personalised recommendations?',
            'answer' => 'Go to your account\'s Privacy section and turn off "Personalise my recommendations". You can also clear your browsing history from the same place.',
        ],
    ],

    'quick_links' => [
        ['label' => 'Buying an item', 'route' => 'how-it-works', 'fragment' => 'buying'],
        ['label' => 'Selling an item', 'route' => 'how-it-works', 'fragment' => 'selling'],
        ['label' => 'Disputes & appeals', 'route' => 'how-it-works', 'fragment' => 'disputes'],
        ['label' => 'My account & privacy', 'route' => 'account', 'fragment' => 'privacy'],
    ],
];
