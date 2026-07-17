<?php

namespace App\Services\AI;

use App\Models\ChatSession;
use App\Models\Setting;
use App\Models\User;
use App\Services\AI\Sources\DatabaseKnowledgeSource;
use Illuminate\Support\Facades\Log;

class AssistantService
{
    public function __construct(
        private DatabaseKnowledgeSource $databaseSource,
        private OpenAIClient $openAI,
    ) {
    }

    /**
     * Handle one user message inside a session: apply business rules,
     * retrieve DB knowledge, ask OpenAI (or fall back to a DB-only answer),
     * persist both sides of the exchange, and return the reply payload.
     */
    public function reply(ChatSession $session, string $userMessage, ?User $user): array
    {
        $locale = $session->locale ?: 'tr';
        $includeProCodes = $user?->hasPermission('view-product-codes') ?? false;

        $session->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // --- Business rule: never discuss pricing; route to dealer network. ---
        if ($this->isPriceQuestion($userMessage)) {
            $note = Setting::get('dealer_contact_note')
                ?: 'Fiyat ve teklif bilgisi için lütfen size en yakın bayimizle iletişime geçin.';

            return $this->persistAssistantReply($session, $note, [], 'rule');
        }

        // --- Retrieve knowledge from the database first. ---
        $retrieved = $this->databaseSource->retrieve($userMessage, $includeProCodes, $locale);

        // --- Ask OpenAI with strict grounding; fall back to DB-only answer on failure. ---
        try {
            $answer = $this->openAI->chat([
                ['role' => 'system', 'content' => $this->systemPrompt($includeProCodes, $locale)],
                ['role' => 'user', 'content' => $this->buildUserPrompt($userMessage, $retrieved['context'])],
            ]);

            if ($answer === '') {
                throw new \RuntimeException('Empty completion.');
            }

            return $this->persistAssistantReply($session, $answer, $retrieved['product_ids'], 'openai');
        } catch (\Throwable $e) {
            Log::warning('Assistant OpenAI fallback: ' . $e->getMessage());

            $fallback = $this->databaseOnlyAnswer($retrieved, $locale);

            return $this->persistAssistantReply($session, $fallback, $retrieved['product_ids'], 'db');
        }
    }

    private function systemPrompt(bool $includeProCodes, string $locale): string
    {
        $lang = $locale === 'en' ? 'English' : 'Turkish';

        $rules = [
            "You are Cera, the product assistant for Turkuaz Seramik / CeraStyle, a ceramic bathroom products manufacturer.",
            "Answer in {$lang}.",
            "Answer ONLY using the product data provided in the user message. If the data does not contain the answer, say you don't have that information and suggest contacting the company.",
            "NEVER give prices, discounts, or quotes. The company sells only through its dealer network — direct users to their nearest dealer for pricing.",
            "Be concise, friendly, and factual. When documents (datasheets, installation guides) are relevant, share their links.",
        ];

        // Belt and suspenders: even though consumer contexts never contain
        // codes, instruct the model explicitly as well.
        $rules[] = $includeProCodes
            ? "The user is a verified professional (dealer/sales); you may share product codes (SKU) and variant codes from the data."
            : "NEVER mention internal product codes or SKUs, even if asked directly; those are only for authorized dealers.";

        return implode("\n", $rules);
    }

    private function buildUserPrompt(string $userMessage, string $context): string
    {
        if ($context === '') {
            return "PRODUCT DATA: (no matching products found)\n\nUSER QUESTION: {$userMessage}";
        }

        return "PRODUCT DATA:\n{$context}\n\nUSER QUESTION: {$userMessage}";
    }

    /**
     * DB-only answer used when OpenAI is unavailable/disabled: a readable
     * summary of the matched products, so the assistant stays useful.
     */
    private function databaseOnlyAnswer(array $retrieved, string $locale): string
    {
        if ($retrieved['context'] === '') {
            return $locale === 'en'
                ? 'I could not find matching products for your question. Could you rephrase, or mention a series or category name?'
                : 'Sorunuza uygun bir ürün bulamadım. Farklı kelimelerle tekrar deneyebilir veya bir seri/kategori adı belirtebilir misiniz?';
        }

        $intro = $locale === 'en'
            ? "Here is what I found in our catalog:\n\n"
            : "Kataloğumuzda bulduklarım:\n\n";

        return $intro . $retrieved['context'];
    }

    private function persistAssistantReply(ChatSession $session, string $content, array $productIds, string $source): array
    {
        $session->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'matched_product_ids' => $productIds ?: null,
            'source' => $source,
        ]);

        return [
            'reply' => $content,
            'product_ids' => $productIds,
            'source' => $source,
        ];
    }

    /**
     * Same rule set as the demo's price detection, extended slightly.
     */
    private function isPriceQuestion(string $message): bool
    {
        $normalized = mb_strtolower($message);

        foreach (['fiyat', 'kaç para', 'kac para', 'ne kadar', 'ücret', 'ucret', 'price', 'cost', 'how much', 'indirim', 'discount', 'teklif'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
