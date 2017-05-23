<?php
namespace Cvsgit\Library;

class Shell {

  /**
   * Array de cores do terminal 
   */
  private static $aColors = array(
    'light_red'   => '[1;31m',
    'light_green' => '[1;32m',
    'yellow'      => '[1;33m',
    'light_blue'  => '[1;34m',
    'magenta'     => '[1;35m',
    'light_cyan'  => '[1;36m',
    'white'       => '[1;37m',
    'normal'      => '[0m',
    'black'       => '[0;30m',
    'red'         => '[0;31m',
    'green'       => '[0;32m',
    'brown'       => '[0;33m',
    'blue'        => '[0;34m',
    'cyan'        => '[0;36m',
    'bold'        => '[1m',
    'underscore'  => '[4m',
    'reverse'     => '[7m'
  );

  /**
   * Retorna o numero de colunas do atual shell 
   * @return int numero de colunas
   */
  static public function columns() {
    return exec('/usr/bin/env tput cols');
  }

  /**
   * Retorna uma string colorida
   * - ao usar less, se nao colorir, usar export LESS="-erX"
   *
   * @param string $sText
   * @param string $sColor
   * @static
   * @access public
   * @return string
   */
  static public function colorString($sText, $sColor = 'normal') {

    $sOutput = self::$aColors[ strtolower($sColor) ];

    if ($sOutput == "") {
      $sOutput = "[0m"; 
    }

    return chr(27) . $sOutput. $sText . chr(27) . "[0m";
  }

  /**
   * Retorna o o tamanho da string sem contar as cores
   *
   * @param string $sText
   * @static
   * @access public
   * @return integer
   */
  static public function strlen($sValue) {

    $aColors      = array_merge( array_values(self::$aColors), array("[0m", chr(27)) );
    $sStripColors = str_replace($aColors, '', $sValue);

    return mb_strlen($sStripColors);
  }

  /**
   * Retorna string para strings que contem cor 
   *
   * @param string $sString
   * @param integer $iLength
   * @static
   * @access public
   * @return string
   */
  static public function str_pad($sString, $iLength) {

    $iRealLength = mb_strlen($sString);
    $iShowLength = self::strlen($sString);
    $iLength    += $iRealLength - $iShowLength;

    return str_pad($sString, $iLength);
  }

}
