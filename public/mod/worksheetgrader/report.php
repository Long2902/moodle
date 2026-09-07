<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require('../../config.php'); require_once(__DIR__.'/locallib.php');
$id=required_param('id',PARAM_INT); [$cm,$course,$activity,$context]=worksheetgrader_get_page_context($id); require_capability('mod/worksheetgrader:viewreports',$context);
worksheetgrader_setup_page($PAGE,$cm,$course,$activity,get_string('reports','mod_worksheetgrader'),'report.php');
$sql="SELECT g.id,g.userid,g.finalgrade,g.adjustment,g.published,g.timemodified,a.id AS attemptid,a.status,t.name AS teamname,s.name AS sessionname,s.sessiondate,s.maxpoints,u.firstname,u.lastname FROM {wsg_grade} g JOIN {wsg_attempt} a ON a.id=g.attemptid JOIN {wsg_team} t ON t.id=a.teamid JOIN {wsg_session} s ON s.id=a.sessionid JOIN {user} u ON u.id=g.userid WHERE s.worksheetgraderid=:aid ORDER BY s.sessiondate DESC,t.name,u.lastname,u.firstname"; $rows=$DB->get_records_sql($sql,['aid'=>$activity->id]);
$published=array_filter($rows,static fn($r)=>$r->published); $grades=array_map(static fn($r)=>(float)$r->finalgrade,$published); $avg=$grades?array_sum($grades)/count($grades):0;
echo $OUTPUT->header(); echo $OUTPUT->render(worksheetgrader_nav_tabs($cm,'reports')); echo $OUTPUT->heading('Dashboard chấm điểm');
echo html_writer::start_div('wsg-stat-grid'); foreach([['Bản ghi điểm',count($rows)],['Đã công bố',count($published)],['Học sinh',count(array_unique(array_map(static fn($r)=>$r->userid,$published)))],['Điểm TB',format_float($avg,2)]] as $i){echo html_writer::div(html_writer::tag('strong',$i[1]).html_writer::span($i[0]),'wsg-stat-card');} echo html_writer::end_div();
$bands = [['0–4.9',0,5],['5.0–6.4',5,6.5],['6.5–7.9',6.5,8],['8.0–8.9',8,9],['9.0–10',9,INF]];
echo $OUTPUT->heading('Phân bố điểm đã công bố', 3);
echo html_writer::start_div('wsg-distribution');
foreach ($bands as [$label,$min,$max]) {
    $count = count(array_filter($grades, static fn($g) => $g >= $min && $g < $max));
    $percent = $grades ? round($count * 100 / count($grades), 1) : 0;
    echo html_writer::div(html_writer::span($label, 'wsg-band-label') . html_writer::div(html_writer::div('', 'wsg-band-fill', ['style'=>'width:'.$percent.'%']), 'wsg-band-track') . html_writer::span($count . ' (' . $percent . '%)', 'wsg-band-count'), 'wsg-band-row');
}
echo html_writer::end_div();
echo html_writer::start_div('mb-3'); foreach(['xlsx'=>'Excel','csv'=>'CSV','html'=>'HTML'] as $format=>$label){echo $OUTPUT->single_button(new moodle_url('/mod/worksheetgrader/export.php',['id'=>$id,'format'=>$format]),'Xuất '.$label,'get');} echo html_writer::end_div();
$table=new html_table();$table->head=['Buổi','Nhóm','Học sinh','Điểm','Điều chỉnh','Trạng thái'];foreach($rows as $r){$table->data[]=[format_string($r->sessionname),format_string($r->teamname),fullname($r),format_float($r->finalgrade,2),format_float($r->adjustment,2),$r->published?'Đã công bố':'Bản nháp'];} echo html_writer::table($table); echo $OUTPUT->footer();
