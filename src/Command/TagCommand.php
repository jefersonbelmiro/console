<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;
use \Cvsgit\Model\ArquivoModel;
use \Cvsgit\Model\Arquivo;

class TagCommand extends Command {

  /**
   * Configura comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('tag');
    $this->setDescription('Altera tag dos arquivos já adicionados para commit(comando cvsgit add)');
    $this->setHelp('Altera tag dos arquivos já adicionados para commit(comando cvsgit add)');

    $this->addArgument('tag', InputArgument::REQUIRED, 'Tag do arquivo');
    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para alterar tags');

    $this->addOption('added',  'a', InputOption::VALUE_NONE, 'Adicionar tag');
    $this->addOption('delete', 'd', InputOption::VALUE_NONE, 'Deletar tag');
  }

  /**
   * Executa comando
   *
   * @param Object $oInput
   * @param Object $oOutput
   * @access public
   * @return void
   */
  public function execute($oInput, $oOutput) {

    $oArquivoModel = new ArquivoModel();
    $aArquivosAdicionados = $oArquivoModel->getAdicionados();

    $aArquivos = array();
    $aOpcoes = array('added', 'delete');
    $sComando = null;
    $aArquivosParametro = $oInput->getArgument('arquivos');

    /**
     * Verifica arquivos passados por parametro 
     */
    foreach($aArquivosParametro as $sArquivo ) {

      if ( !file_exists($sArquivo) ) {

        $oOutput->writeln("<error>Arquivo não encontradao: $sArquivo</error>");
        continue;
      }

      $sArquivo = realpath($sArquivo);

      /**
       * Arquivo já adicionado a lista, pega ele 
       */
      if ( !empty($aArquivosAdicionados[ $sArquivo ]) ) {
        
        $aArquivos[$sArquivo] = $aArquivosAdicionados[ $sArquivo ]; 
        continue;
      }

      $oArquivo = new Arquivo();
      $oArquivo->setArquivo($sArquivo);

      /**
       * Arquivos não adicionados ainda a lista 
       */
      $aArquivos[$sArquivo] = $oArquivo; 
    }

    /**
     * Nenhum arquivo passado por parametro, poe tag em todos os arquivos ja adicionados 
     */
    if ( empty($aArquivos) ) {
      $aArquivos = $aArquivosAdicionados;
    }

    foreach ( $oInput->getOptions() as $sArgumento => $sValorArgumento ) {

      if ( empty($sValorArgumento) || !in_array($sArgumento, $aOpcoes) ) {
        continue;
      }

      if ( !empty($sComando) ) {
        throw new Exception("Mais de uma comando usado(added, delete)");
      }

      if ( empty($sComando) ) {
        $sComando = $sArgumento;
      }
    }

    if ( empty($sComando) ) {
      $sComando = 'added';
    }

    /**
     * Tag do arquivo, sem prefixo T 
     */
    $iTag = ltrim(strtoupper($oInput->getArgument('tag')), 'T');

    $aArquivosTaggeados = $oArquivoModel->taggear($aArquivos, $iTag, $sComando);

    if ( !empty($aArquivosTaggeados) ) {

      foreach ( $aArquivosTaggeados as $sArquivo ) {
        $oOutput->writeln("<info>Arquivo com tag atualizada: " . $this->getApplication()->clearPath($sArquivo) . "</info>");
      }
    }
  }

}
