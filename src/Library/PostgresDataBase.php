<?php
namespace Cvsgit\Library;

/**
 * Acessa banco de dados postgres
 *
 * @uses DataBase
 * @package Library
 * @version $id$
 */
class PostgresDataBase extends DataBase {

  public function __construct($sBase, $sHost, $sPort = "5432", $sUser="postgres") {

    $sParametros = "dbname={$sBase}; host={$sHost}; user={$sUser}; port={$sPort}";
    parent::__construct('pgsql', $sParametros);
    
    return $this;
  }
}
