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
    /** Pending approvals across the fleet — drives the Review Queue rail badge. */
    pendingReviews: number;
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
    /** Card heading, authored server-side so it can track the selected range. */
    label: string;
    value: number;
    display: string;
    caption: string;
}

export interface DashboardKpis {
    total_executions: KpiMetric;
    success_rate: KpiMetric;
    avg_latency: KpiMetric;
    cost_window: KpiMetric;
}

export type DashboardRangeKey = '24h' | '7d' | '14d' | '30d';

export interface DashboardRangeOption {
    key: DashboardRangeKey;
    label: string;
}

export interface DashboardRange {
    key: DashboardRangeKey;
    label: string;
    days: number;
    /** The newest run every window is measured back from. */
    anchor_label: string;
    options: DashboardRangeOption[];
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

/** The box the drawing occupies, in canvas units — labels included. */
export interface DecisionGraphBounds {
    x: number;
    y: number;
    width: number;
    height: number;
}

export interface DecisionGraphData {
    width: number;
    height: number;
    bounds: DecisionGraphBounds;
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

export interface FleetPulse {
    live: boolean;
    latest_run_label: string;
}

export interface CommandCentreProps {
    workspace: Workspace;
    range: DashboardRange;
    pulse: FleetPulse;
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
    /** "34 runs · 14 days", pluralised server-side. */
    scope_label: string;
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

/* -------------------------------------------------------------------------
 * Run Inspector
 * ---------------------------------------------------------------------- */

export type TraceTone =
    | 'reasoning'
    | 'tool'
    | 'policy'
    | 'critical'
    | 'review'
    | 'idle';

export type LineTone = 'ink' | 'ok' | 'warn' | 'critical';

export interface TraceNode {
    id: number;
    order: number;
    order_label: string;
    name: string;
    type: string;
    status: string;
    tone: TraceTone;
    duration_label: string;
    selected: boolean;
}

export interface ExecutionTrace {
    summary: string;
    nodes: TraceNode[];
}

export interface ReasoningLine {
    label: string;
    text: string;
    tone: LineTone;
}

export interface ReasoningEntry {
    id: number;
    order_label: string;
    time: string | null;
    type: string;
    name: string;
    tone: TraceTone;
    lines: ReasoningLine[];
}

export interface ReasoningTimeline {
    title: string;
    meta: string;
    entries: ReasoningEntry[];
    /** The run stopped mid-flight, so the trace has no closing line. */
    open: boolean;
}

export interface ToolCall {
    id: number;
    order_label: string;
    name: string;
    kind: string;
    status: string;
    status_label: string;
    tone: TraceTone;
    input: string;
    output: string | null;
    duration_label: string;
    cost_label: string;
}

export interface ToolCalls {
    summary: string;
    note: string | null;
    items: ToolCall[];
}

export interface InspectorRisk {
    level: RiskLevel;
    level_label: string;
    score: number;
    score_label: string;
    percent: number;
    tone: StatusTone;
}

export interface DecisionConfidence {
    value: number;
    label: string;
    percent: number;
    threshold_label: string;
    verdict: 'clear' | 'below';
}

export interface PolicyBreach {
    rule: string;
    summary: string;
}

export interface PolicyEvaluation {
    tier: string | null;
    risk: InspectorRisk | null;
    confidence: DecisionConfidence | null;
    breach: PolicyBreach | null;
    counts: { passed: number; breached: number; skipped: number };
    clear_label: string;
}

export interface DecisionResolution {
    status: string;
    by: string | null;
    at: string | null;
    notes: string | null;
}

export interface DecisionRecord {
    eyebrow: string;
    headline: string;
    detail: string;
    pending: boolean;
    resolution: DecisionResolution | null;
}

export interface MetadataRow {
    label: string;
    value: string | null;
    href?: string | null;
    tone: 'ink' | 'accent';
}

export interface RelatedRun {
    id: number;
    run_key: string;
    label: string;
    relation: string;
    tone: StatusTone;
}

export interface HeaderStat {
    label: string;
    value: string;
    caption: string | null;
    tone: 'ink' | 'accent';
}

export interface InspectorRun {
    id: number;
    run_key: string;
    status: RunStatus;
    status_label: string;
    tone: StatusTone;
    objective: string;
    agent: string;
    model: string | null;
    error_message: string | null;
    workflow: {
        id: number | null;
        name: string | null;
        slug: string | null;
        trigger_type: string | null;
    };
    workspace: { name: string | null; slug: string | null; tier: string | null };
    trace: ExecutionTrace;
    reasoning: ReasoningTimeline;
    toolCalls: ToolCalls;
    policy: PolicyEvaluation;
    decision: DecisionRecord;
    metadata: MetadataRow[];
    audit: { count: number; label: string };
}

export interface RunInspectorProps {
    run: InspectorRun;
    header: HeaderStat[];
    related: RelatedRun[];
}

/* -------------------------------------------------------------------------
 * Review Queue (Human-in-the-Loop Desk)
 * ---------------------------------------------------------------------- */

export type ReviewDecision = 'approve' | 'reject' | 'request_changes';

export interface ReviewRisk {
    level: RiskLevel;
    level_label: string;
    score: number;
    score_label: string;
    percent: number;
    tone: StatusTone;
    confidence_label: string | null;
}

export interface ReviewIntercept {
    rule: string;
    status_label: string;
    measure: string | null;
    irreversible: boolean;
}

export interface ReviewReadout {
    title: string;
    meta: string;
    lines: { text: string; tone: 'ink' | 'critical' }[];
}

export interface ReviewQueueItem {
    id: number;
    run_id: number;
    run_key: string;
    workflow: string | null;
    agent: string;
    objective: string;
    headline: string;
    summary: string;
    risk: ReviewRisk;
    category: 'DESTRUCTIVE' | 'LOW CONF' | 'POLICY';
    impact_label: string | null;
    waiting_seconds: number;
    waiting_label: string;
    sla_label: string;
    /** The held action is irreversible — renders as a Freeze Frame rather than a row. */
    frozen: boolean;
    intercept: ReviewIntercept;
    readout: ReviewReadout;
    impact_rows: { label: string; value: string }[];
    detail: InspectorRun;
}

export interface ReviewStats {
    pending: number;
    critical: number;
    average_wait_label: string;
    oldest_wait_label: string | null;
    intercepted: number;
    window_label: string;
    sla_label: string;
    approved: number;
    rejected: number;
    decisions: {
        date: string;
        label: string;
        approved: number;
        rejected: number;
    }[];
}

export interface ResolvedReview {
    id: number;
    run_id: number;
    run_key: string | null;
    workflow: string | null;
    status: string;
    status_label: string;
    tone: StatusTone;
    by: string | null;
    at_label: string | null;
}

export interface ReviewerLoad {
    rows: { name: string; is_you: boolean; decisions: number }[];
    max: number;
    unassigned_label: string;
}

export interface QueueConstellation {
    nodes: { run_key: string; level: RiskLevel; x: number; y: number }[];
    caption: string;
}

export interface ReviewQueueProps {
    queue: ReviewQueueItem[];
    stats: ReviewStats;
    resolved: ResolvedReview[];
    reviewers: ReviewerLoad;
    constellation: QueueConstellation;
}

/* -------------------------------------------------------------------------
 * Cover
 * ---------------------------------------------------------------------- */

export type SignalAccent = 'cyan' | 'violet' | 'emerald' | 'amber';

export interface CoverSignal {
    key: string;
    label: string;
    display: string;
    caption: string;
    accent: SignalAccent;
}

export interface CoverPulse extends FleetPulse {
    latest_run_key: string | null;
    tokens_display: string;
    spend_display: string;
    autonomy_display: string;
}

export interface CoverConstellationNode {
    id: number;
    label: string;
    short_label: string;
    slug: string;
    runs: number;
    runs_label: string;
    orbit: number;
    x: number;
    y: number;
    anchor: 'start' | 'middle' | 'end';
    radius: number;
    tone: StatusTone;
}

export interface CoverConstellation {
    nodes: CoverConstellationNode[];
    orbits: number[];
    core_display: string;
    caption: string;
}

export interface CoverEntry {
    key: string;
    label: string;
    description: string;
    href: string;
    meta: string;
}

export interface CoverProps {
    workspace: Workspace | null;
    signals: CoverSignal[];
    pulse: CoverPulse;
    constellation: CoverConstellation;
    entries: CoverEntry[];
}
