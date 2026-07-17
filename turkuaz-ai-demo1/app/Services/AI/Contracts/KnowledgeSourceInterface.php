<?php

namespace App\Services\AI\Contracts;

interface KnowledgeSourceInterface
{
    /**
     * Given a user query, return:
     *  - 'context': plain-text knowledge for the AI prompt (empty string if nothing found)
     *  - 'product_ids': ids of any products that informed the context (for traceability)
     *
     * Future sources (PDF knowledge base, vector search) implement this same
     * contract and get registered alongside the database source — the
     * AssistantService orchestration does not change.
     */
    public function retrieve(string $query, bool $includeProCodes, string $locale): array;
}
