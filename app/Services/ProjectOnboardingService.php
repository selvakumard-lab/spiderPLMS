<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAiAnalysis;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * AI project onboarding: reads an uploaded requirement document, extracts what the project is, estimates the
 * working days and proposes the steps. Everything is an editable first estimate. The user reviews it, and only then
 * is the project (with its plan) created. Without a DeepSeek key, or when the call fails, a rule-based reading is used.
 */
class ProjectOnboardingService
{
    private const MAX_STEPS = 12;

    private const MAX_PROMPT_CHARACTERS = 24000;

    /** Share of the working days each default stage gets when the AI gives no steps. */
    private const STAGE_WEIGHTS = ['requirements' => 10, 'design' => 15, 'development' => 45, 'testing' => 20, 'deployment' => 5, 'handover' => 5];

    private const STAGE_DESCRIPTIONS = [
        'requirements' => 'Review the documentation with the client and confirm scope.',
        'design' => 'Design the solution and get the client approval.',
        'development' => 'Build the agreed modules.',
        'testing' => 'Test the work and fix issues with the client.',
        'deployment' => 'Release the work to its live environment.',
        'handover' => 'Hand over, train the client and close the project.',
    ];

    private const TECHNOLOGIES = [
        'Laravel', 'PHP', 'React', 'React Native', 'Vue', 'Angular', 'Node.js', 'Python', 'Django', 'Flutter', 'Android', 'iOS', 'Swift', 'Kotlin',
        'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'AWS', 'Azure', 'Docker', 'WordPress', 'Shopify', 'Razorpay', 'Stripe', 'WhatsApp', 'REST API', 'GraphQL', 'Tailwind',
    ];

    public function __construct(private DocumentTextExtractor $extractor, private DeepSeekService $ai) {}

    /** Stores the document, reads it and saves the result. The project does not exist yet. */
    public function analyse(UploadedFile $file, ?User $by): ProjectAiAnalysis
    {
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, DocumentTextExtractor::EXTENSIONS, true), 422, 'Upload a PDF, DOCX, TXT or MD file.');

        $tenant = app(TenantContext::class)->get();
        $path = $file->storeAs("tenants/{$tenant->uuid}/onboarding", Str::uuid().'.'.$extension, 'local');

        try {
            $text = $this->extractor->extract(Storage::disk('local')->path($path), $extension);
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($path);
            abort(422, $e->getMessage());
        }

        $result = $this->readWithAi($text, $file->getClientOriginalName()) ?? $this->readWithRules($text, $file->getClientOriginalName());

        return ProjectAiAnalysis::create([
            'tenant_id' => $tenant->id,
            'created_by' => $by?->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'text_length' => mb_strlen($text),
            'source' => $result['source'],
            'confidence' => $result['confidence'],
            'extracted' => $result['extracted'],
            'estimate' => $result['estimate'],
        ]);
    }

    /**
     * Creates the plan for a new project from the reviewed steps, fitted to the project's own working days
     * (so a changed deadline in the form is respected), and files the document with the project.
     *
     * @param  list<array{title: string, description?: ?string, stage?: ?string, working_days?: int|float|null, deliverables?: list<string>}>  $steps
     */
    public function applyPlan(Project $project, array $steps, ?ProjectAiAnalysis $analysis, ?User $by): int
    {
        $steps = array_values(array_filter($steps, fn ($s) => filled($s['title'] ?? null)));

        if ($steps === [] || $project->start_date === null || $project->due_date === null) {
            return 0;
        }

        $days = $this->workingDays($project->start_date, $project->due_date);
        $fitted = $this->fitDays(array_map(fn ($s) => max(1, (int) ($s['working_days'] ?? 1)), $steps), max(count($steps), count($days)));

        $cursor = 0;
        foreach ($steps as $i => $step) {
            $from = min($cursor, count($days) - 1);
            $to = min(count($days) - 1, $from + $fitted[$i] - 1);
            $first = $i === 0 && $project->start_date->lte(today());

            $project->milestones()->create([
                'tenant_id' => $project->tenant_id,
                'stage' => isset($step['stage']) && isset(Project::STAGES[$step['stage']]) ? $step['stage'] : null,
                'title' => Str::limit(trim($step['title']), 190, ''),
                'description' => filled($step['description'] ?? null) ? Str::limit(trim((string) $step['description']), 1900, '') : null,
                'deliverables' => collect((array) ($step['deliverables'] ?? []))->filter(fn ($d) => is_string($d) && trim($d) !== '')->map(fn ($d) => Str::limit(trim($d), 200, ''))->take(10)->values()->all() ?: null,
                'sort_order' => $i,
                'planned_start' => $days[$from]->toDateString(),
                'due_date' => $days[$to]->toDateString(),
                'estimated_days' => $fitted[$i],
                'status' => $first ? 'in_progress' : 'pending',
                'started_on' => $first ? $project->start_date->toDateString() : null,
                'started_at' => $first ? now() : null,
                'source' => $analysis !== null ? 'ai' : 'manual',
            ]);

            $cursor = $to + 1;
        }

        if ($analysis !== null) {
            $analysis->forceFill(['project_id' => $project->id])->save();

            $project->documents()->create([
                'uploaded_by' => $by?->id,
                'title' => 'Project documentation',
                'note' => 'Uploaded for AI onboarding.',
                'name' => $analysis->original_name,
                'path' => $analysis->path,
                'mime_type' => $analysis->mime_type,
                'size' => $analysis->size,
                'visible_to_client' => false,
                'from_client' => false,
            ]);
        }

        return count($steps);
    }

    /**
     * Mon-Fri dates from `$from` to `$to`, both included. Never empty: a range with no weekday yields the start date.
     *
     * @return list<Carbon>
     */
    public function workingDays(Carbon $from, Carbon $to): array
    {
        $days = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            if ($day->isWeekday()) {
                $days[] = $day->copy();
            }
        }

        return $days ?: [$from->copy()];
    }

    /**
     * Shares `$total` days over the steps in proportion to their weights, every step getting at least one day.
     * The result always adds up to the total (largest remainder method).
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    public function fitDays(array $weights, int $total): array
    {
        $count = count($weights);
        $total = max($total, $count);
        $sum = max(1, array_sum($weights));
        $days = [];
        $remainders = [];

        foreach ($weights as $i => $weight) {
            $exact = $weight / $sum * $total;
            $days[$i] = max(1, (int) floor($exact));
            $remainders[$i] = $exact - floor($exact);
        }

        arsort($remainders);
        $keys = array_keys($remainders);

        for ($guard = 0; array_sum($days) !== $total && $guard < $total * 2; $guard++) {
            $key = $keys[$guard % $count];
            if (array_sum($days) < $total) {
                $days[$key]++;
            } elseif ($days[$key] > 1) {
                $days[$key]--;
            }
        }

        ksort($days);

        return array_values($days);
    }

    /** @return array{source: string, confidence: int, extracted: array<string, mixed>, estimate: array<string, mixed>}|null */
    private function readWithAi(string $text, string $filename): ?array
    {
        if (! $this->ai->isConfigured()) {
            return null;
        }

        $stages = implode(', ', array_keys(Project::STAGES));
        $system = 'You are a delivery manager who reads a project requirement document and prepares an initial project plan. '
            .'Use only what the document says; never invent clients, features or technologies. If something is not stated, leave it empty. '
            .'Estimate in WORKING days (Monday to Friday) for a small agile team and treat it as an initial estimate. '
            .'Reply with JSON only: {"project_name": string, "client_name": string, "project_type": string, "summary": "2-4 sentences", '
            .'"scope": [string], "modules": [{"name": string, "description": string}], "deliverables": [string], "technologies": [string], '
            .'"complexity": "low|medium|high", "estimated_working_days": integer, "confidence": integer 0-100, "assumptions": [string], "risks": [string], '
            ."\"steps\": [{\"title\": string, \"description\": string, \"stage\": one of [{$stages}] or null, \"working_days\": integer, \"deliverables\": [string]}]}. "
            .'Steps run in order from kickoff to final delivery (6 to 10 steps) and their working_days must add up to estimated_working_days.';

        $data = $this->ai->completeJson($system, "File: {$filename}\n\nDocument:\n".Str::limit($text, self::MAX_PROMPT_CHARACTERS, ''), 0.2, 90);

        if ($data === null || blank($data['estimated_working_days'] ?? null)) {
            return null;
        }

        return $this->normalise($data, 'deepseek', $filename);
    }

    /** @return array{source: string, confidence: int, extracted: array<string, mixed>, estimate: array<string, mixed>} */
    private function readWithRules(string $text, string $filename): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));
        $words = str_word_count($text);

        $technologies = array_values(array_filter(self::TECHNOLOGIES, fn ($t) => preg_match('/(?<![\pL\pN])'.preg_quote($t, '/').'(?![\pL\pN])/iu', $text) === 1));
        $modules = collect($lines)->filter(fn ($l) => mb_strlen($l) <= 60 && ! str_ends_with($l, '.') && preg_match('/^(#{1,4}\s+|\d{1,2}[.)]\s+|module\s*[:\-])/i', $l) === 1)
            ->map(fn ($l) => trim((string) preg_replace('/^(#{1,4}\s+|\d{1,2}[.)]\s+|module\s*[:\-]\s*)/i', '', $l)))
            ->filter()->unique()->take(10)->map(fn ($name) => ['name' => $name, 'description' => ''])->values()->all();

        $deliverables = [];
        foreach ($lines as $i => $line) {
            if (stripos($line, 'deliverable') !== false) {
                foreach (array_slice($lines, $i + 1, 10) as $next) {
                    if (preg_match('/^[-*\x{2022}]|\d+[.)]\s/u', $next) !== 1) {
                        break;
                    }
                    $deliverables[] = trim((string) preg_replace('/^[-*\x{2022}\d.)\s]+/u', '', $next));
                }
                break;
            }
        }

        $days = (int) max(10, min(180, round(15 + min($words / 40, 60) + count($modules) * 3 + count($technologies) * 1.5)));
        $steps = [];
        foreach (self::STAGE_WEIGHTS as $stage => $weight) {
            $steps[] = ['title' => Project::STAGES[$stage], 'description' => self::STAGE_DESCRIPTIONS[$stage], 'stage' => $stage, 'working_days' => $weight, 'deliverables' => []];
        }

        $name = trim((string) preg_replace('/^#+\s*/', '', $lines[0] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $name = Str::headline(pathinfo($filename, PATHINFO_FILENAME));
        }

        return $this->normalise([
            'project_name' => $name,
            'client_name' => '',
            'project_type' => $this->guessType($text),
            'summary' => Str::limit(trim((string) preg_replace('/\s+/', ' ', $text)), 600, '...'),
            'scope' => [],
            'modules' => $modules,
            'deliverables' => $deliverables,
            'technologies' => $technologies,
            'complexity' => $days < 30 ? 'low' : ($days < 70 ? 'medium' : 'high'),
            'estimated_working_days' => $days,
            'confidence' => 30,
            'assumptions' => ['This is a rule-based reading because the AI service was not available. Check every field.'],
            'risks' => [],
            'steps' => $steps,
        ], 'fallback', $filename);
    }

    /**
     * Cleans whatever the AI (or the rules) returned into one predictable shape.
     *
     * @param  array<string, mixed>  $data
     * @return array{source: string, confidence: int, extracted: array<string, mixed>, estimate: array<string, mixed>}
     */
    private function normalise(array $data, string $source, string $filename): array
    {
        $list = fn (mixed $value, int $max = 15, int $length = 200): array => collect(is_array($value) ? $value : [])
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')->map(fn ($v) => Str::limit(trim($v), $length, ''))->unique()->take($max)->values()->all();

        $steps = collect(is_array($data['steps'] ?? null) ? $data['steps'] : [])
            ->filter(fn ($s) => is_array($s) && filled($s['title'] ?? null))->take(self::MAX_STEPS)
            ->map(fn (array $s) => [
                'title' => Str::limit(trim((string) $s['title']), 190, ''),
                'description' => Str::limit(trim((string) ($s['description'] ?? '')), 500, ''),
                'stage' => isset($s['stage']) && isset(Project::STAGES[$s['stage']]) ? $s['stage'] : null,
                'working_days' => max(1, (int) round((float) ($s['working_days'] ?? 1))),
                'deliverables' => $list($s['deliverables'] ?? [], 8),
            ])->values()->all();

        if ($steps === []) {
            foreach (self::STAGE_WEIGHTS as $stage => $weight) {
                $steps[] = ['title' => Project::STAGES[$stage], 'description' => self::STAGE_DESCRIPTIONS[$stage], 'stage' => $stage, 'working_days' => $weight, 'deliverables' => []];
            }
        }

        // The total is what the AI estimated; the steps are shares of it.
        $total = max(count($steps), min(1000, (int) round((float) ($data['estimated_working_days'] ?? array_sum(array_column($steps, 'working_days'))))));
        foreach ($this->fitDays(array_column($steps, 'working_days'), $total) as $i => $d) {
            $steps[$i]['working_days'] = $d;
        }

        $modules = collect(is_array($data['modules'] ?? null) ? $data['modules'] : [])
            ->filter(fn ($m) => is_array($m) && filled($m['name'] ?? null))->take(15)
            ->map(fn (array $m) => ['name' => Str::limit(trim((string) $m['name']), 120, ''), 'description' => Str::limit(trim((string) ($m['description'] ?? '')), 300, '')])->values()->all();

        $complexity = in_array($data['complexity'] ?? null, ['low', 'medium', 'high'], true) ? $data['complexity'] : 'medium';
        $confidence = max(0, min(100, (int) ($data['confidence'] ?? 50)));

        return [
            'source' => $source,
            'confidence' => $source === 'fallback' ? min($confidence, 40) : $confidence,
            'extracted' => [
                'project_name' => Str::limit(trim((string) ($data['project_name'] ?? '')) ?: Str::headline(pathinfo($filename, PATHINFO_FILENAME)), 160, ''),
                'client_name' => Str::limit(trim((string) ($data['client_name'] ?? '')), 160, ''),
                'project_type' => Str::limit(trim((string) ($data['project_type'] ?? '')), 80, ''),
                'summary' => Str::limit(trim((string) ($data['summary'] ?? '')), 1500, ''),
                'scope' => $list($data['scope'] ?? []),
                'modules' => $modules,
                'deliverables' => $list($data['deliverables'] ?? []),
                'technologies' => $list($data['technologies'] ?? [], 20, 60),
            ],
            'estimate' => [
                'working_days' => $total,
                'complexity' => $complexity,
                'assumptions' => $list($data['assumptions'] ?? [], 8, 300),
                'risks' => $list($data['risks'] ?? [], 8, 300),
                'steps' => $steps,
                'notes' => 'An initial estimate, not a promised date. It counts Monday to Friday and does not know your holidays or team availability.',
            ],
        ];
    }

    private function guessType(string $text): string
    {
        return match (true) {
            preg_match('/e-?commerce|online store|shopping cart|checkout/i', $text) === 1 => 'E-commerce',
            preg_match('/mobile app|android|ios\b|flutter|react native/i', $text) === 1 => 'Mobile app',
            preg_match('/\b(crm|erp|inventory|billing|hrms?)\b/i', $text) === 1 => 'Business software',
            preg_match('/website|landing page|cms/i', $text) === 1 => 'Website',
            preg_match('/\bapi\b|integration|webhook/i', $text) === 1 => 'API and integration',
            default => 'Software project',
        };
    }
}
