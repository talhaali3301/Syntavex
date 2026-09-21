<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\RunStep;
use App\Models\User;
use App\Models\WorkflowRun;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Command Centre: the aggregate figures, the observation window and the
 * Decision Graph, all checked against the rows they are derived from.
 */
class CommandCentreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->where('email', 'admin@syntavex.local')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function props(string $uri = '/dashboard'): array
    {
        $response = $this->actingAs($this->user)->get($uri);
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    private function anchor(): Carbon
    {
        return Carbon::parse(WorkflowRun::query()->max('created_at'));
    }

    /**
     * The runs the given window covers, computed independently of the
     * controller so the assertions are not circular.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, WorkflowRun>
     */
    private function runsInWindow(int $days): \Illuminate\Database\Eloquent\Collection
    {
        $anchor = $this->anchor();

        return WorkflowRun::query()
            ->whereBetween('created_at', [$anchor->copy()->subDays($days), $anchor])
            ->get();
    }

    public function test_the_command_centre_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_it_renders_every_panel(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Dashboard/Index')
                ->has('kpis.total_executions')
                ->has('kpis.success_rate')
                ->has('kpis.avg_latency')
                ->has('kpis.cost_window')
                ->has('fleetTrust.components', 3)
                ->has('humanAttention', 3)
                ->has('recentRuns', 6)
                ->has('decisionGraph.clusters', 4)
                ->has('decisionGraph.legend', 3)
                ->has('governanceLedger')
                ->has('range.options', 4)
                ->where('workspace.name', 'Northstar Support')
                ->where('workspace.workflow_count', 4)
                ->where('workspace.active_workflow_count', 4)
        );
    }

    public function test_the_kpi_row_is_computed_from_the_runs_in_the_window(): void
    {
        $kpis = $this->props()['kpis'];
        $runs = $this->runsInWindow(14);

        $total = $runs->count();
        $completed = $runs->where('status', 'completed')->count();

        $this->assertSame($total, $kpis['total_executions']['value']);
        $this->assertSame(number_format($total), $kpis['total_executions']['display']);
        $this->assertSame(
            $runs->where('status', 'failed')->count().' failed · '
                .$runs->where('status', 'needs_review')->count().' in review',
            $kpis['total_executions']['caption'],
        );

        $this->assertSame(round(($completed / $total) * 100, 1), $kpis['success_rate']['value']);
        $this->assertSame("{$completed} of {$total} runs clean", $kpis['success_rate']['caption']);

        $this->assertSame(
            round($runs->avg('total_duration_ms') / 1000, 2),
            $kpis['avg_latency']['value'],
        );

        $this->assertSame(
            round((float) $runs->sum('total_cost_usd'), 4),
            $kpis['cost_window']['value'],
        );
        $this->assertSame('14d AI Cost', $kpis['cost_window']['label']);
        $this->assertSame("{$total} runs · last 14 days", $kpis['cost_window']['caption']);
    }

    public function test_the_spend_kpi_is_anchored_to_the_newest_run_not_wall_clock_now(): void
    {
        // Regression: this figure used to be "cost in the last 24h from now()",
        // so demoing the seeded data a day later showed $0.00 / 0 runs.
        $before = $this->props()['kpis'];

        $this->travel(45)->days();

        $after = $this->props()['kpis'];

        $this->assertSame($before, $after);
        $this->assertGreaterThan(0, $after['cost_window']['value']);
        $this->assertGreaterThan(0, $after['total_executions']['value']);
    }

    public function test_the_range_selector_re_windows_every_figure(): void
    {
        $day = $this->props('/dashboard?range=24h');
        $month = $this->props('/dashboard?range=30d');

        $this->assertSame('24h', $day['range']['key']);
        $this->assertSame(1, $day['range']['days']);
        $this->assertSame('24h AI Cost', $day['kpis']['cost_window']['label']);

        $this->assertSame($this->runsInWindow(1)->count(), $day['kpis']['total_executions']['value']);
        $this->assertSame($this->runsInWindow(30)->count(), $month['kpis']['total_executions']['value']);

        $this->assertLessThan(
            $month['kpis']['total_executions']['value'],
            $day['kpis']['total_executions']['value'],
        );

        // The 30-day window reaches the whole seeded dataset.
        $this->assertSame(WorkflowRun::query()->count(), $month['kpis']['total_executions']['value']);

        // Recent Runs and the graph follow the window too.
        $this->assertCount(min(6, $this->runsInWindow(1)->count()), $day['recentRuns']);
        $this->assertSame(
            $this->runsInWindow(1)->count(),
            $this->graphRunCount($day['decisionGraph']),
        );
    }

    public function test_an_unknown_range_falls_back_to_the_default(): void
    {
        $default = $this->props('/dashboard');

        foreach (['/dashboard?range=bogus', '/dashboard?range[]=7d', '/dashboard?range=99d'] as $uri) {
            $props = $this->props($uri);

            $this->assertSame('14d', $props['range']['key']);
            $this->assertSame($default['kpis'], $props['kpis']);
        }
    }

    public function test_the_fleet_trust_index_follows_its_documented_formula(): void
    {
        $trust = $this->props()['fleetTrust'];
        $runs = $this->runsInWindow(14);
        $total = $runs->count();

        $reliability = $runs->where('status', 'completed')->count() / $total;
        $overrideRate = $runs->whereIn('status', ['needs_review', 'failed'])->count() / $total;
        $costEfficiency = min(1.0, 0.75 / (float) $runs->avg('total_cost_usd'));

        $expected = (int) round(
            100 * (0.55 * $reliability + 0.30 * (1 - $overrideRate) + 0.15 * $costEfficiency)
        );

        $this->assertSame($expected, $trust['score']);
        $this->assertContains($trust['band'], ['strong', 'steady', 'watch', 'critical']);

        $components = collect($trust['components'])->keyBy('label');
        $this->assertSame(number_format($reliability * 100, 1).'%', $components['Reliability']['display']);
        $this->assertSame(number_format((1 - $overrideRate) * 100, 1).'%', $components['Autonomy']['display']);
        $this->assertSame(number_format($costEfficiency * 100, 1).'%', $components['Cost efficiency']['display']);
        $this->assertSame(['55%', '30%', '15%'], array_column($trust['components'], 'weight'));
    }

    public function test_human_attention_lists_pending_approvals_most_severe_first(): void
    {
        $attention = $this->props()['humanAttention'];

        $this->assertSame(
            ApprovalRequest::query()->where('status', 'pending')->count(),
            count($attention),
        );

        $this->assertSame(['critical', 'medium', 'low'], array_column($attention, 'risk_level'));

        $flagship = $attention[0];
        $run = WorkflowRun::query()->where('run_key', '8421')->firstOrFail();

        $this->assertSame('8421', $flagship['run_key']);
        $this->assertSame($run->id, $flagship['run_id']);
        $this->assertSame('CRITICAL', $flagship['risk_label']);
        $this->assertSame('Priority Refund Review', $flagship['workflow']);
        $this->assertSame('Policy ceiling check', $flagship['step_name']);
        $this->assertSame('$0.6950', $flagship['cost_label']);
        $this->assertSame(
            $run->approvalRequests()->firstOrFail()->summary,
            $flagship['summary'],
        );
    }

    public function test_the_governance_ledger_counts_real_rows(): void
    {
        $ledger = $this->props()['governanceLedger'];

        $resolved = ApprovalRequest::query()->where('status', '!=', 'pending')->count();
        $autoApproved = RunStep::query()
            ->where('step_type', 'approval_gate')
            ->where('status', 'completed')
            ->count();
        $policies = RunStep::query()
            ->whereIn('step_type', ['llm_reasoning', 'approval_gate'])
            ->count();

        $this->assertSame($resolved + $autoApproved, $ledger['decisions_signed']['value']);
        $this->assertSame(
            "{$resolved} human · {$autoApproved} auto-approved",
            $ledger['decisions_signed']['caption'],
        );
        $this->assertSame($policies, $ledger['policies_evaluated']['value']);
        $this->assertSame(
            number_format(AuditEvent::query()->count()).' events sealed',
            $ledger['audit_export']['caption'],
        );
    }

    public function test_the_ledger_stays_cumulative_when_the_window_narrows(): void
    {
        // An audit trail does not shrink because you looked at less of it.
        $this->assertSame(
            $this->props('/dashboard')['governanceLedger'],
            $this->props('/dashboard?range=24h')['governanceLedger'],
        );
    }

    public function test_the_decision_graph_places_every_run_in_the_window(): void
    {
        $graph = $this->props()['decisionGraph'];

        $this->assertSame($this->runsInWindow(14)->count(), $this->graphRunCount($graph));

        $keys = [];

        foreach ($graph['clusters'] as $cluster) {
            $this->assertSame(
                count($cluster['nodes']) + ($cluster['core'] === null ? 0 : 1),
                $cluster['run_count'],
            );
            $this->assertSame(
                "{$cluster['at_risk_count']}/{$cluster['run_count']} at risk",
                $cluster['risk_label'],
            );

            foreach ($cluster['nodes'] as $node) {
                $keys[] = $node['run_key'];
                $this->assertFalse($node['flagged'], 'Only a cluster core is ever flagged.');
            }

            if ($cluster['core'] !== null) {
                $keys[] = $cluster['core']['run_key'];
            }
        }

        $this->assertSame($keys, array_values(array_unique($keys)), 'A run was drawn twice.');
    }

    public function test_the_decision_graph_flags_the_cluster_holding_the_open_approval(): void
    {
        $graph = $this->props()['decisionGraph'];
        $flagged = collect($graph['clusters'])->firstWhere('flagged', true);

        $this->assertNotNull($flagged);
        $this->assertSame('Priority Refund Review', $flagged['label']);
        $this->assertSame('8421', $flagged['core']['run_key']);
        $this->assertTrue($flagged['core']['flagged']);

        $this->assertNotNull($graph['callout']);
        $this->assertSame('8421', $graph['callout']['run_key']);
        $this->assertStringContainsString('critical approval open on #8421', $graph['callout']['detail']);
        $this->assertStringContainsString(
            "{$flagged['at_risk_count']} of {$flagged['run_count']} runs unresolved",
            $graph['callout']['detail'],
        );

        // Every other cluster is tethered back to the flagged one.
        $this->assertCount(count($graph['clusters']) - 1, $graph['links']);
    }

    public function test_the_graph_legend_counts_the_runs_in_the_window(): void
    {
        $graph = $this->props()['decisionGraph'];
        $runs = $this->runsInWindow(14);

        foreach ($graph['legend'] as $entry) {
            $this->assertSame($runs->where('status', $entry['status'])->count(), $entry['count']);
        }

        $this->assertSame($runs->count(), array_sum(array_column($graph['legend'], 'count')));
    }

    public function test_recent_runs_are_the_newest_runs_with_their_derived_columns(): void
    {
        $recent = $this->props()['recentRuns'];
        $expected = WorkflowRun::query()
            ->orderByDesc('created_at')
            ->limit(6)
            ->pluck('run_key')
            ->all();

        $this->assertSame($expected, array_column($recent, 'run_key'));

        $flagship = $recent[0];
        $this->assertSame('8421', $flagship['run_key']);
        $this->assertSame('refund-reviewer', $flagship['agent']);
        // The run is parked on the blocked gate, not its last step.
        $this->assertSame('Policy ceiling check', $flagship['step']);
        $this->assertSame('5.85s', $flagship['latency_label']);
        $this->assertSame('13,900', $flagship['tokens_label']);
        $this->assertSame('$0.6950', $flagship['cost_label']);
        $this->assertSame('Needs review', $flagship['status_label']);
        $this->assertSame('review', $flagship['tone']);
    }

    public function test_the_fleet_pulse_reads_the_newest_run_and_goes_idle_without_one(): void
    {
        $newest = WorkflowRun::query()->max('created_at');

        $this->actingAs($this->user)->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('pulse.live', true)
                ->where('pulse.latest_run_label', Carbon::parse($newest)->format('M j · H:i'))
        );

        AuditEvent::query()->delete();
        ApprovalRequest::query()->delete();
        RunStep::query()->delete();
        WorkflowRun::query()->delete();

        $this->actingAs($this->user)->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('pulse.live', false)
                ->where('pulse.latest_run_label', 'No runs recorded')
        );
    }

    public function test_the_rail_badge_count_tracks_the_pending_approvals(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $expected = ApprovalRequest::query()->where('status', 'pending')->count();

        $this->assertGreaterThan(0, $expected);
        $response->assertInertia(
            fn (AssertableInertia $page) => $page->where('pendingReviews', $expected)
        );

        ApprovalRequest::query()->update(['status' => 'approved']);

        $this->actingAs($this->user)->get('/dashboard')->assertInertia(
            fn (AssertableInertia $page) => $page->where('pendingReviews', 0)
        );
    }

    /**
     * Total runs drawn on the graph, cores included.
     *
     * @param  array<string, mixed>  $graph
     */
    private function graphRunCount(array $graph): int
    {
        $count = 0;

        foreach ($graph['clusters'] as $cluster) {
            $count += count($cluster['nodes']) + ($cluster['core'] === null ? 0 : 1);
        }

        return $count;
    }
}
