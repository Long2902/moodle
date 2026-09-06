<?php
namespace local_digieramedia\marker;
final class reference_parser {
    private const RX='/\[\[digiera-ref:([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12})\]\]/';
    public static function extract(string $text): array {
        preg_match_all(self::RX,$text,$matches,PREG_OFFSET_CAPTURE);
        $out=[];
        foreach ($matches[0] as $i=>$full) {
            $out[]=['uuid'=>strtolower($matches[1][$i][0]),'offset'=>$full[1],'length'=>strlen($full[0]),'marker'=>$full[0]];
        }
        return $out;
    }
    public static function marker(string $uuid): string { return '[[digiera-ref:'.strtolower($uuid).']]'; }
}
