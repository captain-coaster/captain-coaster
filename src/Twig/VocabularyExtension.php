<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\VocabularyLabeler;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class VocabularyExtension extends AbstractExtension
{
    public function __construct(private readonly VocabularyLabeler $labeler)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('vocab_label', $this->labeler->label(...)),
            // For a row carried as scalars: {{ code|vocab_term(name) }}.
            new TwigFilter('vocab_term', $this->labeler->term(...)),
        ];
    }
}
