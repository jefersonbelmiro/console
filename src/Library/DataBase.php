<?php
namespace Cvsgit\Library;
use PDO;

abstract class DataBase extends PDO {

  public function __construct($sDrive, $sParametros) {
   
    parent::__construct($sDrive . ':' . $sParametros);
    $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $this;
  }

  /**
   * Inicia uma transacao
   *
   * @access public
   * @return bool
   */
  public function begin() {
    return $this->beginTransaction();
  }

  /**
   * Grava as modificaoes
   *
   * @access public
   * @return bool
   */
  public function commit() {

    if ( !$this->inTransaction() ) {
      return false;
    }

    return parent::commit();
  }

  /**
   * Desfaz alteracoes feitas
   *
   * @access public
   * @return bool
   */
  public function rollBack() {

    if ( !$this->inTransaction() ) {
      return false;
    }

    return parent::rollBack();
  }

  public function insert($tabela, Array $dados) {

    $campos = implode(", ", array_keys($dados));
    $valores = "'".implode("','", array_values($dados))."'";

    $insert = $this->exec(" INSERT INTO `{$tabela}` ({$campos}) VALUES ({$valores}) ");

    /**
     * ultimo id
     */
    return $this->lastInsertId();
  }

  public function update($tabela, Array $dados, $where = null) {

    $where = !empty($where) ? 'WHERE '.$where : null;

    foreach ( $dados as $ind => $val ) {
      $campos[] = "{$ind} = '{$val}'";
    }

    $campos = implode(", ", $campos);
    return $this->query(" UPDATE `{$tabela}` SET {$campos} {$where} ");
  }

  public function delete($tabela, $where = null) {

    $where = !empty($where) ? 'WHERE ' . $where : null;
    return $this->exec(" DELETE FROM `{$tabela}` {$where} ");
  }

  public function select($sSql) {

    $oQuery = $this->query("$sSql");
    $oQuery->setFetchMode(PDO::FETCH_OBJ);

    return $oQuery->fetch();
  }

  public function selectAll($sSql) {

    $oQuery = $this->query("$sSql");
    $oQuery->setFetchMode(PDO::FETCH_OBJ);
    $aResult = $oQuery->fetchAll();

    if ( !$aResult ) {
      $aResult = array();
    }

    return $aResult;
  }
  

  public function execSql($sSql) {
    
    $oQuery = $this->exec("$sSql");
    return $oQuery;
  }
}
