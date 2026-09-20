<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Runs Explorer: filtering, pagination and CSV export, exercised against the
 * real seeded dataset rather than fixtures, so the assertions track what the
 * screen actually shows in a demo.
 */
class RunsExplorerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->user = User::query()->where('email', 'admin@syntavex.local')->firstOrFail();
    }

    /** The id column of the newest run, which the seeder pins to key #8421. */
    private function flagship(): WorkflowRun
    {
        return WorkflowRun::query()->where('run_key', '8421')->firstOrFail();
    }

    /** The newest run in the workspace, which the screen's date window hangs off. */
    private function anchor(): Carbon
    {
        return Carbon::parse(WorkflowRun::query()->max('created_at'));
    }

    /**
     * A window of $days ending $endingDaysAgo days before the newest run.
     * Ranges are derived from the data rather than typed in, so they keep
     * covering the seeded runs however long after the seed the suite runs.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(int $days, int $endingDaysAgo = 0): array
    {
        $to = $this->anchor()->subDays($endingDaysAgo)->startOfDay();

        return [$to->copy()->subDays($days - 1), $to];
    }

    /** The same window, as a `from=...&to=...` query string. */
    private function windowQuery(int $days, int $endingDaysAgo = 0): string
    {
        [$from, $to] = $this->window($days, $endingDaysAgo);

        return sprintf('from=%s&to=%s', $from->toDateString(), $to->toDateString());
    }

    private function visit(string $uri): TestResponse
    {
        return $this->actingAs($this->user)->get($uri);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(string $uri): array
    {
        $response = $this->visit($uri);
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    public function test_the_runs_explorer_requires_authentication(): void
    {
        $this->get('/runs')->assertRedirect('/login');
        $this->get('/runs/export')->assertRedirect('/login');
    }

    public function test_it_renders_the_explorer_with_seeded_runs(): void
    {
        $this->visit('/runs')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Runs/Index')
                ->has('runs')
                ->has('statusChips', 4)
                ->has('distribution.segments', 3)
                ->has('volume.bars', 14)
                ->where('workspace.slug', 'northstar-support')
        );
    }

    public function test_status_chip_counts_agree_with_the_rows_each_chip_returns(): void
    {
        $chips = collect($this->props('/runs')['statusChips'])->keyBy('key');

        // The ALL chip is the sum of the three status chips...
        $this->assertSame(
            $chips['completed']['count'] + $chips['needs_review']['count'] + $chips['failed']['count'],
            $chips['all']['count'],
        );

        // ...and each chip promises exactly what clicking it returns.
        foreach (['completed', 'needs_review', 'failed'] as $status) {
            $filtered = $this->props("/runs?status={$status}");

            $this->assertSame(
                $chips[$status]['count'],
                $filtered['pagination']['total'],
                "The {$status} chip count does not match the filtered result total.",
            );

            foreach ($filtered['runs'] as $run) {
                $this->assertSame($status, $run['status']);
            }
        }
    }

    public function test_the_distribution_strip_is_computed_from_the_filtered_runs(): void
    {
        $distribution = $this->props('/runs')['distribution'];
        $segments = collect($distribution['segments'])->keyBy('status');

        $this->assertSame(
            $segments->sum('count'),
            $distribution['total'],
        );

        $expectedSuccess = round(($segments['completed']['count'] / $distribution['total']) * 100, 1);
        $this->assertSame($expectedSuccess, $distribution['success_rate']);
        $this->assertStringContainsString('% success', $distribution['success_label']);

        foreach ($segments as $segment) {
            $this->assertSame(
                round(($segment['count'] / $distribution['total']) * 100, 1),
                $segment['percent'],
            );
        }
    }

    public function test_counts_are_pluralised_in_the_copy_they_appear_in(): void
    {
        // The seeder scatters run timestamps at random across the last 14
        // days, so any window over the seeded data holds an unpredictable
        // number of runs. Park a known handful on consecutive days a year
        // back, well clear of the seeded band, and read the copy off those.
        $origin = $this->anchor()->subYear()->startOfDay();

        $parked = WorkflowRun::query()
            ->whereKeyNot($this->flagship()->getKey())
            ->orderBy('id')
            ->take(3)
            ->pluck('id');

        foreach ($parked as $offset => $id) {
            WorkflowRun::query()->whereKey($id)->update([
                'created_at' => $origin->copy()->addDays($offset)->setTime(9, 0),
            ]);
        }

        $first = $origin->toDateString();
        $last = $origin->copy()->addDays(2)->toDateString();
        $empty = $origin->copy()->subDay()->toDateString();

        // One run, one day: both halves singular.
        $this->assertSame(
            '1 run · 1 day',
            $this->props("/runs?from={$first}&to={$first}")['distribution']['scope_label'],
        );

        // Three runs over three days: both halves plural.
        $this->assertSame(
            '3 runs · 3 days',
            $this->props("/runs?from={$first}&to={$last}")['distribution']['scope_label'],
        );

        // Zero is plural, and the empty day next door proves the parked runs
        // are the only thing being counted.
        $this->assertSame(
            '0 runs · 1 day',
            $this->props("/runs?from={$empty}&to={$empty}")['distribution']['scope_label'],
        );

        // A singular count beside a plural window, off the default 14 days.
        $this->assertSame(
            '1 run · 14 days',
            $this->props('/runs?search=8421')['distribution']['scope_label'],
        );
    }

    public function test_the_distribution_window_reports_the_selected_range_not_the_default(): void
    {
        // Regression: the strip used to read "N runs · 14 days" no matter how
        // wide the date filter actually was.
        $this->assertSame(14, $this->props('/runs')['distribution']['window_days']);
        $this->assertSame(
            90,
            $this->props('/runs?'.$this->windowQuery(90, 1))['distribution']['window_days'],
        );
        $this->assertSame(
            6,
            $this->props('/runs?'.$this->windowQuery(6, 10))['distribution']['window_days'],
        );
    }

    public function test_the_rolling_cost_window_follows_the_selected_range(): void
    {
        [, $to] = $this->window(7, 4);

        $props = $this->props('/runs?'.$this->windowQuery(7, 4));

        // Regression: the window used to hang off the newest run in the whole
        // workspace, so every historic range read "$0.00 / 3d".
        $expected = (float) WorkflowRun::query()
            ->whereBetween('created_at', [
                $to->copy()->endOfDay()->subDays(3),
                $to->copy()->endOfDay(),
            ])
            ->sum('total_cost_usd');

        $this->assertSame(
            '$'.number_format($expected, 2).' / 3d',
            $props['distribution']['cost_window_label'],
        );
    }

    public function test_the_workflow_filter_narrows_to_that_workflow(): void
    {
        $workflow = Workflow::query()->where('slug', 'priority-refund-review')->firstOrFail();
        $props = $this->props("/runs?workflow={$workflow->id}");

        $this->assertGreaterThan(0, $props['pagination']['total']);

        foreach ($props['runs'] as $run) {
            $this->assertSame($workflow->name, $run['workflow']);
        }

        $this->assertSame($workflow->id, $props['filters']['workflow']);
    }

    public function test_search_matches_both_the_run_key_and_the_workflow_name(): void
    {
        $byKey = $this->props('/runs?search=8421');
        $this->assertSame(1, $byKey['pagination']['total']);
        $this->assertSame('8421', $byKey['runs'][0]['run_key']);

        $byWorkflow = $this->props('/runs?search=Refund');
        $this->assertGreaterThan(1, $byWorkflow['pagination']['total']);

        foreach ($byWorkflow['runs'] as $run) {
            $this->assertStringContainsStringIgnoringCase('refund', $run['workflow']);
        }
    }

    public function test_a_search_with_no_matches_renders_an_empty_result_set(): void
    {
        $props = $this->props('/runs?search=no-such-trace-id');

        $this->assertSame([], $props['runs']);
        $this->assertSame(0, $props['pagination']['total']);
        $this->assertSame('avg —', $props['distribution']['avg_duration_label']);
        $this->assertSame('— / 3d', $props['distribution']['cost_window_label']);
    }

    public function test_a_date_range_outside_the_data_returns_nothing(): void
    {
        $props = $this->props('/runs?from=2020-01-01&to=2020-01-31');

        $this->assertSame(0, $props['pagination']['total']);
        $this->assertSame(0, $props['showing']['total']);
    }

    public function test_an_inverted_date_range_is_swapped_rather_than_returning_nothing(): void
    {
        [$from, $to] = $this->window(6, 10);

        $forwards = $this->props(sprintf('/runs?from=%s&to=%s', $from->toDateString(), $to->toDateString()));
        $backwards = $this->props(sprintf('/runs?from=%s&to=%s', $to->toDateString(), $from->toDateString()));

        $this->assertSame($forwards['pagination']['total'], $backwards['pagination']['total']);
        $this->assertSame($forwards['filters']['range_label'], $backwards['filters']['range_label']);
    }

    public function test_unknown_filter_values_fall_back_to_the_defaults(): void
    {
        $props = $this->props('/runs?status=not-a-status&workflow=999999');

        $this->assertSame('all', $props['filters']['status']);
        $this->assertNull($props['filters']['workflow']);
        $this->assertTrue($props['filters']['is_default']);
    }

    public function test_array_query_parameters_do_not_break_the_screen(): void
    {
        $props = $this->props('/runs?status[]=failed&search[]=8421');

        $this->assertSame('all', $props['filters']['status']);
        $this->assertSame('', $props['filters']['search']);
    }

    public function test_pagination_walks_every_filtered_run_exactly_once(): void
    {
        $first = $this->props('/runs');
        $lastPage = $first['pagination']['last_page'];
        $total = $first['pagination']['total'];

        $this->assertGreaterThan(1, $lastPage, 'The seeded dataset should span several pages.');

        $seen = [];

        for ($page = 1; $page <= $lastPage; $page++) {
            $props = $this->props("/runs?page={$page}");

            $this->assertSame($page, $props['pagination']['current_page']);
            $this->assertSame($total, $props['pagination']['total']);

            foreach ($props['runs'] as $run) {
                $seen[] = $run['run_key'];
            }
        }

        $this->assertCount($total, $seen);
        $this->assertSame($seen, array_values(array_unique($seen)), 'A run appeared on two pages.');
    }

    public function test_pagination_edges_expose_the_right_links(): void
    {
        $first = $this->props('/runs')['pagination'];
        $this->assertNull($first['prev_url']);
        $this->assertNotNull($first['next_url']);
        $this->assertSame(11, $first['per_page']);
        $this->assertSame(1, $first['from']);

        $last = $this->props('/runs?page='.$first['last_page'])['pagination'];
        $this->assertNotNull($last['prev_url']);
        $this->assertNull($last['next_url']);
        $this->assertSame($last['total'], $last['to']);
    }

    public function test_pagination_carries_the_active_filters_into_its_links(): void
    {
        $pagination = $this->props('/runs?status=completed')['pagination'];

        $this->assertStringContainsString('status=completed', (string) $pagination['next_url']);
    }

    public function test_rows_are_ordered_newest_first(): void
    {
        $runs = $this->props('/runs')['runs'];
        $timestamps = array_column($runs, 'started_at');
        $sorted = $timestamps;
        rsort($sorted);

        $this->assertSame($sorted, $timestamps);
    }

    public function test_the_volume_chart_is_anchored_to_the_newest_run(): void
    {
        $anchor = $this->anchor();
        $volume = $this->props('/runs')['volume'];

        $this->assertCount(14, $volume['bars']);
        $this->assertSame($anchor->toDateString(), end($volume['bars'])['day']);
        $this->assertTrue(end($volume['bars'])['is_latest']);

        foreach ($volume['bars'] as $bar) {
            $expected = WorkflowRun::query()
                ->whereBetween('created_at', [
                    Carbon::parse($bar['day'])->startOfDay(),
                    Carbon::parse($bar['day'])->endOfDay(),
                ])
                ->count();

            $this->assertSame($expected, $bar['count'], "Bar {$bar['day']} does not match the run count for that day.");
        }
    }

    public function test_the_flagship_run_renders_its_seeded_values_end_to_end(): void
    {
        $run = $this->flagship();
        $props = $this->props('/runs?search=8421');

        $this->assertSame($run->id, $props['expandedRunId'], 'The flagship run should open inline on load.');

        $row = $props['runs'][0];

        // Row columns, straight off the seeded totals.
        $this->assertSame('8421', $row['run_key']);
        $this->assertSame('Priority Refund Review', $row['workflow']);
        $this->assertSame('needs_review', $row['status']);
        $this->assertSame('NEEDS REVIEW', $row['status_label']);
        $this->assertSame('refund-reviewer', $row['agent']);
        $this->assertSame('Refund $120.00 · ticket ZD-40219', $row['objective']);

        $this->assertSame(5850, $row['duration_ms']);
        $this->assertSame('5.85s', $row['duration_label']);
        $this->assertSame(13900, $row['tokens']);
        $this->assertSame('13.9k', $row['tokens_label']);
        $this->assertSame(0.695, $row['cost_usd']);
        $this->assertSame('$0.70', $row['cost_label']);
        $this->assertSame($run->created_at->format('M j · H:i:s'), $row['started_label']);

        // ...and the same numbers on the model they were read from.
        $this->assertSame(5850, $run->total_duration_ms);
        $this->assertSame(13900, $run->total_tokens);
        $this->assertSame('0.6950', $run->total_cost_usd);

        // Expansion panel.
        $expansion = $row['expansion'];
        $this->assertSame(5, $expansion['steps']);
        $this->assertSame(2, $expansion['tool_calls']);
        $this->assertSame(1, $expansion['policy_hits']);
        $this->assertSame('claude-opus-5', $expansion['model']);
        $this->assertCount(5, $expansion['trace']);

        $this->assertSame('critical', $expansion['risk']['level']);
        $this->assertSame(0.79, $expansion['risk']['score']);
        $this->assertSame('automated_refund_ceiling breached', $expansion['risk']['rule']);

        $this->assertSame('Refund $120.00 approved', $expansion['decision']['headline']);
        $this->assertSame(0.94, $expansion['decision']['confidence']);
        $this->assertFalse($expansion['decision']['signed']);
        $this->assertSame('unsigned', $expansion['decision']['signed_label']);

        // The approval summary is shown verbatim, and its $120 matches the
        // amount the gate actually blocked.
        $approval = $run->approvalRequests()->firstOrFail();
        $this->assertSame($approval->summary, $expansion['decision']['summary']);
        $this->assertStringContainsString('$120', $expansion['decision']['summary']);

        $gate = $run->steps()->where('step_type', 'approval_gate')->firstOrFail();
        $this->assertEquals(120.0, $gate->input_payload['requested_amount']);
        $this->assertEquals(100.0, $gate->input_payload['policy_limit']);
        $this->assertEquals(20.0, $gate->output_payload['over_by']);
    }

    public function test_the_flagship_narrative_quotes_one_consistent_refund_amount(): void
    {
        $run = $this->flagship();
        $reasoning = $run->steps()->where('step_type', 'llm_reasoning')->firstOrFail();
        $webhook = $run->steps()->where('step_type', 'webhook')->firstOrFail();

        $this->assertEquals(120.0, $webhook->output_payload['requested_amount']);
        $this->assertStringContainsString('$120 credit', $reasoning->output_payload['drafted_apology']);
        $this->assertStringContainsString(
            '$120 refund above the $100 policy ceiling',
            $run->approvalRequests()->firstOrFail()->summary,
        );

        // The outage date in the rationale is derived from the run, not typed
        // in, so it never drifts away from the dates shown on screen.
        $this->assertStringContainsString(
            $run->created_at->copy()->subDay()->toDateString(),
            $reasoning->output_payload['rationale'],
        );
    }

    public function test_a_goodwill_credit_reads_as_a_refund_with_no_ticket(): void
    {
        // Regression: this run used to fall back to a step name for its
        // objective and render "Approve_with_flagd by agent" as its decision.
        $props = $this->props('/runs?status=needs_review');
        $row = collect($props['runs'])->firstWhere('objective', 'Refund $65.00 · no ticket');

        $this->assertNotNull($row, 'The goodwill run should describe itself as a ticketless refund.');
        $this->assertSame('Approved with flag by agent', $row['expansion']['decision']['headline']);
    }

    public function test_the_export_streams_every_filtered_run(): void
    {
        $total = $this->props('/runs')['pagination']['total'];

        $response = $this->visit('/runs/export');
        $response->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('syntavex-runs-', $response->headers->get('content-disposition'));

        $rows = $this->csvRows($response);

        $this->assertSame(
            ['run_key', 'workflow', 'status', 'started_at', 'duration_ms', 'tokens', 'cost_usd', 'error_message'],
            array_shift($rows),
        );

        // Every page, not just the one on screen.
        $this->assertCount($total, $rows);
        $this->assertGreaterThan(count($this->props('/runs')['runs']), count($rows));
    }

    public function test_the_export_matches_the_current_filtered_state(): void
    {
        $queries = ['status=failed', 'status=completed', 'search=8421', $this->windowQuery(6, 10)];

        foreach ($queries as $query) {
            $expected = $this->props("/runs?{$query}")['pagination']['total'];
            $rows = $this->csvRows($this->visit("/runs/export?{$query}"));
            array_shift($rows);

            $this->assertCount($expected, $rows, "The export does not match the screen for ?{$query}.");
        }
    }

    public function test_the_exported_flagship_row_carries_its_seeded_values(): void
    {
        $rows = $this->csvRows($this->visit('/runs/export?search=8421'));
        array_shift($rows);

        $this->assertCount(1, $rows);
        [$key, $workflow, $status, $startedAt, $duration, $tokens, $cost, $error] = $rows[0];

        $run = $this->flagship();
        $this->assertSame('8421', $key);
        $this->assertSame('Priority Refund Review', $workflow);
        $this->assertSame('needs_review', $status);
        $this->assertSame($run->created_at->toIso8601String(), $startedAt);
        $this->assertSame('5850', $duration);
        $this->assertSame('13900', $tokens);
        $this->assertSame('0.6950', $cost);
        $this->assertSame('', $error);
    }

    public function test_the_export_records_the_error_message_on_failed_runs(): void
    {
        $rows = $this->csvRows($this->visit('/runs/export?status=failed'));
        array_shift($rows);

        foreach ($rows as $row) {
            $this->assertSame('failed', $row[2]);
            $this->assertNotSame('', $row[7], 'A failed run was exported with no error message.');
        }
    }

    public function test_an_unknown_run_id_is_a_404_rather_than_an_error(): void
    {
        $this->visit('/runs/999999')->assertNotFound();
    }

    public function test_the_export_route_is_never_read_as_a_run_id(): void
    {
        $response = $this->visit('/runs/export')->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_the_review_queue_placeholder_resolves(): void
    {
        $this->visit('/reviews')->assertInertia(
            fn (AssertableInertia $page) => $page->component('Reviews/Index')
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csvRows(TestResponse $response): array
    {
        $csv = $response->streamedContent();

        $rows = [];
        $handle = fopen('php://memory', 'r+b');
        fwrite($handle, $csv);
        rewind($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }

            $rows[] = array_map(fn (?string $value): string => (string) $value, $row);
        }

        fclose($handle);

        return $rows;
    }
}
