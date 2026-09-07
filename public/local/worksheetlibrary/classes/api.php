<?php
namespace local_worksheetlibrary;
final class api {
 public static function published_for_place(int $courseid,int $sectionid=0,string $search=''): array { return service\binding_service::for_place($courseid,$sectionid,$search); }
 public static function published_version(int $versionid): \stdClass { global $DB; $v=$DB->get_record('wslib_version',['id'=>$versionid,'state'=>'published'],'*',MUST_EXIST); $v->item=$DB->get_record('wslib_item',['id'=>$v->itemid],'*',MUST_EXIST); return $v; }
 public static function primary_file(int $versionid): ?\stored_file { return service\file_service::primary($versionid); }
}
