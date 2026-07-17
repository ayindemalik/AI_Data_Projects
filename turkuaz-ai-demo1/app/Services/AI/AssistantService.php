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

    public function reply(ChatSession $session, string $userMessage, ?User $user): array
    {
        // Language follows the MESSAGE, not the app locale: a Turkish
        // question gets a Turkish answer even if the site locale is 'en'.
        $locale = $this->detectLanguage($userMessage);
        if ($session->locale !== $locale) {
            $session->update(['locale' => $locale]);
        }

        $includeProCodes = $user?->hasPermission('view-product-codes') ?? false;

        $session->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);

        // --- Business rule: never discuss pricing; route to dealer network. ---
        if ($this->isPriceQuestion($userMessage)) {
            $note = Setting::get('dealer_contact_note')
                ?: ($locale === 'en'
                    ? 'For pricing and quotes, please contact your nearest dealer.'
                    : 'Fiyat ve teklif bilgisi için lütfen size en yakın bayimizle iletişime geçin.');

            return $this->persistAssistantReply($session, $note, [], 'rule');
        }

        $retrieved = $this->databaseSource->retrieve($userMessage, $includeProCodes, $locale);

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

    /**
     * THE PROMPT. Edit here to tune the assistant's behavior.
     */
    private function systemPrompt(bool $includeProCodes, string $locale): string
    {
        $lang = $locale === 'en' ? 'English' : 'Turkish';

        $rules = [
            "You are Cera, the product assistant for CeraStyle / Turkuaz Seramik, a ceramic bathroom products manufacturer.",
            "ALWAYS answer in {$lang} — the language of the user's question. Never mix languages.",
            "Answer ONLY from the PRODUCT DATA provided. Never invent products, features, or documents. If the data doesn't contain the answer, say so briefly and suggest contacting the company.",
            "Answer ONLY what was asked. If the user asks about a specific product type (e.g. washbasins), do not list other product types even if they appear in the data.",
            "For list questions ('which products...'), give a short bullet list: product name, dimensions, colors. No descriptions unless asked.",
            "For questions about ONE product, give its key details concisely. Share document links (datasheet, installation guide) only when relevant to the question.",
            "NEVER give prices, discounts, or quotes. The company sells only through its dealer network — for pricing, direct users to their nearest dealer.",
            "Keep answers under 150 words unless the user explicitly asks for full details.",
        ];

        $rules[] = $includeProCodes
            ? "The user is a verified professional (dealer/sales); you may share product codes (SKU) and variant codes when asked."
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
     * DB-only fallback: compact and localized.
     */
    private function databaseOnlyAnswer(array $retrieved, string $locale): string
    {
        if ($retrieved['context'] === '') {
            return $locale === 'en'
                ? 'I could not find matching products. Could you mention a series, category, or product name?'
                : 'Uygun bir ürün bulamadım. Bir seri, kategori veya ürün adı belirtebilir misiniz?';
        }

        $intro = $locale === 'en'
            ? "Here is what I found in our catalog:\n\n"
            : "Kataloğumuzda bulduklarım:\n\n";

        return $intro . $retrieved['context'];
    }

    /**
     * Lightweight language detection: Turkish-specific characters or common
     * Turkish words -> tr; otherwise en. Defaults to Turkish for ambiguous
     * short inputs, since the primary audience is Turkish.
     */
    private function detectLanguage(string $message): string
    {
        if (preg_match('/[çğıöşüÇĞİÖŞÜ]/u', $message)) {
            return 'tr';
        }

        $normalized = ' ' . mb_strtolower($message) . ' ';

        $turkishWords = [' ve ', ' bir ', ' var ', ' hangi ', ' nedir ', ' nasil ', ' icin ', ' mi ', ' mu ', ' serisi', 'merhaba', 'lavabo', 'klozet', 'rezervuar'];
        foreach ($turkishWords as $word) {
            if (str_contains($normalized, $word)) {
                return 'tr';
            }
        }

        $englishWords = [' the ', ' which ', ' what ', ' is ', ' are ', ' how ', ' does ', 'hello', 'hi ', ' please '];
        foreach ($englishWords as $word) {
            if (str_contains($normalized, $word)) {
                return 'en';
            }
        }

        return 'tr';
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
