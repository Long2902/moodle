<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_coursepublisher_category',
        get_string('pluginname', 'local_coursepublisher')
    ));

    $pages = [
        'dashboard' => ['/local/coursepublisher/index.php', 'local/coursepublisher:view'],
        'programs' => ['/local/coursepublisher/programs.php', 'local/coursepublisher:configureprograms'],
        'masters' => ['/local/coursepublisher/masters.php', 'local/coursepublisher:configureprograms'],
        'regions' => ['/local/coursepublisher/regions.php', 'local/coursepublisher:configuretopology'],
        'schools' => ['/local/coursepublisher/schools.php', 'local/coursepublisher:configuretopology'],
        'targets' => ['/local/coursepublisher/targets.php', 'local/coursepublisher:bindcourses'],
        'health' => ['/local/coursepublisher/health.php', 'local/coursepublisher:view'],
        'preview' => ['/local/coursepublisher/preview.php', 'local/coursepublisher:preview'],
        'jobs' => ['/local/coursepublisher/jobs.php', 'local/coursepublisher:view'],
    ];

    foreach ($pages as $key => [$path, $capability]) {
        $ADMIN->add('local_coursepublisher_category', new admin_externalpage(
            'local_coursepublisher_' . $key,
            get_string('nav_' . $key, 'local_coursepublisher'),
            new moodle_url($path),
            $capability
        ));
    }
}
