<?php
namespace local_worksheetlibrary\service;
final class access_service {
 public static function can_author(?int $userid=null): bool {
  global $USER;
  $userid=$userid?:$USER->id;
  if (is_siteadmin($userid) || has_capability('local/worksheetlibrary:manage', \context_system::instance(), $userid)) return true;
  foreach (enrol_get_users_courses($userid, true, 'id') as $course) {
   if (has_capability('moodle/course:update', \context_course::instance($course->id), $userid)) return true;
  }
  return false;
 }
 public static function can_view(?int $userid=null): bool { return self::can_author($userid); }
 public static function require_view(): void { if (!self::can_view()) throw new \required_capability_exception(\context_system::instance(),'moodle/course:update','nopermissions',''); }
 public static function require_author(): void { if (!self::can_author()) throw new \required_capability_exception(\context_system::instance(),'moodle/course:update','nopermissions',''); }
}
