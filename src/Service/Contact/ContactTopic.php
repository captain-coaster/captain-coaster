<?php

declare(strict_types=1);

namespace App\Service\Contact;

/**
 * What a contact message is about; the value is the `topic` query parameter of the contact page.
 */
enum ContactTopic: string
{
    case Data = 'data';
    case Missing = 'missing';
    case Site = 'site';
    case Other = 'other';

    public function translationKey(): string
    {
        return 'contact.topic.'.$this->value;
    }

    /** Team-facing label (email subject, Discord), in English like the other team notifications. */
    public function teamLabel(): string
    {
        return match ($this) {
            self::Data => 'Data error',
            self::Missing => 'Missing coaster or park',
            self::Site => 'Bug or idea',
            self::Other => 'Other',
        };
    }

    /** Discord embed color: sunshine, success, action and muted from tokens.css. */
    public function color(): int
    {
        return match ($this) {
            self::Data => 0xFFCD61,
            self::Missing => 0x23634E,
            self::Site => 0x2359AD,
            self::Other => 0x536577,
        };
    }
}
