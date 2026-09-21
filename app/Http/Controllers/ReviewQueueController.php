<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewDecisionRequest;
use App\Http\Resources\ReviewQueueItemResource;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\ReviewDecision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Review Queue (Human-in-the-Loop Desk).
 *
 * Lists every approval still waiting on a person and records the verdict when
 * one is given. This is the only screen that writes: a decision moves the
 * approval, the run and the step the gate held back, and leaves an audit event
 * behind. The Runs Explorer and Run Inspector read those same rows.
 */
class ReviewQueueController extends Controller
{
    private const RESOLVED_LIMIT = 5;

    /** Window the desk's throughput figures are measured over. */
    private const ACTIVITY_DAYS = 7;

    public function index(): Response
    {
        $pending = $this->pending();

        return Inertia::render('Reviews/Index', [
            'queue' => $pending
                ->map(fn (ApprovalRequest $approval): array => (new ReviewQueueItemResource($approval))->resolve())
                ->values()
                ->all(),
            'stats' => $this->stats($pending),
            'resolved' => $this->recentlyResolved(),
            'reviewers' => $this->reviewerLoad($pending),
            'constellation' => $this->constellation($pending),
        ]);
    }

    public function decide(ReviewDecisionRequest $request, ApprovalRequest $approval): RedirectResponse
    {
        if ($approval->status !== 'pending') {
            return back()->withErrors([
                'decision' => 'This approval was already resolved by '.($approval->resolved_by ?? 'another reviewer').'.',
            ]);
        }

        $decision = $request->decision();
        $note = $request->note();
        $reviewer = $request->user()->name;

        DB::transaction(function () use ($approval, $decision, $note, $reviewer): void {
            $approval->forceFill([
                'status' => $decision->approvalStatus(),
                'resolved_by' => $reviewer,
                'resolution_notes' => $note,
                // Request-changes hands the run back rather than closing it out,
                // so it never gets a resolution timestamp.
                'resolved_at' => $decision->isTerminal() ? Carbon::now() : null,
            ])->save();

            $run = $approval->workflowRun;

            $run->forceFill([
                'status' => $decision->runStatus(),
                'error_message' => $decision === ReviewDecision::Reject
                    ? 'Rejected in human review. '.($note ?? $approval->summary)
                    : null,
            ])->save();

            $run->steps()
                ->where('status', 'pending')
                ->update(['status' => $decision->heldStepStatus(), 'updated_at' => Carbon::now()]);

            AuditEvent::create([
                'workspace_id' => $run->workflow->workspace_id,
                'workflow_run_id' => $run->id,
                'action' => $decision->auditAction(),
                'performed_by' => $reviewer,
                'details' => array_filter([
                    'run_key' => $run->run_key,
                    'approval_request_id' => $approval->id,
                    'decision' => $decision->value,
                    'risk_level' => $approval->risk_level,
                    'note' => $note,
                ], fn (mixed $value): bool => $value !== null),
            ]);
        });

        return redirect()->route('reviews.index');
    }

    // -----------------------------------------------------------------
    // Queue
    // -----------------------------------------------------------------

    /**
     * Severity band first, then risk score, then the longest wait — a critical
     * item never sits behind a low-risk one that happens to be older, and the
     * worst breach in a band leads it.
     *
     * @return Collection<int, ApprovalRequest>
     */
    private function pending(): Collection
    {
        return ApprovalRequest::query()
            ->where('status', 'pending')
            ->with(['workflowRun.workflow.workspace', 'workflowRun.steps', 'workflowRun.approvalRequests', 'workflowRun.auditEvents'])
            ->get()
            ->sort(fn (ApprovalRequest $a, ApprovalRequest $b): int => [
                ReviewQueueItemResource::rank($a), -ReviewQueueItemResource::score($a), $a->created_at,
            ] <=> [
                ReviewQueueItemResource::rank($b), -ReviewQueueItemResource::score($b), $b->created_at,
            ])
            ->values();
    }

    // -----------------------------------------------------------------
    // Desk context
    // -----------------------------------------------------------------

    /**
     * @param  Collection<int, ApprovalRequest>  $pending
     * @return array<string, mixed>
     */
    private function stats(Collection $pending): array
    {
        $since = Carbon::now()->subDays(self::ACTIVITY_DAYS);
        $now = Carbon::now();

        $waits = $pending->map(fn (ApprovalRequest $a): int => max(0, (int) $a->created_at->diffInSeconds($now)));
        $oldest = $waits->max() ?? 0;

        $decided = ApprovalRequest::query()
            ->whereIn('status', ['approved', 'rejected'])
            ->where('resolved_at', '>=', $since)
            ->get();

        return [
            'pending' => $pending->count(),
            'critical' => $pending->where('risk_level', 'critical')->count(),
            'average_wait_label' => $waits->isEmpty() ? '—' : $this->elapsedLabel((int) round($waits->avg())),
            'oldest_wait_label' => $waits->isEmpty() ? null : $this->elapsedLabel($oldest),
            'intercepted' => ApprovalRequest::query()->where('created_at', '>=', $since)->count(),
            'window_label' => self::ACTIVITY_DAYS.'D',
            'sla_label' => 'SLA '.ReviewQueueItemResource::SLA_HOURS.'h',
            'approved' => $decided->where('status', 'approved')->count(),
            'rejected' => $decided->where('status', 'rejected')->count(),
            'decisions' => $this->decisionSeries($decided, $since),
        ];
    }

    /**
     * Decisions per day across the activity window, for the header trace.
     *
     * @param  Collection<int, ApprovalRequest>  $decided
     * @return array<int, array<string, mixed>>
     */
    private function decisionSeries(Collection $decided, Carbon $since): array
    {
        $byDay = $decided->groupBy(fn (ApprovalRequest $a): string => $a->resolved_at->toDateString());
        $days = [];

        for ($cursor = $since->copy()->startOfDay(); $cursor <= Carbon::now(); $cursor->addDay()) {
            $onDay = $byDay->get($cursor->toDateString(), collect());

            $days[] = [
                'date' => $cursor->toDateString(),
                'label' => $cursor->format('M j'),
                'approved' => $onDay->where('status', 'approved')->count(),
                'rejected' => $onDay->where('status', 'rejected')->count(),
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentlyResolved(): array
    {
        return ApprovalRequest::query()
            ->whereIn('status', ['approved', 'rejected', 'changes_requested'])
            ->with('workflowRun.workflow')
            ->orderByDesc(DB::raw('COALESCE(resolved_at, updated_at)'))
            ->limit(self::RESOLVED_LIMIT)
            ->get()
            ->map(fn (ApprovalRequest $approval): array => [
                'id' => $approval->id,
                'run_id' => $approval->workflow_run_id,
                'run_key' => $approval->workflowRun?->run_key,
                'workflow' => $approval->workflowRun?->workflow?->name,
                'status' => $approval->status,
                'status_label' => str_replace('_', ' ', $approval->status),
                'tone' => $approval->status === 'approved' ? 'completed' : ($approval->status === 'rejected' ? 'critical' : 'review'),
                'by' => $approval->resolved_by,
                'at_label' => ($approval->resolved_at ?? $approval->updated_at)?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Decisions recorded per reviewer, with the signed-in user marked.
     *
     * @param  Collection<int, ApprovalRequest>  $pending
     * @return array<string, mixed>
     */
    private function reviewerLoad(Collection $pending): array
    {
        $decisions = ApprovalRequest::query()
            ->whereNotNull('resolved_by')
            ->selectRaw('resolved_by, COUNT(*) as total')
            ->groupBy('resolved_by')
            ->pluck('total', 'resolved_by');

        $me = request()->user()->name;
        $rows = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'is_you' => $user->name === $me,
                'decisions' => (int) ($decisions[$user->name] ?? 0),
            ]);

        // A reviewer who has signed something but no longer has an account
        // still owns those decisions; the ledger would otherwise lose them.
        foreach ($decisions as $name => $total) {
            if (! $rows->contains('name', $name)) {
                $rows->push(['name' => $name, 'is_you' => false, 'decisions' => (int) $total]);
            }
        }

        return [
            'rows' => $rows->sortByDesc('decisions')->values()->all(),
            'max' => max(1, (int) $rows->max('decisions')),
            'unassigned_label' => sprintf(
                '%d awaiting a human · unassigned',
                $pending->count(),
            ),
        ];
    }

    /**
     * Pending items as a small graph around the reviewer.
     *
     * @param  Collection<int, ApprovalRequest>  $pending
     * @return array<string, mixed>
     */
    private function constellation(Collection $pending): array
    {
        $items = $pending->values();
        $count = max(1, $items->count());

        $destructive = $items->filter(function (ApprovalRequest $approval): bool {
            $gate = $approval->workflowRun->steps->firstWhere('step_type', 'approval_gate');

            return (bool) (is_array($gate?->output_payload) ? ($gate->output_payload['irreversible'] ?? false) : false);
        })->count();

        $workflows = $items->pluck('workflowRun.workflow_id')->unique()->count();

        return [
            'nodes' => $items->map(function (ApprovalRequest $approval, int $index) use ($count): array {
                $angle = -M_PI / 2 + ($index / $count) * 2 * M_PI;

                return [
                    'run_key' => $approval->workflowRun->run_key,
                    'level' => $approval->risk_level,
                    'x' => round(50 + cos($angle) * 34, 2),
                    'y' => round(50 + sin($angle) * 32, 2),
                ];
            })->all(),
            'caption' => sprintf(
                '%d open · %d workflow%s · %d destructive',
                $items->count(),
                $workflows,
                $workflows === 1 ? '' : 's',
                $destructive,
            ),
        ];
    }

    private function elapsedLabel(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $minutes)
            : sprintf('%dm %02ds', $minutes, $seconds % 60);
    }
}
