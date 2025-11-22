<?php

declare(strict_types=1);

namespace App\DTO;

class SearchResponseDTO
{
    public array $debug = [];

    public function __construct(
        public readonly string $query,
        public readonly array $results,
        public readonly array $totalResults,
        public readonly bool $hasMore
    ) {
    }

    public function toArray(): array
    {
        $formattedResults = [];
        foreach ($this->results as $type => $typeResults) {
            $formattedResults[$type] = array_map(
                fn (SearchResultDTO $result) => $result->toArray(),
                $typeResults
            );
        }

        $response = [
            'query' => $this->query,
            'results' => $formattedResults,
            'totalResults' => $this->totalResults,
            'hasMore' => $this->hasMore,
        ];

        // Add debug info if available
        if (!empty($this->debug)) {
            $response['debug'] = $this->debug;
        }

        return $response;
    }
}
