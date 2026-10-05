<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * A row of a translated vocabulary, labelled by VocabularyLabeler.
 */
interface Vocabulary
{
    /** Stable identifier the code and the translations refer to. Null: displayed by its name. */
    public function getCode(): ?string;

    /** English label. */
    public function getName(): string;
}
