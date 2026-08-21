<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Series;
use App\Models\Subcategory;
use App\Services\Product\ProductSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
 * Shows exactly what a chat question turns into before the model ever sees it.
 *
 * The assistant never writes SQL from the question. ProductSearchService
 * detects a series/subcategory/category by name and filters on those ids, or
 * falls back to loading every active product and scoring it in PHP. This
 * command prints both halves, so a bad answer can be traced to the step that
 * caused it: intent detection, the SQL, or the scoring.
 *
 *   php artisan assistant:explain "60 cm mat siyah lavabo önerir misin?"
 */
class ExplainAssistantQuery extends Command
{
    protected $signature = 'assistant:explain
                            {question : The question exactly as a user would type it}
                            {--limit=10 : Row limit passed to searchWithIntent()}
                            {--scores : Also list the fuzzy score of every product that scored above zero}';

    protected $description = 'Trace a chat question through intent detection, SQL, and scoring.';

    public function handle(ProductSearchService $search): int
    {
        $question = (string) $this->argument('question');
        $normalized = $this->reach('normalize', $search, $question);
        $words = $this->reach('words', $search, $normalized);
        $tokens = $this->reach('tokenize', $search, $question);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan;options=bold>Question</>', $question);
        $this->components->twoColumnDetail('Normalized', $normalized);
        $this->components->twoColumnDetail('Words', implode(' . ', $words));
        $this->components->twoColumnDetail('Tokens (3+ chars, fuzzy only)', implode(' . ', $tokens));

        // The code lookup short-circuits the whole search, so flag it first.
        if (preg_match('/\b[0-9A-Z-]{6,}\b/i', $question, $m)) {
            $this->newLine();
            $this->components->warn("Contains a code-like token \"{$m[0]}\": DatabaseKnowledgeSource "
                .'calls findByCode() first and skips the search entirely if that hits.');
        }

        $this->newLine();
        $this->info('-- Intent detection ------------------------------');
        $detected = $this->detect($search, $question);

        foreach ($detected as $label => $hit) {
            $this->components->twoColumnDetail(
                $label,
                $hit ? "<fg=green>#{$hit['id']} {$hit['name']}</>" : '<fg=gray>(none)</>'
            );
        }

        $colorIds = $this->reach('detectColorIds', $search, $normalized);
        $measure = $this->reach('detectMeasure', $search, $normalized);

        $this->components->twoColumnDetail(
            'Colour',
            $colorIds
                ? '<fg=green>'.\App\Models\Color::whereIn('id', $colorIds)
                    ->get()->map(fn ($c) => '#'.$c->id.' '.($c->name['tr'] ?? ''))->implode(', ').'</>'
                : '<fg=gray>(none)</>'
        );
        $this->components->twoColumnDetail(
            'Size',
            $measure ? "<fg=green>{$measure['value']} ({$measure['type']})</>" : '<fg=gray>(none)</>'
        );

        // Colour and size words are consumed too, so the leftovers report below
        // only ever blames words nothing looked at.
        foreach (\App\Models\Color::whereIn('id', $colorIds ?: [])->get() as $c) {
            $detected['Colour #'.$c->id] = [
                'id' => $c->id,
                'name' => $c->name['tr'] ?? '',
                'normalized' => $this->reach('normalize', $search, $c->name['tr'] ?? ''),
            ];
        }

        if ($measure) {
            $detected['Size'] = ['id' => 0, 'name' => $measure['value'], 'normalized' => $measure['value'].' cm'];
        }

        $this->newLine();
        $this->info('-- SQL -------------------------------------------');

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = $this->interpolate($q->sql, $q->bindings);
        });

        $result = $search->searchWithIntent($question, (int) $this->option('limit'));

        foreach ($queries as $sql) {
            // The products query is the one that decides what comes back;
            // the rest are relation eager-loads.
            $this->line(str_contains($sql, 'from `products`')
                ? "  <fg=yellow>{$sql}</>"
                : "  <fg=gray>{$sql}</>");
        }

        $this->newLine();
        $this->info('-- Result ----------------------------------------');
        $this->components->twoColumnDetail(
            'Path taken',
            $result['filtered']
                ? '<fg=green>structured</> - real WHERE clauses, treated as a precise listing'
                : '<fg=yellow>fuzzy fallback</> - every active product loaded and scored in PHP'
        );
        $this->components->twoColumnDetail('Products returned', (string) $result['products']->count());

        if ($result['applied'] ?? []) {
            $this->components->twoColumnDetail(
                'Filters that survived',
                collect($result['applied'])
                    ->map(fn ($v, $k) => $k.'='.(is_array($v) ? implode('|', array_map(fn ($x) => is_array($x) ? implode(':', $x) : $x, $v)) : $v))
                    ->implode('  ')
            );
        }

        foreach ($result['products'] as $p) {
            $this->line(sprintf(
                '  <fg=gray>#%-5s</> %-40s <fg=gray>%s | %s | %s</>',
                $p->id,
                mb_strimwidth((string) ($p->name['tr'] ?? ''), 0, 40, '..'),
                $p->series?->name['tr'] ?? '-',
                $p->dimensions ?: '-',
                $p->color?->name['tr'] ?? '-'
            ));
        }

        if ($this->option('scores') && !$result['filtered']) {
            $this->scoreBreakdown($search, $tokens);
        }

        $this->leftovers($words, $detected, $result['filtered']);

        return self::SUCCESS;
    }

    /**
     * The words that never influenced the result — usually the reason an answer
     * ignores a size or colour the user clearly asked for.
     */
    private function leftovers(array $words, array $detected, bool $structured): void
    {
        $this->newLine();
        $this->info('-- What the search did NOT use -------------------');

        if (!$structured) {
            $this->line('  <fg=yellow>No structure was detected, so nothing was filtered in SQL at all.</>');
            $this->line('  <fg=yellow>Every active product was loaded and scored by substring match.</>');
            $this->newLine();
        }

        $consumed = [];

        foreach ($detected as $hit) {
            if ($hit) {
                $consumed = array_merge($consumed, preg_split('/[^a-z0-9]+/', $hit['normalized'], -1, PREG_SPLIT_NO_EMPTY) ?: []);
            }
        }

        $unused = array_values(array_filter($words, function (string $word) use ($consumed) {
            foreach ($consumed as $c) {
                if (str_starts_with($word, $c)) {
                    return false;
                }
            }

            return true;
        }));

        $this->line('  '.($unused === [] ? '(nothing)' : '<fg=red>'.implode('</>  <fg=red>', $unused).'</>'));

        $this->newLine();
        $this->line('  <fg=gray>Detected as intent: Series, Subcategory, Category, Colour, and a</>');
        $this->line('  <fg=gray>size ("60 cm" or "55x42"). Anything else above is ignored.</>');
        $this->line('  <fg=gray>Colour and size are dropped again — size first, then colour — if</>');
        $this->line('  <fg=gray>keeping them would return nothing at all.</>');
        $this->newLine();
    }

    private function scoreBreakdown(ProductSearchService $search, array $tokens): void
    {
        $this->newLine();
        $this->info('-- Fuzzy scores (top 15) -------------------------');

        $scored = Product::with(['category', 'subcategory', 'series', 'color'])
            ->active()->get()
            ->map(fn (Product $p) => ['product' => $p, 'score' => $this->reach('score', $search, $p, $tokens)])
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take(15);

        foreach ($scored as $row) {
            $this->line(sprintf(
                '  <fg=green>%3d</>  #%-5s %s',
                $row['score'],
                $row['product']->id,
                $row['product']->name['tr'] ?? ''
            ));
        }
    }

    /** @return array<string, array{id:int, name:string, normalized:string}|null> */
    private function detect(ProductSearchService $search, string $question): array
    {
        $normalized = $this->reach('normalize', $search, $question);

        $lookup = [
            'Series' => [Series::active()->get(), []],
            'Subcategory' => [Subcategory::active()->get(), $this->reach('subcategorySynonyms', $search)],
            'Category' => [Category::active()->get(), []],
        ];

        $out = [];

        foreach ($lookup as $label => [$models, $synonyms]) {
            $id = $this->reach('detectByName', $search, $models, $normalized, $synonyms);
            $model = $id ? $models->firstWhere('id', $id) : null;

            $out[$label] = $model ? [
                'id' => $model->id,
                'name' => $model->name['tr'] ?? '',
                'normalized' => $this->reach('normalize', $search, $model->name['tr'] ?? ''),
            ] : null;
        }

        return $out;
    }

    /**
     * Diagnostics need the private steps of the search; this is the only place
     * that reaches past the public API, and it is read-only.
     */
    private function reach(string $method, object $target, mixed ...$args): mixed
    {
        $ref = new ReflectionMethod($target, $method);
        $ref->setAccessible(true);

        return $ref->invoke($target, ...$args);
    }

    /** Bindings inlined, so the SQL can be pasted straight into a DB client. */
    private function interpolate(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            $value = match (true) {
                is_null($binding) => 'null',
                is_bool($binding) => $binding ? '1' : '0',
                is_numeric($binding) => (string) $binding,
                $binding instanceof \DateTimeInterface => "'".$binding->format('Y-m-d H:i:s')."'",
                default => "'".addslashes((string) $binding)."'",
            };

            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }
}
