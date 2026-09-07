<?php
namespace local_worksheetlibrary\service;
final class blank_docx_service {
 public static function create_to_temp(string $title='Phiếu học tập'): string { global $CFG; require_once($CFG->libdir.'/filelib.php'); $base=make_request_directory(); $files=[
  '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
  '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
  'word/document.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($title,ENT_XML1).'</w:t></w:r></w:p><w:p/><w:sectPr/></w:body></w:document>'
 ]; foreach($files as $rel=>$data){$p=$base.'/'.$rel; if(!is_dir(dirname($p)))mkdir(dirname($p),0770,true); file_put_contents($p,$data);} $zip=$base.'/blank.docx'; $packer=get_file_packer('application/zip'); $map=[]; foreach(array_keys($files) as $rel)$map[$rel]=$base.'/'.$rel; if(!$packer->archive_to_pathname($map,$zip)) throw new \moodle_exception('Could not create DOCX'); return $zip; }
}
