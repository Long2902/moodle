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
$id=required_param('id',PARAM_INT);$format=required_param('format',PARAM_ALPHA);[$cm,$course,$activity,$context]=worksheetgrader_get_page_context($id);require_capability('mod/worksheetgrader:export',$context);
$sql="SELECT g.id,g.userid,g.groupgrade,g.adjustment,g.finalgrade,g.feedback,g.published,a.status,t.name AS teamname,s.name AS sessionname,s.sessiondate,u.firstname,u.lastname,u.email,u.idnumber FROM {wsg_grade} g JOIN {wsg_attempt} a ON a.id=g.attemptid JOIN {wsg_team} t ON t.id=a.teamid JOIN {wsg_session} s ON s.id=a.sessionid JOIN {user} u ON u.id=g.userid WHERE s.worksheetgraderid=:aid ORDER BY s.sessiondate,t.name,u.lastname,u.firstname";$records=$DB->get_records_sql($sql,['aid'=>$activity->id]);$rows=[['Buổi','Ngày','Nhóm','Học sinh','Email','Mã HS','Điểm nhóm','Điều chỉnh','Điểm cuối','Nhận xét','Trạng thái']];foreach($records as $r){$rows[]=[strip_tags(format_string($r->sessionname)),userdate($r->sessiondate),strip_tags(format_string($r->teamname)),fullname($r),$r->email,$r->idnumber,(float)$r->groupgrade,(float)$r->adjustment,(float)$r->finalgrade,$r->feedback,$r->published?'Đã công bố':'Bản nháp'];}
$basename='worksheet_report_'.$activity->id.'_'.date('Ymd_His');
if($format==='csv'){header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$basename.'.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");foreach($rows as $row){fputcsv($out,$row);}fclose($out);exit;}
if($format==='html'){header('Content-Type: text/html; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$basename.'.html"');echo '<!doctype html><meta charset="utf-8"><title>'.s($activity->name).'</title><style>body{font-family:Arial;margin:24px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #aaa;padding:6px}th{background:#eef}</style><h1>'.s($activity->name).'</h1><table>';foreach($rows as $i=>$row){echo '<tr>';foreach($row as $cell){$tag=$i===0?'th':'td';echo '<'.$tag.'>'.s((string)$cell).'</'.$tag.'>';}echo '</tr>';}echo '</table>';exit;}
if($format==='xlsx'){
    require_once(__DIR__.'/classes/export/xlsx_writer.php');
    $writer=new \mod_worksheetgrader\export\xlsx_writer();
    $published=array_filter($records,static fn($r)=>$r->published);$gradevalues=array_map(static fn($r)=>(float)$r->finalgrade,$published);
    $overview=[['Chỉ số','Giá trị'],['Hoạt động',strip_tags(format_string($activity->name))],['Khóa học',strip_tags(format_string($course->fullname))],['Xuất lúc',userdate(time())],['Số buổi',$DB->count_records('wsg_session',['worksheetgraderid'=>$activity->id])],['Bản ghi đã công bố',count($published)],['Điểm trung bình',$gradevalues?array_sum($gradevalues)/count($gradevalues):0]];
    $writer->add_sheet('Tổng quan',$overview);
    $teamrows=[['Buổi','Nhóm','Số thành viên','Điểm trung bình','Trạng thái']];$teamstats=[];
    foreach($records as $r){$key=$r->sessionname.'|'.$r->teamname;if(!isset($teamstats[$key])){$teamstats[$key]=['session'=>$r->sessionname,'team'=>$r->teamname,'grades'=>[],'published'=>true];}$teamstats[$key]['grades'][]=(float)$r->finalgrade;$teamstats[$key]['published']=$teamstats[$key]['published']&&$r->published;}
    foreach($teamstats as $stat){$teamrows[]=[$stat['session'],$stat['team'],count($stat['grades']),array_sum($stat['grades'])/max(1,count($stat['grades'])),$stat['published']?'Đã công bố':'Có bản nháp'];}
    $writer->add_sheet('Kết quả nhóm',$teamrows);
    $writer->add_sheet('Học sinh',$rows);
    $logs=[['Thời gian','Người thao tác','Hành động','Chi tiết']];foreach($DB->get_records('wsg_log',['worksheetgraderid'=>$activity->id],'timecreated') as $log){$user=$DB->get_record('user',['id'=>$log->actorid]);$logs[]=[userdate($log->timecreated),$user?fullname($user):'', $log->action,$log->detailsjson];}$writer->add_sheet('Nhật ký',$logs);
    $tmp=make_request_directory().'/'.$basename.'.xlsx';$writer->save($tmp);send_file($tmp,$basename.'.xlsx',0,0,true,true,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
}
throw new moodle_exception('invalidparameter');
