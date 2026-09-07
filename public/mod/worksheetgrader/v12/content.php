<?php
// Variables provided by content.php: $id,$cm,$course,$activity,$context,$session,$sessionid.
defined('MOODLE_INTERNAL') || die();
require_capability('mod/worksheetgrader:manageactivity', $context);
$selectedcourse = optional_param('librarycourseid', (int)$course->id, PARAM_INT);
$selectedsection = optional_param('librarysectionid', 0, PARAM_INT);
$q = optional_param('q', '', PARAM_TEXT);
$courses = [];
foreach (enrol_get_users_courses((int)$USER->id, true, 'id,fullname,shortname') as $c) {
    if (has_capability('moodle/course:update', context_course::instance($c->id))) { $courses[$c->id] = $c; }
}
if (!isset($courses[$selectedcourse])) { $courses[$course->id] = $course; $selectedcourse = (int)$course->id; }
$sections = $DB->get_records('course_sections', ['course'=>$selectedcourse], 'section', 'id,section,name,visible');
$results = class_exists('\\local_worksheetlibrary\\api')
    ? \local_worksheetlibrary\api::published_for_place($selectedcourse, $selectedsection, $q) : [];
$PAGE->requires->js_call_amd('mod_worksheetgrader/worksheet_picker', 'init', [[
    'sessionid'=>(int)$sessionid,
    'nexturl'=>(new moodle_url('/mod/worksheetgrader/teams.php',['id'=>$id,'sessionid'=>$sessionid]))->out(false),
]]);
echo $OUTPUT->header();
echo html_writer::div('<span class="wsg-v12-eyebrow">BƯỚC 2/4</span><h2>Chọn phiếu học tập</h2><p>Chọn khóa học, bài học và một phiên bản phiếu đã xuất bản từ Kho phiếu dùng chung.</p>', 'wsg-v12-pagehead');
echo $OUTPUT->render(worksheetgrader_nav_tabs($cm, 'content', $sessionid));
echo html_writer::div(
    html_writer::div('<span class="is-done">✓</span><strong>Thông tin buổi</strong>','wsg-v12-step is-done') .
    html_writer::div('<span>2</span><strong>Chọn phiếu</strong>','wsg-v12-step is-active') .
    html_writer::div('<span>3</span><strong>'.(($session->teammode??'')==='individual'?'Cá nhân':'Tạo nhóm').'</strong>','wsg-v12-step') .
    html_writer::div('<span>4</span><strong>Xác nhận & mở</strong>','wsg-v12-step'), 'wsg-v12-steps');

echo html_writer::start_div('wsg-worksheet-picker');
echo html_writer::start_div('wsg-picker-main');
echo html_writer::start_tag('form',['method'=>'get','class'=>'wsg-picker-filters','data-region'=>'picker-filters']);
echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'id','value'=>$id]);
echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'sessionid','value'=>$sessionid]);
echo html_writer::start_div('wsg-filter'); echo html_writer::label('Khóa học tôi giảng dạy','wsg-course');
$courseopts=[];foreach($courses as $c)$courseopts[$c->id]=format_string($c->fullname);echo html_writer::select($courseopts,'librarycourseid',$selectedcourse,false,['id'=>'wsg-course','class'=>'form-select','data-region'=>'course-select']);echo html_writer::end_div();
echo html_writer::start_div('wsg-filter'); echo html_writer::label('Bài học / Section','wsg-section');$sectionopts=[0=>'Toàn khóa học'];foreach($sections as $s)$sectionopts[$s->id]=($s->section==0?'Chung':('Bài '.$s->section)).($s->name?' — '.format_string($s->name):'');echo html_writer::select($sectionopts,'librarysectionid',$selectedsection,false,['id'=>'wsg-section','class'=>'form-select','data-region'=>'section-select']);echo html_writer::end_div();
echo html_writer::start_div('wsg-filter wsg-filter-search');echo html_writer::label('Tìm phiếu','wsg-q');echo html_writer::empty_tag('input',['id'=>'wsg-q','name'=>'q','value'=>$q,'class'=>'form-control','placeholder'=>'Tên phiếu…']);echo html_writer::end_div();echo html_writer::tag('button','Lọc',['class'=>'btn btn-outline-primary']);echo html_writer::end_tag('form');
echo html_writer::start_div('wsg-library-results','','',['data-region'=>'library-results']);
if(!$results){echo $OUTPUT->notification('Chưa có phiếu đã xuất bản được gắn vào vị trí này. Bạn có thể mở Kho phiếu để tạo/gắn phiếu.','info');}
foreach($results as $item){$version=(int)$item->currentversionid;echo '<button type="button" class="wsg-library-card" data-versionid="'.$version.'" data-name="'.s($item->name).'" data-kind="'.s($item->kind).'" data-versionno="'.(int)$item->versionno.'"><span class="wsg-doc-icon '.s($item->kind).'">'.($item->kind==='office'?'W':($item->kind==='pdf'?'PDF':($item->kind==='native'?'N':'HTML'))).'</span><span class="wsg-library-card-body"><strong>'.s($item->name).'</strong><small>v'.(int)$item->versionno.' · '.s(strtoupper($item->kind)).'</small></span><span>›</span></button>';}
echo html_writer::end_div();echo html_writer::end_div();
echo html_writer::start_tag('aside',['class'=>'wsg-picker-preview','data-region'=>'worksheet-preview']);echo '<div data-region="preview-empty"><div class="wsg-preview-placeholder">📄</div><h3>Xem trước phiếu</h3><p>Chọn một phiếu ở bên trái để xem thông tin và sử dụng cho buổi.</p></div><div class="d-none" data-region="preview-body"><span class="badge bg-primary" data-region="preview-kind"></span><h3 data-region="preview-name"></h3><p data-region="preview-version"></p><button type="button" class="btn btn-primary btn-lg w-100" data-action="select-worksheet">Dùng phiếu này cho buổi</button></div><hr><a class="btn btn-outline-secondary w-100" href="'.$CFG->wwwroot.'/local/worksheetlibrary/index.php">Mở Kho phiếu</a>';echo html_writer::end_tag('aside');echo html_writer::end_div();
echo $OUTPUT->footer();
