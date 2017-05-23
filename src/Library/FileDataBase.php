<?php
namespace Cvsgit\Library;

/**
 * Banco de dados usando arquivo sqlite
 *
 * @uses DataBase
 * @package Library
 * @version $id$
 */
class FileDataBase extends DataBase {

  public function __construct($sArquivoBanco) {

    /**
     * Arquivo do banco nao existe
     */
    if ( !file_exists($sArquivoBanco) ) {
      throw new Exception("Arquivo não existe: $sArquivoBanco");
    }

    parent::__construct('sqlite', $sArquivoBanco);
    return $this;
  }
}
