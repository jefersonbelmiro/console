<?php 
namespace Cvsgit\Library;

/**
 * Log das aplicacoes 
 * 
 * @package System
 * @version 1.0
 */
class Log {

  /**
   * Prefixo da linha:
   * - {funcao}  - Funcao onde chama o log
   * - {arquivo} - Arquivo onde foi chamado funcao
   * - {linha}   - Linha onde foi chamado funcao
   * - {hora}    - Hora da chamada do log
   * - {tempo}   - Tempo que passou at? a chamada da funcao de write
   *
   * exemplo: '[{arquivo}][{linha}][{tempo}] {mensagem}' 
   * ira gravar linha: [path/arquivo.php][33][00:01:34] mensagem do log
   */
  private $sMaskLine = "[{hora}] [{funcao}:{linha}]\n{mensagem}";

  /**
   * Caminho do arquivo de log
   * 
   * @var string
   * @access private
   */
  private $sPathFile = '/tmp/console_log';

  /**
   * Tempo para medir intervaloo entre os logs
   * 
   * @var float
   * @access private
   */
  private $startTime = 0;

  /**
   * Instancia do Log
   * 
   * @static
   * @var mixed
   * @access public
   */
  private static $oInstance;

  public function __construct() {
    $this->startTime = microtime(true); 
  }

  public static function getInstance() {
    
    if ( empty(self::$oInstance) ) {
      self::$oInstance = new Log();
    } 

    return self::$oInstance;
  }

  public function setFile($sPathFile) {
    $this->sPathFile = $sPathFile;
  }

  public function clear() {

    if ( file_exists($this->sPathFile) ) {
      return unlink($this->sPathFile);
    }

    return false;
  }

  public function setMaskLine($sMaskLine) {
    $this->sMaskLine = $sMaskLine;
  }

  public function write($mensagem) {

    $aTrace   = $this->getCalled();
    $sArquivo = str_replace(getcwd() . '/', '', $aTrace['sFile']);
    $iLinha   = $aTrace['iLine'];
    $sFuncao  = $aTrace['sFunction'];

    $sMensagemLog  = $this->sMaskLine;
    $sMensagemLog  = str_replace('{mensagem}', $mensagem ,      $sMensagemLog);
    $sMensagemLog  = str_replace('{linha}',    $iLinha,         $sMensagemLog);
    $sMensagemLog  = str_replace('{arquivo}',  $sArquivo,       $sMensagemLog);
    $sMensagemLog  = str_replace('{funcao}',   $sFuncao,        $sMensagemLog);
    $sMensagemLog  = str_replace('{hora}',     date('H:i:s'),   $sMensagemLog);
    $sMensagemLog  = str_replace('{tempo}',    $this->tempo() , $sMensagemLog);

    file_put_contents($this->sPathFile, $sMensagemLog . "\n", FILE_APPEND);
  }

  public function header() {

    $sHeaderLog = '';

    if ( file_exists($this->sPathFile) ) {

      $aFileTime = fileatime($this->sPathFile);

      if ( strtotime(date('Y-m-d', $aFileTime)) >= strtotime(date('Y-m-d')) ) {
        return false;
      }

      $sHeaderLog = "\n\n"; 
    } 

    $sHeaderLog .= "---------------------------------------------------------------------\n"; 
    $sHeaderLog .= "------------------------[ " . date('d/m/Y') . " ]-------------------------------\n"; 
    $sHeaderLog .= "---------------------------------------------------------------------\n"; 

    return file_put_contents($this->sPathFile, $sHeaderLog, FILE_APPEND);
  }

  public function getCalled() {
    
    $aBackTrace = debug_backtrace();
    $sFunction  = null;
    $sClass     = null;
    $sFile      = null;
    $iLine      = null;

    foreach ($aBackTrace as $iTrace => $aTrace) {


      if ( !empty($aTrace['class']) && !empty($aTrace['function']) ) {

        if ( $aTrace['class'] == 'System' && $aTrace['function'] == 'log' ) {

          /**
           * linha e arquivo
           */
          $sFile = $aTrace['file'];
          $iLine = $aTrace['line'];

          /**
           * funcao e classe
           */
          if ( !empty($aBackTrace[$iTrace + 1]) ) {

            $sFunction = $aBackTrace[$iTrace + 1]['function'];
            $sClass = $aBackTrace[$iTrace + 1]['class'];
          }
        
          break;
        }
      }
    }

    return array(
      'sFunction' => $sFunction,
      'sClass'    => $sClass,
      'sFile'     => $sFile,
      'iLine'     => $iLine,
    );
  }

  public function tempo() {

    $nFinal = microtime(true) - $this->startTime; 

    // segundos e milesegundos
    $sec = intval($nFinal);
    $micro = $nFinal - $sec;

    $final = strftime('%T', mktime(0, 0, $sec)) . str_replace('0.', '.', sprintf('%.3f', $micro));

    return $final;
  }

}
