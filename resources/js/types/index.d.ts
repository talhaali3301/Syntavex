export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};

/* -------------------------------------------------------------------------
 * Command Centre
 * ---------------------------------------------------------------------- */

/** Maps onto the Phase 1 `status.*` colour tokens in tailwind.config.js. */
export type StatusTone = 'completed' | 'review' | 'critical' | 'info';

export type RunStatus = 'completed' | 'needs_review' | 'failed' | 'running';

export type RiskLevel = 'low' | 'medium' | 'high' | 'critical';

export interface Workspace {
    name: string;
    slug: string;
    tier: string;
    workflow_count: number;
    active_workflow_count: number;
}

export interface KpiMetric {
    value: number;
    display: string;
    caption: string;
}

export interface DashboardKpis {
    total_executions: KpiMetric;
    success_rate: KpiMetric;
    avg_latency: KpiMetric;
    cost_24h: KpiMetric;
}

export interface FleetTrustComponent {
    label: string;
    display: string;
    weight: string;
}

export interface FleetTrust {
    score: number;
    band: 'strong' | 'steady' | 'watch' | 'critical' | 'unknown';
    components: FleetTrustComponent[];
}

export interface ApprovalRequestSummary {
    id: number;
    risk_level: RiskLevel;
    risk_label: string;
    tone: StatusTone;
    summary: string;
    status: string;
    step_name: string | null;
    step_type: string | null;
    step_order: number | null;
    run_id: number | null;
    run_key: string | null;
    run_status: RunStatus | null;
    workflow: string | null;
    cost_usd: number | null;
    cost_label: string;
    waiting_since: string | null;
    waiting_label: string | null;
}

export interface RunListItem {
    id: number;
    run_key: string;
    workflow: string | null;
    workflow_slug: string | null;
    agent: string;
    step: string | null;
    step_type: string | null;
    status: RunStatus;
    status_label: string;
    tone: StatusTone;
    latency_ms: number | null;
    latency_label: string;
    tokens: number | null;
    tokens_label: string;
    cost_usd: number | null;
    cost_label: string;
    error_message: string | null;
    created_at: string | null;
    created_at_label: string | null;
}

export interface DecisionGraphNode {
    id: number;
    run_key: string;
    status: RunStatus;
    tone: StatusTone;
    at_risk: boolean;
    flagged: boolean;
    cost_label: string;
    x: number;
    y: number;
    r: number;
}

export interface DecisionGraphCluster {
    id: number;
    label: string;
    slug: string;
    x: number;
    y: number;
    radius: number;
    run_count: number;
    at_risk_count: number;
    risk_ratio: number;
    risk_label: string;
    flagged: boolean;
    /** The run that triggered the flag, drawn at the cluster's core. */
    core: DecisionGraphNode | null;
    nodes: DecisionGraphNode[];
}

export interface DecisionGraphLink {
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    hot: boolean;
}

export interface DecisionGraphCallout {
    cluster_id: number;
    title: string;
    workflow: string;
    x: number;
    y: number;
    radius: number;
    detail: string;
    run_key: string | null;
}

export interface DecisionGraphLegendItem {
    status: RunStatus;
    label: string;
    tone: StatusTone;
    count: number;
}

export interface DecisionGraphData {
    width: number;
    height: number;
    clusters: DecisionGraphCluster[];
    links: DecisionGraphLink[];
    callout: DecisionGraphCallout | null;
    legend: DecisionGraphLegendItem[];
}

export interface GovernanceLedgerEntry {
    label: string;
    value: number | string;
    display: string;
    caption: string;
}

export interface GovernanceLedgerData {
    decisions_signed: GovernanceLedgerEntry;
    policies_evaluated: GovernanceLedgerEntry;
    audit_export: GovernanceLedgerEntry;
}

export interface CommandCentreProps {
    workspace: Workspace;
    kpis: DashboardKpis;
    fleetTrust: FleetTrust;
    humanAttention: ApprovalRequestSummary[];
    recentRuns: RunListItem[];
    decisionGraph: DecisionGraphData;
    governanceLedger: GovernanceLedgerData;
}

/* -------------------------------------------------------------------------
 * Runs Explorer
 * ---------------------------------------------------------------------- */

export type BarTone = 'accent' | 'info' | 'review' | 'critical';

export type StepTone = 'completed' | 'review' | 'critical' | 'info' | 'idle';

export interface RunsFilters {
    status: 'all' | 'completed' | 'needs_review' | 'failed';
    workflow: number | null;
    search: string;
    from: string;
    to: string;
    range_label: string;
    is_default: boolean;
}

export interface StatusChip {
    key: 'all' | 'completed' | 'needs_review' | 'failed';
    label: string;
    count: number;
    tone: 'accent' | StatusTone;
}

export interface DistributionSegment {
    status: string;
    label: string;
    tone: StatusTone;
    count: number;
    percent: number;
}

export interface DistributionData {
    total: number;
    window_days: number;
    success_rate: number;
    success_label: string;
    segments: DistributionSegment[];
    avg_duration_label: string;
    cost_window_label: string;
    filtered_total: number;
}

export interface VolumeBar {
    day: string;
    label: string;
    count: number;
    percent: number;
    tone: BarTone;
    is_latest: boolean;
}

export interface VolumeData {
    window_days: number;
    peak: number;
    anchor_label: string;
    bars: VolumeBar[];
}

export interface TraceStep {
    id: number;
    name: string;
    type: string;
    status: string;
    tone: StepTone;
}

export interface RunRisk {
    level: RiskLevel;
    level_label: string;
    score: number;
    score_label: string;
    tone: StatusTone;
    rule: string;
}

export interface RunDecision {
    headline: string;
    confidence: number | null;
    confidence_label: string;
    signed: boolean;
    signed_label: string;
    summary: string | null;
}

export interface RunExpansion {
    steps: number;
    tool_calls: number;
    policy_hits: number;
    trace: TraceStep[];
    risk: RunRisk | null;
    decision: RunDecision | null;
    model: string | null;
}

export interface RunRow {
    id: number;
    run_key: string;
    workflow: string | null;
    objective: string;
    agent: string;
    started_at: string | null;
    started_label: string | null;
    duration_ms: number | null;
    duration_label: string;
    tokens: number | null;
    tokens_label: string;
    cost_usd: number | null;
    cost_label: string;
    status: RunStatus;
    status_label: string;
    tone: StatusTone;
    error_message: string | null;
    expansion: RunExpansion | null;
}

export interface PageLink {
    type: 'page' | 'gap';
    page?: number;
    url?: string;
    active?: boolean;
}

export interface PaginationData {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    range_label: string;
    prev_url: string | null;
    next_url: string | null;
    links: PageLink[];
}

export interface WorkflowOption {
    id: number;
    name: string;
    runs_count: number;
}

export interface RunsExplorerProps {
    workspace: Pick<Workspace, 'name' | 'slug' | 'tier'>;
    filters: RunsFilters;
    statusChips: StatusChip[];
    distribution: DistributionData;
    volume: VolumeData;
    runs: RunRow[];
    pagination: PaginationData;
    workflowOptions: WorkflowOption[];
    expandedRunId: number | null;
    showing: { filtered: number; total: number };
}
