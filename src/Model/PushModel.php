<?php
namespace Cvsgit\Model;

class PushModel extends CvsGitModel {

  private $aArquivos;
  private $sTitulo;

  public function adicionar(Array $aArquivos) {
    $this->aArquivos = $aArquivos;
  }

  public function setTitulo($sTitulo) {
    $this->sTitulo = $sTitulo;
  }

  /**
   * Salva no banco as modificacoes do comando cvsgit push
   *
   * @access public
   * @return void
   */
  public function salvar() {

    $aArquivosCommitados = $this->aArquivos;
    $sTituloPush = $this->sTitulo;

    $oArquivoModel = new ArquivoModel();
    $oDataBase = $this->getDataBase();
    $oDataBase->begin();

    /**
     * Cria header do push 
     * @var integer $iPull - pk da tabela pull
     */
    $iPull = $oDataBase->insert('pull', array(
      'project_id' => $this->getProjeto()->id,
      'title'      => $sTituloPush,
      'date'       => date('Y-m-d H:i:s')
    ));

    /**
     * Percorre array de arquivos commitados e salva no banco
     */
    foreach ( $aArquivosCommitados as $oCommit ) {

      $oDataBase->insert('pull_files', array(
        'pull_id' => $iPull,
        'name'    => $oCommit->getArquivo(),
        'type'    => $oCommit->getTipo(),
        'tag'     => $oCommit->getTagArquivo(),
        'message' => $oCommit->getMensagem()
      ));

      /**
       * Remove arqui da lista para commit 
       */
      $oArquivoModel->removerArquivo($oCommit->getArquivo());
    } 

    $oDataBase->commit();
  }

}
