<?php
namespace Cvsgit\Model;

class Arquivo {

  const COMANDO_COMMITAR_TAGGEAR    = 0;
  const COMANDO_ADICIONAR_TAG       = 1;
  const COMANDO_REMOVER_TAG         = 2;
  const COMANDO_REMOVER_ARQUIVO_TAG = 3;
  const COMANDO_COMMITAR            = 4;

  public $sArquivo       = null;
  public $sMensagem      = null;
  public $sTipo          = null;
  public $iTagMensagem   = null;
  public $iTagArquivo    = null;
  public $iComando       = 0;

  public function __construct() {

  }

  public function setMensagem($sMensagem) {
    $this->sMensagem = $sMensagem;
  }

  public function getMensagem() {
    return $this->sMensagem;
  }

  public function setTipo($sTipo) { 
    $this->sTipo = $sTipo;
  }

  public function getTipo() { 
    return $this->sTipo;
  }

  public function setArquivo($sArquivo) { 
    $this->sArquivo = $sArquivo;
  }

  public function getArquivo() { 
    return $this->sArquivo;
  }

  public function setTagMensagem($iTagMensagem) {
    $this->iTagMensagem = $iTagMensagem;
  }

  public function getTagMensagem() {
    return $this->iTagMensagem;
  }

  public function setTagArquivo($iTagArquivo) {
    $this->iTagArquivo = $iTagArquivo;
  }
   
  public function getTagArquivo() {
    return $this->iTagArquivo;
  }

  public function setComando($iComando) { 
    $this->iComando = $iComando;
  }

  public function getComando() { 
    return $this->iComando;
  }

}
