<?php
namespace Cvsgit\Library;

class String {

  const REGEX_STRING = '([^ ]+?)(?: |(?<!\\\\)"|(?<!\\\\)\'|$)';
  const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\')';

  /**
   * tokenize
   *
   * @param string $input
   * @static
   * @access public
   * @return string
   */
  public static function tokenize($input) {

    if ( !is_scalar($input) ) {
      throw new Exception("Config->tokenize: Parametro inválido");
    }
    
    $input = preg_replace('/(\r\n|\r|\n|\t)/', ' ', $input);

    $tokens = array();
    $length = strlen($input);
    $cursor = 0;

    while ($cursor < $length) {

      if (preg_match('/\s+/A', $input, $match, null, $cursor)) {
      } elseif (preg_match('/([^="\' ]+?)(=?)('.self::REGEX_QUOTED_STRING.'+)/A', $input, $match, null, $cursor)) {
        $tokens[] = $match[1].$match[2].stripcslashes(str_replace(array('"\'', '\'"', '\'\'', '""'), '', substr($match[3], 1, strlen($match[3]) - 2)));
      } elseif (preg_match('/'.self::REGEX_QUOTED_STRING.'/A', $input, $match, null, $cursor)) {
        $tokens[] = stripcslashes(substr($match[0], 1, strlen($match[0]) - 2));
      } elseif (preg_match('/'.self::REGEX_STRING.'/A', $input, $match, null, $cursor)) {
        $tokens[] = stripcslashes($match[1]);
      } else {
        // should never happen
        // @codeCoverageIgnoreStart
        throw new \InvalidArgumentException(sprintf('Unable to parse input near "... %s ..."', substr($input, $cursor, 10)));
        // @codeCoverageIgnoreEnd
      }

      $cursor += strlen($match[0]);
    }

    return $tokens;
  }


  /**
   * Remover os acentos de uma string
   *
   * @param string $sString
   * @static
   * @access public
   * @return string
   */
  public static function removeAccents($sString){

    $sFrom   = 'ÀÁÃÂÉÊÍÓÕÔÚÜÇàáãâéêíóõôúüç';
    $sTo     = 'AAAAEEIOOOUUCaaaaeeiooouuc';
    $sString = strtr($sString, $sFrom, $sTo);

    return $sString;
  }

}
