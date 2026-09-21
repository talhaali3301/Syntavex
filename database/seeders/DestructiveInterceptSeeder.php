<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\RunStep;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The bulk customer-record deletion intercept the Review Queue is built around.
 *
 * Kept out of DatabaseSeeder so the original 40-run dataset stays byte-for-byte
 * what Phases 2–5 were built and tested against. Run it after DatabaseSeeder.
 */
class DestructiveInterceptSeeder extends Seeder
{
    use WithoutModelEvents;

    private const WORKFLOW_SLUG = 'account-lifecycle-automation';

    private const AGENT = 'agent:janitor-v2';

    /** Rows the dormancy filter genuinely matched, before the NULL widening. */
    private const MATCHED_ROWS = 1204;

    private const NULL_ROWS = 13598;

    private const TABLE_ROWS = 15106;

    private const SCOPED_ROWS = self::MATCHED_ROWS + self::NULL_ROWS;

    private const ROW_CEILING = 500;

    public function run(): void
    {
        if (Workflow::query()->where('slug', self::WORKFLOW_SLUG)->exists()) {
            return;
        }

        $workspace = Workspace::query()->firstOrFail();

        $workflow = Workflow::create([
            'workspace_id' => $workspace->id,
            'name' => 'Account Lifecycle Automation',
            'slug' => self::WORKFLOW_SLUG,
            'trigger_type' => 'cron',
            'is_active' => true,
        ]);

        $startedAt = Carbon::now()->subMinutes(13)->subSeconds(41);
        $steps = $this->steps();

        $run = $this->persist(new WorkflowRun([
            'workflow_id' => $workflow->id,
            'run_key' => (string) ((int) WorkflowRun::query()->max('run_key') + 1),
            'status' => 'needs_review',
            'total_duration_ms' => array_sum(array_column($steps, 'duration_ms')),
            'total_tokens' => array_sum(array_column($steps, 'tokens_used')),
            'total_cost_usd' => array_sum(array_column($steps, 'cost_usd')),
        ]), $startedAt, $startedAt);

        $cursor = $startedAt->copy();
        $gate = null;

        foreach ($steps as $order => $attributes) {
            $step = $this->persist(
                new RunStep($attributes + ['workflow_run_id' => $run->id, 'step_order' => $order + 1]),
                $cursor->copy(),
                $cursor->copy(),
            );

            if ($attributes['step_type'] === 'approval_gate') {
                $gate = $step;
            }

            $cursor->addMilliseconds($attributes['duration_ms'] ?? 0);
        }

        $this->persist(new ApprovalRequest([
            'workflow_run_id' => $run->id,
            'run_step_id' => $gate->id,
            'risk_level' => 'critical',
            'summary' => sprintf(
                'Agent expanded a cleanup job from a 90-day dormancy rule to %s of the %s customer records after treating a null last_seen_at as dormant. Destructive scope · irreversible · no backup snapshot referenced.',
                number_format(self::SCOPED_ROWS),
                number_format(self::TABLE_ROWS),
            ),
            'status' => 'pending',
        ]), $cursor, $cursor);

        $this->persist(new AuditEvent([
            'workspace_id' => $workspace->id,
            'workflow_run_id' => $run->id,
            'action' => 'run_created',
            'performed_by' => 'system',
            'details' => ['run_key' => $run->run_key, 'workflow' => self::WORKFLOW_SLUG, 'trigger' => 'cron'],
        ]), $startedAt, $startedAt);

        $this->persist(new AuditEvent([
            'workspace_id' => $workspace->id,
            'workflow_run_id' => $run->id,
            'action' => 'approval_requested',
            'performed_by' => self::AGENT,
            'details' => [
                'run_key' => $run->run_key,
                'risk_level' => 'critical',
                'queue' => 'human_attention',
                'blocked_rule' => 'data.destructive.bulk_delete',
            ],
        ]), $cursor, $cursor);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function steps(): array
    {
        return [
            $this->step('Nightly dormancy sweep', 'webhook', 'completed', 268, null,
                ['source' => 'scheduler.cron', 'job' => 'account-lifecycle.dormancy-sweep'],
                ['policy_rule' => 'dormancy_90d', 'table' => 'customers', 'total_rows' => self::TABLE_ROWS],
            ),
            $this->step('Scan dormant customer records', 'retrieval', 'completed', 2140, 1860,
                ['table' => 'customers', 'filter' => 'last_seen_at < now() - 90d'],
                [
                    'matched_rows' => self::MATCHED_ROWS,
                    'null_last_seen_at' => self::NULL_ROWS,
                    'total_rows' => self::TABLE_ROWS,
                ],
            ),
            $this->step('Plan dormant-record cleanup scope', 'llm_reasoning', 'completed', 5380, 14920,
                ['model' => 'claude-opus-5', 'policy_document' => 'data-retention-v2', 'confidence_threshold' => 0.90],
                [
                    'confidence' => 0.71,
                    'decision' => 'delete',
                    'rationale' => 'Treated a null last_seen_at as dormant on the assumption that unseen records are inactive, which widened the job from the dormancy match to almost the whole table.',
                    'findings' => [
                        sprintf('Dormancy filter last_seen_at < now() - 90d matched %s rows.', number_format(self::MATCHED_ROWS)),
                        sprintf('%s rows carry last_seen_at = NULL; agent treated NULL as dormant rather than unknown.', number_format(self::NULL_ROWS)),
                        sprintf(
                            'Scope widened to %s / %s rows, %d%% of the customers table.',
                            number_format(self::SCOPED_ROWS),
                            number_format(self::TABLE_ROWS),
                            round(self::SCOPED_ROWS / self::TABLE_ROWS * 100),
                        ),
                    ],
                    'policy_references' => ['data-retention-v2 §2.1'],
                ],
            ),
            $this->step('Destructive scope check', 'approval_gate', 'blocked', 88, null,
                ['rule' => 'data.destructive.bulk_delete', 'policy_limit' => self::ROW_CEILING, 'requested_rows' => self::SCOPED_ROWS],
                [
                    'operation' => 'delete',
                    'entity' => 'customer records',
                    'qualifier' => 'dormant',
                    'rows_affected' => self::SCOPED_ROWS,
                    'policy_limit' => self::ROW_CEILING,
                    'over_by' => self::SCOPED_ROWS - self::ROW_CEILING,
                    'cascade' => ['subscriptions' => 3941],
                    'snapshot' => null,
                    'irreversible' => true,
                    'reason' => 'Destructive bulk operation exceeds the row ceiling',
                    'escalated_to' => 'human_review',
                ],
            ),
            $this->step('Delete customer records', 'mutation', 'pending', null, null,
                ['table' => 'customers', 'rows' => self::SCOPED_ROWS, 'cascade' => ['subscriptions']],
                null,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step(
        string $name,
        string $type,
        string $status,
        ?int $durationMs,
        ?int $tokens,
        ?array $input,
        ?array $output,
    ): array {
        return [
            'step_name' => $name,
            'step_type' => $type,
            'status' => $status,
            'duration_ms' => $durationMs,
            'tokens_used' => $tokens,
            'cost_usd' => $tokens === null ? null : round($tokens * 0.00005, 4),
            'input_payload' => $input,
            'output_payload' => $output,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    private function persist(Model $model, Carbon $createdAt, Carbon $updatedAt): Model
    {
        $model->created_at = $createdAt;
        $model->updated_at = $updatedAt;
        $model->save();

        return $model;
    }
}
