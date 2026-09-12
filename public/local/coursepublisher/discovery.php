<?php
require_once('../../config.php');
require_once(__DIR__ . '/lib.php');
use local_coursepublisher\form\discovery_form;
use local_coursepublisher\local\discovery_service;
use local_coursepublisher\local\discovery_presenter;
require_login();
$context=context_system::instance();
require_capability('local/coursepublisher:preview',$context);
$PAGE->set_context($context);$PAGE->set_url(new moodle_url('/local/coursepublisher/discovery.php'));$PAGE->set_title(get_string('nav_discovery','local_coursepublisher'));$PAGE->set_heading(get_string('pluginname','local_coursepublisher'));
$error=null;$scan=null;$registration=null;$defaults=[];
$action=optional_param('action','',PARAM_ALPHA);
if($action==='register'){
    require_capability('local/coursepublisher:configuretopology',$context);
    require_capability('local/coursepublisher:bindcourses',$context);
    require_sesskey();
    $programid=required_param('programid',PARAM_INT);
    $selected=optional_param_array('selected',[],PARAM_RAW_TRIMMED);
    $targetgroupids=array_values(array_filter(array_map('intval',explode(',',optional_param('targetgroupids','',PARAM_SEQUENCE)))));
    $containerids=array_values(array_filter(array_map('intval',explode(',',optional_param('containerids','',PARAM_SEQUENCE)))));
    $showunmatched=(bool)optional_param('showunmatched',0,PARAM_BOOL);
    try{
        $registration=discovery_service::register_selected($programid,$selected,$targetgroupids,$containerids);
        $scan=discovery_service::scan($programid,$targetgroupids,$containerids);
        $defaults=['programid'=>$programid,'targetgroupids'=>$targetgroupids,'containerids'=>$containerids,'showunmatched'=>$showunmatched ? 1 : 0];
    }catch(Throwable $e){$error=$e->getMessage();}
}
$form=new discovery_form();
if($defaults)$form->set_data($defaults);
if($data=$form->get_data()){
    try{
        $groups=array_map('intval',(array)($data->targetgroupids??[]));
        $containers=array_map('intval',(array)($data->containerids??[]));
        $scan=discovery_service::scan((int)$data->programid,$groups,$containers);
        $showunmatched=!empty($data->showunmatched);
        $defaults=['programid'=>(int)$data->programid,'targetgroupids'=>$groups,'containerids'=>$containers,'showunmatched'=>$showunmatched ? 1 : 0];
        $form->set_data($defaults);
    }catch(Throwable $e){$error=$e->getMessage();}
}
local_coursepublisher_output_start('discovery',get_string('nav_discovery','local_coursepublisher'),get_string('discoverysubtitle','local_coursepublisher'));
if($error)echo html_writer::div(s($error),'cp-notice-error');
if($registration){
    $msg=get_string('registrationresult','local_coursepublisher',(object)['created'=>$registration['created'],'existing'=>$registration['existing'],'failed'=>$registration['failed']]);
    echo html_writer::div(s($msg),$registration['failed']?'cp-notice-error':'cp-notice-success');
}
local_coursepublisher_card_start(get_string('discoveryrequest','local_coursepublisher'),get_string('discoveryrequesthelp','local_coursepublisher'),'cp-form-card');$form->display();local_coursepublisher_card_end();
if($scan){
    $summary=$scan['summary'];
    $showunmatched=!empty($defaults['showunmatched']);
    $displayrows=discovery_presenter::visible_rows($scan['rows'],$showunmatched);
    $matchedcourses=discovery_presenter::matched_course_count($scan['rows']);
    echo html_writer::start_div('cp-preview-summary');
    foreach([
        get_string('discoveryregions','local_coursepublisher')=>$summary['regions'],
        get_string('discoveryunits','local_coursepublisher')=>$summary['units'],
        get_string('discoverycourses','local_coursepublisher')=>$summary['courses'],
        get_string('discoverymatchedcourses','local_coursepublisher')=>$matchedcourses,
        get_string('discoveryready','local_coursepublisher')=>($summary['statuses'][discovery_service::STATUS_READY_NEW]??0),
        get_string('discoveryexisting','local_coursepublisher')=>($summary['statuses'][discovery_service::STATUS_EXISTING]??0),
        get_string('discoveryconflicts','local_coursepublisher')=>array_sum(array_intersect_key($summary['statuses'],array_flip([discovery_service::STATUS_CONFLICT_GROUP,discovery_service::STATUS_CONFLICT_DUPLICATE_COURSE,discovery_service::STATUS_CONFLICT_EXISTING_BINDING,discovery_service::STATUS_CONFLICT_COURSE_REUSED,discovery_service::STATUS_INVALID_TOPOLOGY]))),
    ] as $label=>$value){echo html_writer::div(html_writer::div($label,'cp-summary-label').html_writer::div((string)$value,'cp-summary-value'),'cp-summary-tile');}
    echo html_writer::end_div();

    local_coursepublisher_card_start(get_string('discoveryresults','local_coursepublisher'),get_string('discoveryrootcurrent','local_coursepublisher',format_string($scan['root']->name).' (#'.$scan['root']->id.')'));
    if(!$displayrows){echo html_writer::div(get_string('discoverynoresults','local_coursepublisher'),'cp-empty');}
    else{
        $canregister=has_capability('local/coursepublisher:configuretopology',$context)&&has_capability('local/coursepublisher:bindcourses',$context);
        if($canregister){echo html_writer::start_tag('form',['method'=>'post','action'=>$PAGE->url]);echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'sesskey','value'=>sesskey()]);echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'action','value'=>'register']);echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'programid','value'=>(int)$scan['program']->id]);echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'targetgroupids','value'=>implode(',',array_map('intval',array_keys($scan['groups'])))]);echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'containerids','value'=>implode(',',array_map('intval',array_keys($scan['containers'])))]);echo html_writer::empty_tag('input',['type'=>'hidden','name'=>'showunmatched','value'=>$showunmatched?1:0]);}
        $table=new html_table();$table->attributes['class']='generaltable cp-table';$selecthead=get_string('select','local_coursepublisher');if($canregister){$selecthead.=' '.html_writer::empty_tag('input',['type'=>'checkbox','id'=>'cp-discovery-select-all','title'=>get_string('discoveryselectall','local_coursepublisher'),'aria-label'=>get_string('discoveryselectall','local_coursepublisher')]);}$table->head=[$selecthead,get_string('region','local_coursepublisher'),get_string('discoverycontainer','local_coursepublisher'),get_string('schoolunit','local_coursepublisher'),get_string('course','local_coursepublisher'),get_string('targetgroupgrade','local_coursepublisher'),get_string('matchedrules','local_coursepublisher'),get_string('existingbinding','local_coursepublisher'),get_string('status','local_coursepublisher'),get_string('reason','local_coursepublisher')];
        foreach($displayrows as $row){
            $select='—';if($canregister&&$row['selectable'])$select=html_writer::empty_tag('input',['type'=>'checkbox','name'=>'selected[]','value'=>discovery_service::candidate_key($row),'class'=>'cp-discovery-check']);
            $course=$row['courseid']?format_string($row['coursename']).' ['.s($row['shortname']).'] (#'.$row['courseid'].')':'—';
            $group=$row['targetgroupid']?format_string($row['groupname']).' ['.s($row['groupkey']).']':'—';
            $rulelabels=[];foreach($row['matchedruleids'] as $ruleid){if(isset($scan['rules'][$ruleid])){$rule=$scan['rules'][$ruleid];$rulelabels[]=format_string($rule->name).' (#'.(int)$rule->id.')';}}
            $ruleevidence=$rulelabels?implode(', ',$rulelabels):'—';
            $bindingevidence=!empty($row['targetbindid'])?'#'.(int)$row['targetbindid']:'—';
            $kind=$row['status']===discovery_service::STATUS_READY_NEW?'ready':($row['status']===discovery_service::STATUS_EXISTING?'info':(str_starts_with($row['status'],'CONFLICT')||$row['status']===discovery_service::STATUS_INVALID_TOPOLOGY?'error':($row['status']===discovery_service::STATUS_MISSING?'warning':'disabled')));
            $table->data[]=[$select,format_string($row['regionname']),format_string($row['containername']),format_string($row['unitname']),$course,$group,$ruleevidence,$bindingevidence,local_coursepublisher_badge($kind,get_string('discovery_status_'.$row['status'],'local_coursepublisher')),s($row['reason'])];
        }
        echo html_writer::div(html_writer::table($table),'cp-table-wrap');
        if($canregister){echo html_writer::tag('button',get_string('registerselected','local_coursepublisher'),['type'=>'submit','class'=>'btn btn-primary']);echo html_writer::end_tag('form');$PAGE->requires->js_init_code("const all=document.getElementById('cp-discovery-select-all');if(all){all.addEventListener('change',()=>document.querySelectorAll('.cp-discovery-check').forEach((box)=>{box.checked=all.checked;}));}");}
    }
    local_coursepublisher_card_end();
}
local_coursepublisher_output_end();
