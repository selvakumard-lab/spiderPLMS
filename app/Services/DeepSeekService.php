<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks DeepSeek to analyse a project's activity log and estimate the next step.
 * Falls back to a simple average-based estimate when no key is set or the call fails,
 * so the system keeps working without the AI.
 */
class DeepSeekService
{
    public function isConfigured(): bool
    {
        return filled(config('services.deepseek.key'));
    }

    /**
     * One general chat completion that must answer in JSON. Returns null when no key is set or the call fails,
     * so callers can fall back to a rule-based answer.
     *
     * @return array<string, mixed>|null
     */
    public function completeJson(string $system, string $prompt, float $temperature = 0.3, int $timeout = 30): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('services.deepseek.key'))->timeout($timeout)->acceptJson()
                ->post(rtrim((string) config('services.deepseek.base_url'), '/').'/chat/completions', [
                    'model' => config('services.deepseek.model'),
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => $temperature,
                ])->throw();

            $data = json_decode((string) $response->json('choices.0.message.content'), true, flags: JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (Throwable $e) {
            Log::warning('DeepSeek request failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array{next_step_hours: float, summary: string, source: string}
     */
    public function analyse(Project $project): array
    {
        $project->loadMissing('activities');
        $key = config('services.deepseek.key');

        if (! empty($key) && $project->activities->isNotEmpty()) {
            try {
                return $this->askDeepSeek($project, (string) $key);
            } catch (Throwable $e) {
                Log::warning('DeepSeek analysis failed, using fallback: '.$e->getMessage());
            }
        }

        return $this->fallback($project);
    }

    /**
     * @return array{next_step_hours: float, summary: string, source: string}
     */
    private function askDeepSeek(Project $project, string $key): array
    {
        $log = $project->activities->map(fn ($a) => sprintf(
            '%s | %s | %s | est %sh | actual %sh | %s | progress %d%%',
            $a->activity_date->toDateString(), $a->title, $a->description ?: '-', $a->estimated_hours ?? '?', $a->hours_spent, $a->status, $a->progress_percent,
        ))->implode("\n");

        $prompt = sprintf(
            "Project: %s\nStart: %s\nPlanned completion: %s\nTotal working days: %s\nToday: %s\n\nActivity log (date | title | description | estimate | actual | status | progress):\n%s\n\n".
            'Analyse progress against the plan and estimate the hours the NEXT step will need. '.
            'Reply with JSON only: {"next_step_hours": number, "summary": "2-3 sentences: is the project on time or behind, and why"}.',
            $project->name, $project->start_date?->toDateString(), ($project->revised_due_date ?? $project->due_date)?->toDateString(),
            $project->total_working_days ?? 'unknown', now()->toDateString(), $log,
        );

        $response = Http::withToken($key)->timeout(25)->acceptJson()
            ->post(rtrim((string) config('services.deepseek.base_url'), '/').'/chat/completions', [
                'model' => config('services.deepseek.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a project delivery analyst. Be concise and realistic.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ])->throw();

        $data = json_decode((string) $response->json('choices.0.message.content'), true, flags: JSON_THROW_ON_ERROR);

        return [
            'next_step_hours' => round(max(0.0, (float) ($data['next_step_hours'] ?? 0)), 2),
            'summary' => trim((string) ($data['summary'] ?? '')),
            'source' => 'deepseek',
        ];
    }

    /**
     * @return array{next_step_hours: float, summary: string, source: string}
     */
    private function fallback(Project $project): array
    {
        $done = $project->activities->where('hours_spent', '>', 0);
        $average = $done->isEmpty() ? 0.0 : round((float) $done->avg('hours_spent'), 2);

        $estimated = $project->activities->whereNotNull('estimated_hours');
        $ratio = $estimated->sum('estimated_hours') > 0 ? $estimated->sum('hours_spent') / $estimated->sum('estimated_hours') : 1.0;

        $summary = $done->isEmpty()
            ? 'No activity recorded yet, so no estimate is possible.'
            : sprintf(
                'Estimate based on your average of %sh per step%s. (Rule-based estimate — add a DeepSeek key for AI analysis.)',
                $average,
                $ratio > 1.1 ? sprintf(', which is running %d%% over earlier estimates', round(($ratio - 1) * 100)) : '',
            );

        return ['next_step_hours' => round($average * max(1.0, $ratio), 2), 'summary' => $summary, 'source' => 'fallback'];
    }
}
