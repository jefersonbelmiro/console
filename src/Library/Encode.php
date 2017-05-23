<?php
namespace Cvsgit\Library;

class Encode {

  static public function ISOToUTF8($sText) {
    return mb_convert_encoding($sText, 'UTF-8', 'ISO-8859-1'); 
  }

  static public function UTF8ToISO($sText) {
    return mb_convert_encoding($sText, 'ISO-8859-1', 'UTF-8'); 
  }

  static public function toUTF8($sText) {
    return mb_convert_encoding($sText, "UTF-8", mb_detect_encoding($sText, "UTF-8, ISO-8859-1, ISO-8859-15", true));
  }

  static public function toISO($sText) {
    return mb_convert_encoding($sText, "ISO-8859-1", mb_detect_encoding($sText, "UTF-8, ISO-8859-1, ISO-8859-15", true)); 
  }

}
