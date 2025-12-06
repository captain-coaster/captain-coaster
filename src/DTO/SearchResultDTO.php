<?php

declare(strict_types=1);

namespace App\DTO;

class SearchResultDTO
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $type,
        public readonly ?string $image = null,
        public readonly ?string $subtitle = null,
        public readonly array $metadata = []
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'image' => $this->image,
            'subtitle' => $this->subtitle,
            'metadata' => $this->metadata,
        ];
    }
}
