<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;

class AnnotateCommand extends Command {

  /**
   * Configura comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('annotate');
    $this->setDescription('cvs annotate');
    $this->setHelp('cvs annotate');
    $this->addArgument('arquivo', InputArgument::REQUIRED, 'Arquivo para comparação');
  }

  /**
   * Executa comando 
   *
   * @param OutputInterface $oInput
   * @param InputInterface $oOutput
   * @access public
   * @return void
   */
  public function execute($oInput, $oOutput) {

    $sArquivo = $oInput->getArgument('arquivo');

    if ( !file_exists($sArquivo) ) {
      throw new Exception("Arquivo não existe: $sArquivo");
    }

    /**
     * Lista informacoes do commit, sem as tags
     */
    exec('cvs annotate ' . $sArquivo . ' 2> /tmp/cvsgit_last_error', $aRetornoComandoAnnotate, $iStatusComandoAnnotate);

    if ( $iStatusComandoAnnotate > 0 ) {

      throw new Exception(
        "Erro ao execurar cvs log -N $sArquivo". PHP_EOL . $this->getApplication()->getLastError(), $iStatusComandoAnnotate
      );
    }

      
    $sDiretorioTemporario = '/tmp/cvs-annotate/';
    $sArquivoPrograma     = $sDiretorioTemporario . 'arquivo_' . basename($sArquivo);
    $sArquivoDados        = $sDiretorioTemporario . 'dados_' . basename($sArquivo); 

    $sConteudoArquivoPrograma = '';
    $sConteudoArquivoDados    = '';

    $iColunasDados = 0;

    /**
     * Cria diretorio temporario
     */
    if ( !is_dir($sDiretorioTemporario) && !mkdir($sDiretorioTemporario, 0777, true) ) {
      throw new Exception("Não foi possivel criar diretório temporario: $sDiretorioTemporario");
    }

    foreach ( $aRetornoComandoAnnotate as $sLinha ) {

      $aLinha = explode(':', $sLinha);
      $aLinhaDados = explode(" ", $aLinha[0]);
      $sLinhaPrograma = $aLinha[1];
      $sLinhaDados = '';

      foreach ( $aLinhaDados as $iIndiceLinhaDados => $sDadosLinhaDados ) {

        if ( empty($sDadosLinhaDados) ) {
          continue;
        } 

        $sDadosLinhaDados = str_replace(array('(', ')'), '', $sDadosLinhaDados);
        $sLinhaDados .= $sDadosLinhaDados . ' '; 
      }

      $iLinhaDados = strlen($sLinhaDados);

      if ( $iLinhaDados > $iColunasDados ) {
        $iColunasDados = $iLinhaDados;
      }
      
      // $sConteudoArquivoPrograma .= $sLinhaPrograma . PHP_EOL;
      $sConteudoArquivoDados    .= trim($sLinhaDados) . PHP_EOL;
    }

    // file_put_contents($sArquivoPrograma, $sConteudoArquivoPrograma);
    file_put_contents($sArquivoDados, $sConteudoArquivoDados);

    $this->binario($sArquivo, $sArquivoDados, $iColunasDados);
  }

  /**
   * Executa binario 
   *
   * @param string $sArquivoPrograma
   * @param string $sArquivoDados
   * @access private
   * @return boolean
   */
  private function binario($sArquivoPrograma, $sArquivoDados, $iColunasDados) {

    $sComandosVim  = "set scrollbind | vsp $sArquivoDados";
    $sComandosVim .= " | vertical resize $iColunasDados | set scrollbind | set nonu";
    $aParametros = array($sArquivoPrograma, '-c', $sComandosVim);
    $sBinario = '/usr/bin/vim';
    return pcntl_exec($sBinario, $aParametros);
  }

}
