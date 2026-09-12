<?php
require('../../config.php');

require_login();
\local_worksheetlibrary\service\access_service::require_view();

$versionid = required_param('versionid', PARAM_INT);
$version = $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
$item = $DB->get_record('wslib_item', ['id' => $version->itemid, 'archived' => 0], '*', MUST_EXIST);
if ($item->kind !== 'native' || empty($version->nativejson)) {
    throw new moodle_exception('Print is only available for Native worksheets');
}

$nativejson = (string)$version->nativejson;
$document = \local_digieranative\document\validator::validate_json($nativejson);
$layout = is_array($document['meta']['layout'] ?? null) ? $document['meta']['layout'] : [];
$orientation = ($layout['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
$marginname = in_array(($layout['margin'] ?? 'normal'), ['normal', 'narrow', 'wide'], true)
    ? (string)$layout['margin']
    : 'normal';
$margin = ['narrow' => '10mm', 'normal' => '20mm', 'wide' => '28mm'][$marginname];
$asseturls = \local_worksheetlibrary\service\native_asset_service::asset_urls($versionid);
$html = \local_digieranative\document\renderer::render_json($nativejson, 'preview', $asseturls);
$title = format_string($item->name);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= s($title) ?></title>
<style>
@page { size: A4 <?= $orientation ?>; margin: <?= $margin ?>; }
html,body{margin:0;padding:0;background:#fff;color:#111827;font-family:Arial,"Helvetica Neue",sans-serif;font-size:11pt;line-height:1.45}
.dgn-print-sheet{box-sizing:border-box;width:100%;margin:0 auto}
.dgn-document p{margin:0 0 7pt}.dgn-heading{margin:10pt 0 6pt;font-weight:700}.dgn-list{margin:4pt 0 8pt 18pt}
.dgn-table,.dgn-answer-table{border-collapse:collapse;width:100%}.dgn-table td,.dgn-answer-table td{border:1px solid #64748b;padding:5pt}
.dgn-question{margin:10pt 0 4pt;font-weight:700}.dgn-answer{border:1px dashed #94a3b8;padding:8pt;margin:4pt 0 8pt}
.dgn-image{text-align:center}.dgn-image img{max-width:100%;height:auto}.dgn-align-left{text-align:left}.dgn-align-center{text-align:center}.dgn-align-right{text-align:right}.dgn-align-justify{text-align:justify}
.dgn-page-break{page-break-after:always;height:1px}.dgn-horizontal-rule{border:0;border-top:1px solid #64748b}
.dgn-print-toolbar{position:sticky;top:0;z-index:2;display:flex;gap:8px;justify-content:flex-end;padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
.dgn-print-toolbar button{border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:7px 12px;font:inherit;cursor:pointer}.dgn-print-toolbar .primary{background:#0d6efd;border-color:#0d6efd;color:#fff}
@media print{.dgn-print-toolbar{display:none!important}.dgn-print-sheet{width:auto}.dgn-page-break{break-after:page}}
</style>
</head>
<body>
<div class="dgn-print-toolbar"><button type="button" onclick="window.close()">Đóng</button><button class="primary" type="button" onclick="window.print()">In</button></div>
<main class="dgn-print-sheet"><?= $html ?></main>
<script>
window.addEventListener('load', function() {
    window.setTimeout(function() { window.print(); }, 180);
});
</script>
</body>
</html>
