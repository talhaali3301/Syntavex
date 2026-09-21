<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CoverTest extends TestCase
{
    use RefreshDatabase;

    private function props(): array
    {
        $response = $this->get('/');
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    private function signal(array $signals, string $key): array
    {
        return collect($signals)->firstWhere('key', $key);
    }

    public function test_it_is_the_entry_route_and_needs_no_session(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page->component('Cover/Index')
        );

        $this->assertGuest();
    }

    public function test_it_reports_the_workspace_the_runs_actually_belong_to(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->firstOrFail();
        $props = $this->props();

        $this->assertSame($workspace->name, $props['workspace']['name']);
        $this->assertSame($workspace->slug, $props['workspace']['slug']);
        $this->assertSame(
            Workflow::query()->where('workspace_id', $workspace->id)->count(),
            $props['workspace']['workflow_count'],
        );
    }

    public function test_the_signals_are_the_aggregates_of_the_seeded_rows(): void
    {
        $this->seed(DatabaseSeeder::class);

        $total = WorkflowRun::query()->count();
        $completed = WorkflowRun::query()->where('status', 'completed')->count();
        $intervened = WorkflowRun::query()->whereIn('status', ['needs_review', 'failed'])->count();

        $signals = $this->props()['signals'];

        $this->assertSame(number_format($total), $this->signal($signals, 'runs')['display']);
        $this->assertSame(
            $intervened.' needed a human',
            $this->signal($signals, 'runs')['caption'],
        );
        $this->assertSame(
            number_format(($completed / $total) * 100, 1).'%',
            $this->signal($signals, 'success')['display'],
        );
        $this->assertSame(
            number_format(AuditEvent::query()->count()),
            $this->signal($signals, 'decisions')['display'],
        );
        $this->assertSame(
            number_format(ApprovalRequest::query()->where('status', 'pending')->count()),
            $this->signal($signals, 'pending')['display'],
        );
    }

    public function test_the_constellation_places_one_node_per_workflow_inside_the_canvas(): void
    {
        $this->seed(DatabaseSeeder::class);

        $constellation = $this->props()['constellation'];

        $this->assertCount(Workflow::query()->count(), $constellation['nodes']);
        $this->assertSame(number_format(WorkflowRun::query()->count()), $constellation['core_display']);

        foreach ($constellation['nodes'] as $node) {
            $this->assertGreaterThanOrEqual(0, $node['x'] - $node['radius']);
            $this->assertLessThanOrEqual(100, $node['x'] + $node['radius']);
            $this->assertGreaterThanOrEqual(0, $node['y'] - $node['radius']);
            $this->assertLessThanOrEqual(100, $node['y'] + $node['radius']);
            $this->assertContains($node['tone'], ['completed', 'review', 'critical', 'info']);
        }
    }

    public function test_the_busiest_workflow_orbits_closest_to_the_core(): void
    {
        $this->seed(DatabaseSeeder::class);

        $nodes = collect($this->props()['constellation']['nodes']);

        $this->assertSame(
            $nodes->sortByDesc('runs')->pluck('id')->all(),
            $nodes->sortBy('orbit')->pluck('id')->all(),
        );
    }

    public function test_it_renders_against_an_empty_database(): void
    {
        $props = $this->props();

        $this->assertNull($props['workspace']);
        $this->assertSame('0', $this->signal($props['signals'], 'runs')['display']);
        $this->assertSame('—', $this->signal($props['signals'], 'success')['display']);
        $this->assertSame([], $props['constellation']['nodes']);
        $this->assertFalse($props['pulse']['live']);
        $this->assertSame('No runs recorded', $props['pulse']['latest_run_label']);
    }

    public function test_the_entry_cards_point_at_the_screens_they_describe(): void
    {
        $this->seed(DatabaseSeeder::class);

        $hrefs = collect($this->props()['entries'])->pluck('href')->all();

        $this->assertSame(['/dashboard', '/runs', '/reviews'], $hrefs);
    }
}
