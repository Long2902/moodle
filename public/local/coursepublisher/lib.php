<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Return plugin-local navigation metadata.
 */
function local_coursepublisher_nav_items(): array {
    return [
        'dashboard' => ['/local/coursepublisher/index.php', 'nav_dashboard', '▦', 'local/coursepublisher:view'],
        'programs' => ['/local/coursepublisher/programs.php', 'nav_programs', '◆', 'local/coursepublisher:configureprograms'],
        'targetgroups' => ['/local/coursepublisher/target_groups.php', 'nav_targetgroups', '◇', 'local/coursepublisher:configureprograms'],
        'masters' => ['/local/coursepublisher/masters.php', 'nav_masters', '◎', 'local/coursepublisher:configureprograms'],
        'regions' => ['/local/coursepublisher/regions.php', 'nav_regions', '⌖', 'local/coursepublisher:configuretopology'],
        'schools' => ['/local/coursepublisher/schools.php', 'nav_schools', '▤', 'local/coursepublisher:configuretopology'],
        'targets' => ['/local/coursepublisher/targets.php', 'nav_targets', '▣', 'local/coursepublisher:bindcourses'],
        'discoveryrules' => ['/local/coursepublisher/discovery_rules.php', 'nav_discoveryrules', '⌁', 'local/coursepublisher:configureprograms'],
        'discovery' => ['/local/coursepublisher/discovery.php', 'nav_discovery', '⌕', 'local/coursepublisher:preview'],
        'health' => ['/local/coursepublisher/health.php', 'nav_health', '✓', 'local/coursepublisher:view'],
        'preview' => ['/local/coursepublisher/preview.php', 'nav_preview', '▷', 'local/coursepublisher:preview'],
        'batches' => ['/local/coursepublisher/batches.php', 'nav_batches', '⇉', 'local/coursepublisher:view'],
        'jobs' => ['/local/coursepublisher/jobs.php', 'nav_jobs', '◫', 'local/coursepublisher:view'],
    ];
}

/**
 * Start the Course Publisher shell.
 */
function local_coursepublisher_output_start(string $current, string $title, string $subtitle = ''): void {
    global $OUTPUT, $PAGE;

    $context = context_system::instance();
    $PAGE->set_pagelayout('standard');
    $PAGE->requires->css(new moodle_url('/local/coursepublisher/styles.css'));

    echo $OUTPUT->header();
    echo html_writer::start_div('local-coursepublisher cp-shell');

    echo html_writer::start_tag('aside', ['class' => 'cp-sidebar', 'aria-label' => get_string('pluginnavigation', 'local_coursepublisher')]);
    echo html_writer::div(
        html_writer::span('CP', 'cp-brand-mark') .
        html_writer::span(get_string('pluginshortname', 'local_coursepublisher'), 'cp-brand-text'),
        'cp-brand'
    );

    echo html_writer::start_tag('nav', ['class' => 'cp-side-nav']);
    foreach (local_coursepublisher_nav_items() as $key => [$path, $stringkey, $icon, $capability]) {
        if (!has_capability($capability, $context)) {
            continue;
        }
        $classes = 'cp-side-link' . ($key === $current ? ' active' : '');
        $attrs = ['class' => $classes];
        if ($key === $current) {
            $attrs['aria-current'] = 'page';
        }
        $label = html_writer::span($icon, 'cp-nav-icon', ['aria-hidden' => 'true']) .
            html_writer::span(get_string($stringkey, 'local_coursepublisher'), 'cp-nav-label');
        echo html_writer::link(new moodle_url($path), $label, $attrs);
    }
    echo html_writer::end_tag('nav');
    echo html_writer::end_tag('aside');

    echo html_writer::start_tag('main', ['class' => 'cp-main']);
    echo html_writer::start_div('cp-topbar');
    echo html_writer::start_div('cp-topbar-copy');
    echo html_writer::tag('h2', format_string($title), ['class' => 'cp-page-title']);
    echo html_writer::end_div();
    if ($current !== 'preview' && has_capability('local/coursepublisher:preview', $context)) {
        echo html_writer::link(
            new moodle_url('/local/coursepublisher/preview.php'),
            '+ ' . get_string('newdryrun', 'local_coursepublisher'),
            ['class' => 'btn btn-primary cp-primary-action']
        );
    }
    echo html_writer::end_div();
}

/**
 * End the Course Publisher shell.
 */
function local_coursepublisher_output_end(): void {
    global $OUTPUT;

    echo html_writer::end_tag('main');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
}

/**
 * Render a status badge.
 */
function local_coursepublisher_badge(string $status, ?string $label = null): string {
    $map = [
        'ok' => ['success', $label ?? get_string('healthy', 'local_coursepublisher')],
        'ready' => ['success', $label ?? get_string('routeok', 'local_coursepublisher')],
        'enabled' => ['success', $label ?? get_string('enabledstatus', 'local_coursepublisher')],
        'warning' => ['warning', $label ?? get_string('warning', 'local_coursepublisher')],
        'missing' => ['warning', $label ?? get_string('missing', 'local_coursepublisher')],
        'error' => ['danger', $label ?? get_string('error', 'local_coursepublisher')],
        'blocked' => ['danger', $label ?? get_string('routeblocked', 'local_coursepublisher')],
        'disabled' => ['muted', $label ?? get_string('disabledstatus', 'local_coursepublisher')],
        'info' => ['info', $label ?? get_string('info', 'local_coursepublisher')],
    ];
    [$tone, $text] = $map[$status] ?? ['muted', $label ?? $status];
    return html_writer::span(html_writer::span('', 'cp-badge-dot') . s($text), 'cp-badge cp-badge-' . $tone);
}

/**
 * Render common edit/toggle actions.
 */
function local_coursepublisher_actions(moodle_url $baseurl, int $id, bool $enabled): string {
    $edit = html_writer::link(
        new moodle_url($baseurl, ['edit' => $id]),
        get_string('edit', 'local_coursepublisher'),
        ['class' => 'cp-action-link']
    );
    $toggle = html_writer::link(
        new moodle_url($baseurl, ['toggle' => $id, 'sesskey' => sesskey()]),
        $enabled ? get_string('disable', 'local_coursepublisher') : get_string('enable', 'local_coursepublisher'),
        ['class' => 'cp-action-link']
    );
    return $edit . html_writer::span('·', 'cp-action-separator') . $toggle;
}

/**
 * Start a reusable white card.
 */
function local_coursepublisher_card_start(string $title = '', string $extra = '', string $classes = ''): void {
    echo html_writer::start_div('cp-card ' . $classes);
    if ($title !== '' || $extra !== '') {
        echo html_writer::start_div('cp-card-header');
        if ($title !== '') {
            echo html_writer::tag('h3', format_string($title), ['class' => 'cp-card-title']);
        }
        if ($extra !== '') {
            echo html_writer::div($extra, 'cp-card-extra');
        }
        echo html_writer::end_div();
    }
}

function local_coursepublisher_card_end(): void {
    echo html_writer::end_div();
}

/**
 * Render one topology-health badge with an operator-facing reason.
 */
function local_coursepublisher_health_cell(stdClass $health): string {
    $badge = local_coursepublisher_badge($health->status === 'ok' ? 'ok' : $health->status, $health->status === 'ok' ?
        get_string('healthy', 'local_coursepublisher') : ($health->status === 'error' ? get_string('error', 'local_coursepublisher') : get_string('missing', 'local_coursepublisher')));
    return html_writer::div($badge, 'cp-health-badge') .
        html_writer::div(s($health->reason), 'cp-health-reason');
}


/** Render a job/item status badge. */
function local_coursepublisher_job_badge(string $status): string {
    $labels = [
        'draft' => get_string('jobstatus_draft', 'local_coursepublisher'),
        'preflight_failed' => get_string('jobstatus_preflight_failed', 'local_coursepublisher'),
        'ready' => get_string('jobstatus_ready', 'local_coursepublisher'),
        'waiting' => get_string('jobstatus_waiting', 'local_coursepublisher'),
        'queued' => get_string('jobstatus_queued', 'local_coursepublisher'),
        'running' => get_string('jobstatus_running', 'local_coursepublisher'),
        'partial' => get_string('jobstatus_partial', 'local_coursepublisher'),
        'succeeded' => get_string('jobstatus_succeeded', 'local_coursepublisher'),
        'failed' => get_string('jobstatus_failed', 'local_coursepublisher'),
        'manual_review' => get_string('jobstatus_manual_review', 'local_coursepublisher'),
        'cancelled' => get_string('jobstatus_cancelled', 'local_coursepublisher'),
        'pending' => get_string('jobitem_pending', 'local_coursepublisher'),
        'skipped' => get_string('jobitem_skipped', 'local_coursepublisher'),
    ];
    $tones = [
        'draft' => 'muted', 'preflight_failed' => 'danger', 'ready' => 'info', 'waiting' => 'info', 'queued' => 'info',
        'running' => 'warning', 'partial' => 'warning', 'succeeded' => 'success', 'failed' => 'danger',
        'manual_review' => 'warning', 'cancelled' => 'muted', 'pending' => 'info', 'skipped' => 'muted',
    ];
    $tone = $tones[$status] ?? 'muted';
    $label = $labels[$status] ?? $status;
    return html_writer::span(html_writer::span('', 'cp-badge-dot') . s($label), 'cp-badge cp-badge-' . $tone);
}


/** Render a Batch parent status badge. */
function local_coursepublisher_batch_badge(string $status): string {
    $labels = [
        'draft' => get_string('batchstatus_draft', 'local_coursepublisher'),
        'preflight' => get_string('batchstatus_preflight', 'local_coursepublisher'),
        'queued' => get_string('batchstatus_queued', 'local_coursepublisher'),
        'running' => get_string('batchstatus_running', 'local_coursepublisher'),
        'paused' => get_string('batchstatus_paused', 'local_coursepublisher'),
        'partial' => get_string('batchstatus_partial', 'local_coursepublisher'),
        'succeeded' => get_string('batchstatus_succeeded', 'local_coursepublisher'),
        'failed' => get_string('batchstatus_failed', 'local_coursepublisher'),
        'cancelled' => get_string('batchstatus_cancelled', 'local_coursepublisher'),
    ];
    $tones = [
        'draft' => 'muted', 'preflight' => 'info', 'queued' => 'info', 'running' => 'warning',
        'paused' => 'warning', 'partial' => 'warning', 'succeeded' => 'success', 'failed' => 'danger',
        'cancelled' => 'muted',
    ];
    return html_writer::span(
        html_writer::span('', 'cp-badge-dot') . s($labels[$status] ?? $status),
        'cp-badge cp-badge-' . ($tones[$status] ?? 'muted')
    );
}

/** Render a per-target Batch status badge. */
function local_coursepublisher_batch_target_badge(string $status): string {
    $labels = [
        'ready' => get_string('batchready', 'local_coursepublisher'),
        'blocked' => get_string('batchblocked', 'local_coursepublisher'),
        'waiting' => get_string('batchwaiting', 'local_coursepublisher'),
        'queued' => get_string('batchqueued', 'local_coursepublisher'),
        'running' => get_string('batchrunning', 'local_coursepublisher'),
        'succeeded' => get_string('batchsucceeded', 'local_coursepublisher'),
        'failed' => get_string('batchfailed', 'local_coursepublisher'),
        'manual_review' => get_string('batchmanualreview', 'local_coursepublisher'),
        'cancelled' => get_string('batchcancelledtargets', 'local_coursepublisher'),
    ];
    $tones = [
        'ready' => 'success', 'blocked' => 'danger', 'waiting' => 'info', 'queued' => 'info',
        'running' => 'warning', 'succeeded' => 'success', 'failed' => 'danger',
        'manual_review' => 'warning', 'cancelled' => 'muted',
    ];
    return html_writer::span(
        html_writer::span('', 'cp-badge-dot') . s($labels[$status] ?? $status),
        'cp-badge cp-badge-' . ($tones[$status] ?? 'muted')
    );
}
