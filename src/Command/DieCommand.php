<?php

namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;
use \Cvsgit\Library\Encode;

/**
 * Classe responsável pela remoção de arquivos do CVS
 *
 * @author Luiz Marcelo Schmitt <luiz.marcelo@dbseller.com.br>
 * @package CVS
 */
class DieCommand extends Command {

  /**
   * Carrega as configurações e adiciona os helpers
   */
  public function configure() {

    $this->setName('die');
    $this->setDescription('Remove o(s) arquivo(s) commitado(s) no CVS');
    $this->setHelp('Remove o(s) arquivo(s) commitado(s) no CVS');

    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para remover');

    $this->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Mensagem de log' );
  }

  /**
   * Executa o comando de remover do CVS
   *
   * @param \Symfony\Component\Console\Input\InputInterface   $oInput
   * @param \Symfony\Component\Console\Output\OutputInterface $oOutput
   * @return int|void
   * @throws Exception
   */
  public function execute($oInput, $oOutput) {

    $this->oInput  = $oInput;
    $this->oOutput = $oOutput;
    $this->oConfig = $this->getApplication()->getConfig();

    $this->processarParametros();
    $this->validar();
    $this->enviar();
  }

  /**
   * Pega os valores passados por parametros
   *
   * @throws Exception
   */
  private function processarParametros() {

    $this->aArquivos      = array();
    $aArquivosParaRemover = $this->oInput->getArgument('arquivos');

    foreach ($aArquivosParaRemover as $sArquivo) {

      $sArquivo = realpath($sArquivo);

      $this->aArquivos[$sArquivo] = array(
        'file' => $sArquivo
      );
    }
  }

  /**
   * Executa uma validação básica
   *
   * @throws Exception
   */
  private function validar() {

    if (empty($this->aArquivos)) {
      throw new Exception('Informe o(s) arquivo(s) que deseja remover!');
    }

    $this->aMensagemErro = array();
    $iErros              = 0;

    foreach ($this->aArquivos as $aArquivo) {

      $sArquivo = $this->getApplication()->clearPath($aArquivo['file']);
      $sErro    = '<error>[x]</error>';

      if ( !file_exists($aArquivo['file']) ) {

        $sArquivo = $sErro . ' ' . $sArquivo;
        $this->aMensagemErro[$sArquivo][] = "Arquivo não existe!";
        $iErros++;
      }
    }

    /**
     * Encontrou erros
     */
    if ( $iErros > 0 ) {

      $this->oOutput->writeln("\n " . $iErros . " erro(s) encontrado(s):");

      foreach ( $this->aMensagemErro as $sArquivo => $aMensagemArquivo ) {

        $this->oOutput->writeln("\n -- " . $sArquivo);
        $this->oOutput->writeln("    " . implode("\n    ", $aMensagemArquivo));
      }

      $this->oOutput->writeln('');
      exit(1);
    }
  }

  /**
   * Remove os arquivos do diretório e do CVS depois commita
   */
  private function enviar() {

    $oDialog   = $this->getHelperSet()->get('dialog');
    $sConfirma = $oDialog->ask($this->oOutput, 'Remover e Commitar?: (s/N): ');

    if ( strtoupper($sConfirma) != 'S' ) {
      exit(0);
    }

    foreach ($this->aArquivos as $aArquivo) {

      if (!$this->removerArquivo($aArquivo['file'])) {
        continue;
      }

      if (!$this->commitArquivo($aArquivo['file'])) {
        continue;
      }
    }

    $this->oOutput->writeln('');
  }

  /**
   * Remove o arquivo do diretório e do CVS
   *
   * @param string $sArquivo
   * @return bool
   */
  private function removerArquivo($sArquivo) {

    $sArquivoRemover = $this->getApplication()->clearPath($sArquivo);
    $sRemoveArquivo  = Encode::toUTF8('/bin/rm ' . escapeshellarg($sArquivoRemover));
    $sCommandoRemove = "{$sRemoveArquivo} 2> /tmp/cvsgit_last_error";

    $this->oOutput->writeln('');
    $this->oOutput->writeln("--Removendo do diretório: <comment>[{$sArquivoRemover}]</comment> {$sCommandoRemove}");

    exec($sCommandoRemove, $aRetornoCommandoRemove, $iStatusCommandoRemove);

    if ($iStatusCommandoRemove > 0) {

      $this->getApplication()->displayError("Erro ao remover arquivo: {$sRemoveArquivo}", $this->oOutput);
      return false;
    }

    $sRemoveArquivoCVS  = Encode::toUTF8('cvs remove ' . escapeshellarg($sArquivoRemover));
    $sCommandoRemoveCVS = "{$sRemoveArquivoCVS} 2> /tmp/cvsgit_last_error";

    $this->oOutput->writeln('');
    $this->oOutput->writeln("--Removendo do CVS: <comment>[{$sArquivoRemover}]</comment> {$sRemoveArquivoCVS}");

    exec($sCommandoRemoveCVS, $aCommandoRemoveCVS, $iStatusCommandoRemoveCVS);

    if ($iStatusCommandoRemoveCVS > 0) {

      $this->getApplication()->displayError("Erro ao remover arquivo do CVS: {$sRemoveArquivoCVS}", $this->oOutput);
      return false;
    }

    return true;
  }

  /**
   * Commita o arquivo removido
   *
   * @param string $sArquivo
   * @return bool
   */
  private function commitArquivo($sArquivo) {

    $sMensagemCommit = $this->oInput->getOption('message');
    $sMensagemCommit = (empty($sMensagemCommit)) ? 'Arquivo removido do CVS.' : $sMensagemCommit;
    $sArquivoCommit  = $this->getApplication()->clearPath($sArquivo);
    $sCommitArquivo  = Encode::toUTF8("cvs commit -m '{$sMensagemCommit}' " . escapeshellarg($sArquivoCommit));
    $sComandoCommit  = "{$sCommitArquivo} 2> /tmp/cvsgit_last_error";

    $this->oOutput->writeln('');
    $this->oOutput->writeln("--Commitando no CVS <comment>[{$sArquivoCommit}]</comment> {$sComandoCommit}");

    exec($sComandoCommit, $aRetornoComandoCommit, $iStatusComandoCommit);

    if ( $iStatusComandoCommit > 0 ) {

      $this->getApplication()->displayError("Erro ao commitar arquivo: {$sArquivoCommit}", $this->oOutput);
      return false;
    }

    return true;
  }
}
