<?php
namespace Cvsgit\Library;

/**
 * Tabela para console
 * 
 * @package Console 
 * @version 1.0
 */
class Table {

  protected $aHeaders = array();
  protected $aWidth   = array();
  protected $aRows    = array();

  public function __construct(array $headers = null, array $rows = null) {

    if ( !empty($headers) ) {
      $this->setHeaders($headers);
    }

    if ( !empty($rows) ) {
        $this->setRows($rows);
    }
  }

  protected function checkRow(array $row) {

    foreach ($row as $column => $str) {

      $width = $this->strlen($str);

      if (!isset($this->aWidth[$column]) || $width > $this->aWidth[$column]) {
        $this->aWidth[$column] = $width;
      }
    }

    return $row;
  }

  public function render( $lEspacos = false ) {
    
    $borderStr = '';
    $sTabela = '';

    if ( !$lEspacos ) {

      foreach ($this->aHeaders as $column => $header) {
      
        if ( $column > 0 ) {
          $borderStr .= '+';
        }
      
        $borderStr .= '-' . str_repeat('-', $this->aWidth[$column]) . '-';
      }

      $sTabela  = "\n" . $this->renderRow($this->aHeaders);
      $sTabela .= "\n" . $borderStr;
    }

    foreach ($this->aRows as $row) {
      $sTabela .= "\n" . $this->renderRow($row, $lEspacos);
    }

    max($this->aWidth);
    return $sTabela . "\n";
  }

  protected function renderRow(array $row, $lEspacos = false) {

    $render = '';

    foreach ($row as $column => $val) {

      $render .= '';

      if ( $column > 0 && !$lEspacos ) {
        $render .= '|';
      }

      if ( !$lEspacos) {
        $render .= ' ';
      } 

      $render .= $this->str_pad($val, $this->aWidth[$column]) . ' ';
    }

    return $render;
  }

  public function sort($column) {

    if (!isset($this->aHeaders[$column])) {

      trigger_error('Coluna invalida no index ' . $column, E_USER_NOTICE);
      return;
    }

    usort($this->aRows, function($a, $b) use ($column) {
      return strcmp($a[$column], $b[$column]);
    });
  }

  public function setHeaders(array $headers) {
    $this->aHeaders = $this->checkRow($headers);
  }

  public function addRow(array $row) {
    $this->aRows[] = $this->checkRow($row);
  }

  public function setRows(array $rows) {

    $this->aRows = array();

    foreach ($rows as $row) {
      $this->addRow($row);
    }
  }

  public function getRows() {
    return $this->aRows;
  }

  public function getWidths() {
    return $this->aWidth;
  }

  /**
   * Retorna o o tamanho da string sem contar as cores
   *
   * @param string $sText
   * @static
   * @access public
   * @return integer
   */
  public function strlen($sValue) {
  
    // $sValue = Encode::toISO($sValue);
    $sValue = strip_tags($sValue);
    $sValue = mb_strlen($sValue);

    return $sValue;
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
  public function str_pad($sString, $iLength) {

    $iRealLength = mb_strlen($sString);
    $iShowLength = $this->strlen($sString);
    $iLength    += $iRealLength - $iShowLength;

    return str_pad($sString, $iLength);
  }

}
