<?php
namespace Cvsgit\Model;

use Exception;

use \Cvsgit\Library\FileDataBase;

class CvsGitModel {

  protected static $oDataBase;
  protected static $oProjeto;
  protected static $sRepositorio;

  public function __construct() {

    if ( !file_exists('CVS/Repository') ) {
      throw new Exception("Diretório atual não é um repositorio CVS");
    }

    if ( empty( self::$sRepositorio) ) {

      $sDiretorioRepositorio = trim(file_get_contents('CVS/Repository'));
      $aDiretorioRepositorio = explode('/', $sDiretorioRepositorio);
      self::$sRepositorio = $aDiretorioRepositorio[0];
    }

    if ( empty(self::$oDataBase) || empty(self::$oProjeto) ) {

      if ( !file_exists(CONFIG_DIR . $this->getRepositorio() .  '.db') ) {
        throw new Exception("Diretório atual não inicializado, utilize o comando cvsgit init");
      }

      self::$oDataBase = new FileDataBase(CONFIG_DIR . $this->getRepositorio() .  '.db');
      $this->buscarProjeto();
    }
  }

  public function getDataBase() {
    return self::$oDataBase;
  }

  public function getProjeto() {
    return self::$oProjeto;
  }
  
  public function getRepositorio() {
    return self::$sRepositorio;
  }

  public function buscarProjeto() {

    $sDiretorioAtual = getcwd();
    $sRepositorio    = $this->getRepositorio();
    $aProjetos       = self::$oDataBase->selectAll("select * from project where name = '$sRepositorio' or path = '$sDiretorioAtual'");

    foreach( $aProjetos as $oProjeto ) {

      /**
       * Repositorio 
       */
      if ( $oProjeto->name == $sRepositorio || $oProjeto->path == $sDiretorioAtual ) {

        self::$oProjeto = $oProjeto;
        return true;
      }

      /**
       * Inicio do diretorio atual contem projeto 
       */
      if ( strpos($sDiretorioAtual, $oProjeto->path) !== false && strpos($sDiretorioAtual, $oProjeto->path) == 0 ) {

        self::$oProjeto = $oProjeto;
        return true;
      }
    }   

    throw new Exception("Diretório atual não inicializado, utilize o comando cvsgit init");
  }

}
