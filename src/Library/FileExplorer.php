<?php
namespace Cvsgit\Library;

/**
 *
 * @author Rafalel Serpa Nery
 * @abstract
 */
abstract class FileExplorer {

  /**
   * Mostra os Itens de Um diretório
   * @param string  $sDiretorio        -> Diretório a ser pesquisado
   * @param boolean $lMostraDiretorios -> mostra pastas
   * @param boolean $lMostraArquivos   -> mostra arquivos
   * @param string  $sRegexpIgnorar    -> expressão regular para ignorar casos Ex. $sRegexpIgnorar = "/CVS/";
   * @param boolean $lRecursivo        -> pesquisar diretório recursivamente
   * @return $aRetorno - Array contendo a string dos itens encontrados nos diretórios
   */
  public static function listarDiretorio( $sDiretorio, $lMostraDiretorios = true, $lMostraArquivos = true, $sRegexpIgnorar = null, $lRecursivo = false) {

    $aRetorno = array();
    if ( !is_dir( $sDiretorio ) ) {
      throw new Exception("Nao e um diretorio.");
    }

    if ( !is_readable( $sDiretorio ) ) {
      throw new Exception("Diretorio não Pode ser Lido.");
    }

    $rDiretorio = opendir( $sDiretorio );

    if ( !$rDiretorio ) {
      throw new Exception('Nao foi possivel abrir o Diretorio');
    }

    while ( ( $sArquivo = readdir( $rDiretorio ) ) !== false ) {

      // echo "\nIgnorar Diretorio: $sDiretorio/$sArquivo - " . is_dir("$sDiretorio/$sArquivo") ." - ". !$lMostraDiretorios;
      if ( is_dir("$sDiretorio/$sArquivo")  && !$lMostraDiretorios ) {
        continue;
      }
      // echo "\nIgnorar Arquivo: $sDiretorio/$sArquivo - " .  !is_dir("$sDiretorio/$sArquivo") . " - " . !$lMostraArquivos;
      //var_dump("$sDiretorio/$sArquivo",is_dir("$sDiretorio/$sArquivo"), $lMostraDiretorios, $lMostraArquivos);
      if ( !is_dir("$sDiretorio/$sArquivo") && !$lMostraArquivos ) {
        continue;
      }

      $lAchouExpressao = is_null( $sRegexpIgnorar ) ? false : preg_match( $sRegexpIgnorar, $sArquivo );

      if ( $sArquivo == "." || $sArquivo == ".." || $lAchouExpressao ) {
        continue;
      }

      if ( is_dir( "$sDiretorio/$sArquivo" ) && is_readable( "$sDiretorio/$sArquivo" ) && $lRecursivo ) {
        $aRetorno = array_merge($aRetorno, FileExplorer::listarDiretorio( "$sDiretorio/$sArquivo",
                                                                          $lMostraDiretorios ,
                                                                          $lMostraArquivos,
                                                                          $sRegexpIgnorar,
                                                                          $lRecursivo ) );
      }

      $aRetorno[] = "{$sDiretorio}/{$sArquivo}";
    }
    
    return $aRetorno;
  }
}
