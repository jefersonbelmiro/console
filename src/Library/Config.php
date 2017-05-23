<?php
namespace Cvsgit\Library;

class Config {

  private $oConfig;

  public function __construct($sArquivo = null, $sFormato = 'json') {

    if ( empty($sArquivo) ) {
      return false;
    }

    if ( !file_exists($sArquivo) ) {
      throw new Exception("Arquivo não encontrado: $sArquivo");
    }

    switch($sFormato) {

      case 'json' :

        $sConfiguracoes = file_get_contents($sArquivo);
        $this->oConfig  = $this->jsonCleanDecode($sConfiguracoes);

        if( is_null($this->oConfig) ) {
          throw new Exception("Arquivo JSON de configuração inválido: $sArquivo");
        }

      break;

      case 'array' :
        $this->oConfig = require $sArquivo;
      break;

      default :
        throw new Exception("Formato inválido: " . $sFormato);
      break;
    }

  }

  /**
   * Retorna uma ou todas as configuracoes
   *
   * @param mixed $sConfig
   * @access public
   * @return void
   */
  public function get($sConfig = null) {

    if ( !empty($sConfig) && isset($this->oConfig->$sConfig) ) {
      return $this->oConfig->$sConfig;
    }

    if ( empty($sConfig) ) {
      return $this->oConfig;
    }

    return null;
  }

  /**
   * Clean comments of json content and decode it with json_decode().
   * Work like the original php json_decode() function with the same params
   *
   * @param   string  $json    The json string being decoded
   * @param   bool    $assoc   When TRUE, returned objects will be converted into associative arrays.
   * @param   integer $depth   User specified recursion depth. (>=5.3)
   * @param   integer $options Bitmask of JSON decode options. (>=5.4)
   * @return  string
   */
  private function jsonCleanDecode($json, $assoc = false, $depth = 512, $options = 0) {

    // search and remove comments like /* */ and //
    $json = preg_replace("#(?<![a-z])(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]?(//).*)#", '', $json);


    if(version_compare(phpversion(), '5.4.0', '>=')) {
      $json = json_decode($json, $assoc, $depth, $options);
    }
    elseif(version_compare(phpversion(), '5.3.0', '>=')) {
      $json = json_decode($json, $assoc, $depth);
    }
    else {
      $json = json_decode($json, $assoc);
    }

    return $json;
  }

}
