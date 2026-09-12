<?php
require('../../config.php');

require_login();
\local_worksheetlibrary\service\access_service::require_view();

$versionid = required_param('versionid', PARAM_INT);
$version = $DB->get_record('wslib_version', ['id' => $versionid], '*', MUST_EXIST);
$item = $DB->get_record('wslib_item', ['id' => $version->itemid, 'archived' => 0], '*', MUST_EXIST);
if ($item->kind !== 'native' || empty($version->nativejson)) {
    throw new moodle_exception('PDF download is only available for Native worksheets');
}

$nativejson = (string)$version->nativejson;
$document = \local_digieranative\document\validator::validate_json($nativejson);
$layout = is_array($document['meta']['layout'] ?? null) ? $document['meta']['layout'] : [];
$orientation = ($layout['orientation'] ?? 'portrait') === 'landscape' ? 'L' : 'P';
$marginname = in_array(($layout['margin'] ?? 'normal'), ['normal', 'narrow', 'wide'], true)
    ? (string)$layout['margin']
    : 'normal';
$margin = ['narrow' => 10.0, 'normal' => 20.0, 'wide' => 28.0][$marginname];

require_once($CFG->libdir . '/tcpdf/tcpdf.php');

$context = context_system::instance();
$fs = get_file_storage();
$tempdir = make_request_directory('digiera-native-pdf');
$asseturls = [];
$replacements = [];
foreach ($fs->get_area_files(
    $context->id,
    'local_worksheetlibrary',
    'nativeasset',
    $versionid,
    'id ASC',
    false
) as $file) {
    if ($file->is_directory()) {
        continue;
    }
    $key = pathinfo($file->get_filename(), PATHINFO_FILENAME);
    if (!preg_match('/\Aa_[a-f0-9]{32}\z/', $key)) {
        continue;
    }
    $mime = $file->get_mimetype();
    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
    if ($extension === null) {
        continue;
    }
    $placeholder = 'https://digiera.invalid/native-asset/' . $key;
    $localpath = $tempdir . DIRECTORY_SEPARATOR . $key . '.' . $extension;
    if (file_put_contents($localpath, $file->get_content()) === false) {
        throw new moodle_exception('Could not prepare image for PDF');
    }
    $asseturls[$key] = $placeholder;
    $replacements[$placeholder] = $localpath;
}

$html = \local_digieranative\document\renderer::render_json($nativejson, 'preview', $asseturls);
if ($replacements) {
    $html = strtr($html, $replacements);
}
$html = '<style>
    .dgn-document{font-size:11pt;line-height:1.45;color:#111827}
    .dgn-document p{margin:0 0 7pt}
    .dgn-heading{margin:10pt 0 6pt;font-weight:bold}
    .dgn-list{margin:4pt 0 8pt 18pt}
    .dgn-table,.dgn-answer-table{border-collapse:collapse;width:100%}
    .dgn-table td,.dgn-answer-table td{border:1px solid #64748b;padding:5pt}
    .dgn-question{margin:10pt 0 4pt;font-weight:bold}
    .dgn-answer{border:1px dashed #94a3b8;padding:8pt;margin:4pt 0 8pt}
    .dgn-image img{max-width:100%;height:auto}
    .dgn-align-center{text-align:center}.dgn-align-right{text-align:right}.dgn-align-justify{text-align:justify}
    .dgn-page-break{page-break-after:always;height:1px}
    .dgn-horizontal-rule{border:0;border-top:1px solid #64748b}
</style>' . $html;

$pdf = new TCPDF($orientation, PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator('DIGIERA Moodle');
$pdf->SetAuthor(fullname($USER));
$pdf->SetTitle(format_string($item->name));
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins($margin, $margin, $margin);
$pdf->SetAutoPageBreak(true, $margin);
$pdf->SetFont('freesans', '', 10.5, '', true);
$pdf->AddPage();
$pdf->writeHTML($html, true, false, true, false, '');

$basename = clean_filename(format_string($item->name));
if ($basename === '') {
    $basename = 'worksheet';
}
if (!str_ends_with(core_text::strtolower($basename), '.pdf')) {
    $basename .= '.pdf';
}
$pdfbytes = $pdf->Output($basename, 'S');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . rawurlencode($basename) . '"; filename*=UTF-8\'\'' . rawurlencode($basename));
header('Content-Length: ' . strlen($pdfbytes));
header('Cache-Control: private, no-store, max-age=0');
echo $pdfbytes;
exit;
