<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent to a student when a new listing matches one of their saved searches
 * - see SavedSearch::newMatches() and NotifySavedSearchMatches.
 */
class SavedSearchMatchFound extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Listing>  $matches
     */
    public function __construct(
        protected SavedSearch $savedSearch,
        protected Collection $matches
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->matches->count();
        $description = $this->searchDescription();

        $mail = (new MailMessage)
            ->subject('New listings match your saved search')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($count.' new '.str('listing')->plural($count).' just matched your saved search for '.$description.'.');

        foreach ($this->matches->take(5) as $listing) {
            $mail->line('- '.$listing->title.' (K'.number_format((float) $listing->price, 2).')');
        }

        return $mail->action('View the marketplace', route('listings.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'saved_search_match_found',
            'title' => 'New matches',
            'message' => $this->matches->count().' new '.str('listing')->plural($this->matches->count()).' match your saved search for '.$this->searchDescription().'.',
            'url' => route('listings.index'),
        ];
    }

    private function searchDescription(): string
    {
        $parts = array_filter([
            $this->savedSearch->term ? '"'.$this->savedSearch->term.'"' : null,
            $this->savedSearch->category?->name,
            $this->savedSearch->condition,
        ]);

        return $parts === [] ? 'all new listings' : implode(' / ', $parts);
    }
}
