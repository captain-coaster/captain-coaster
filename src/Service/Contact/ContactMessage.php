<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\Coaster;
use App\Entity\Park;
use App\Entity\User;

/**
 * A message sent through the contact page, with the page or search it came from.
 */
final readonly class ContactMessage
{
    public function __construct(
        public ContactTopic $topic,
        public string $message,
        public string $name,
        public ?string $email,
        public string $locale,
        public ?string $subject = null,
        public ?User $user = null,
        public ?Coaster $coaster = null,
        public ?Park $park = null,
        public ?string $searchQuery = null,
    ) {
    }

    /** What the message is about, in a few words: the custom subject, the coaster, the park or the search. */
    public function about(): ?string
    {
        if (null !== $this->subject) {
            return $this->subject;
        }

        return match (true) {
            null !== $this->coaster => implode(', ', array_filter([$this->coaster->getName(), $this->coaster->getPark()?->getName()])),
            null !== $this->park => $this->park->getName(),
            null !== $this->searchQuery => '"'.$this->searchQuery.'"',
            default => null,
        };
    }

    public function title(): string
    {
        $about = $this->about();

        return $this->topic->teamLabel().(null !== $about ? ': '.$about : '');
    }
}
