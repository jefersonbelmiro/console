<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;
use \Cvsgit\Library\Config;
use \Cvsgit\Library\String;

class ConfigCommand extends Command {

  /**
   * Arquivo do arquivo de configuracoes
   * 
   * @var string
   * @access private
   */
  private $sArquivoConfiguracoes; 

  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('config');
    $this->setDescription('Exibe configurações da aplicação');
    $this->setHelp('Exibe configurações da aplicação');
    $this->addOption('edit', 'e', InputOption::VALUE_NONE, 'Editar configurações');
    $this->addOption('restart', 'r', InputOption::VALUE_NONE, 'Reiniciar configurações');
  }

  /**
   * Executa o comando
   *
   * @param Object $oInput
   * @param Object $oOutput
   * @access public
   * @return void
   */
  public function execute($oInput, $oOutput) {

    $this->sArquivoConfiguracoes = CONFIG_DIR . basename($this->getApplication()->getModel()->getProjeto()->name) . '_config.json';

    if ( $oInput->getOption('restart') ) {

      $this->criarArquivoConfiguracoes();

      $oOutput->writeln("<info>Configurações reiniciadas.</info>");
      return;
    }

    if ( !file_exists($this->sArquivoConfiguracoes) ) {
      $this->criarArquivoConfiguracoes();
    }

    /**
     * Editar usando editor 
     */
    if ( $oInput->getOption('edit') ) {

      $iStatus = $this->editarArquivoConfiguracoes();

      if ( $iStatus > 0 ) {
        throw new Exception('Não foi possivel editar configurações');
      }

      return $iStatus;
    }

    $oConfig = new Config($this->sArquivoConfiguracoes);

    $sOutput       = PHP_EOL;
    $aIgnore       = $oConfig->get('ignore');
    $iTagRelease   = $oConfig->get('tag')->release;
    $tagsSprint    = $oConfig->get('tag')->sprint;

    $sOutput .= "- <comment>Arquivo:</comment> " . PHP_EOL;
    $sOutput .= "  " .$this->sArquivoConfiguracoes . PHP_EOL;

    /**
     * Ignorar 
     */
    if ( !empty($aIgnore) ) {

      $sOutput .= PHP_EOL;
      $sOutput .= "- <comment>Ignorar:</comment>" . PHP_EOL;
      $sOutput .= '  ' . implode(PHP_EOL . '  ', $aIgnore) . PHP_EOL;
    }

    /**
     * Tags 
     */
    if ( !empty($iTagRelease) || !empty($tagsSprint) ) {

      $sOutput .= PHP_EOL;
      $sOutput .= "- <comment>Tags:</comment>" . PHP_EOL;

      if ( !empty($iTagRelease) ) {

        $sOutput .= PHP_EOL;
        $sOutput .= "  <comment>Release:</comment>" . PHP_EOL;
        $sOutput .= '  ' . $iTagRelease . PHP_EOL;
      }

      if ( !empty($tagsSprint) ) {

        if ( is_array($tagsSprint) ) {

          $sOutput .= PHP_EOL;
          $sOutput .= "  <comment>Sprint:</comment>" . PHP_EOL;
          $sOutput .= '  ' . implode(', ', $tagsSprint). PHP_EOL;
        }

        if ( is_object($tagsSprint) ) {

          $sOutput .= PHP_EOL;
          $sOutput .= "  <comment>Sprint:</comment>" . PHP_EOL;

          foreach ( $tagsSprint as $sTag => $sDescricao ) {

            $sOutput .= '  ' . $sTag;

            if ( !empty($sDescricao) ) {
              $sOutput .= ': ' . $sDescricao;
            }

            $sOutput .=  PHP_EOL; 
          }
        }
      }

    }

    $oOutput->writeln($sOutput);
  }

  private function editarArquivoConfiguracoes() {
    return $this->binario($this->sArquivoConfiguracoes);
  }

  private function criarArquivoConfiguracoes() {

    $sConteudoArquivo = file_get_contents(APPLICATION_DIR . 'src/install/config.json');

    $lCriarConfiguracoes = file_put_contents($this->sArquivoConfiguracoes, $sConteudoArquivo);

    if ( !$lCriarConfiguracoes ) {
      throw new Exception("Não foi possivel criar arquivo de configurações: $this->sArquivoConfiguracoes");
    }

    return $lCriarConfiguracoes;
  }

  private function binario($sArquivoConfiguracoes) {

    $aParametrosEditor = array();

    /**
     * Tenta pegar as configuracoes atuais
     * - caso json estiver incorreto usar mascara padrao(a que está no catch) 
     */
    try {
      $sMascaraBinario = $this->getApplication()->getConfig('mascaraBinarioEditorConfiguracoes');
    } catch(Exception $oErro) {
      $sMascaraBinario = "/usr/bin/vim [arquivo] -c 'set filetype=javascript'";
    }

    /**
     * Arquivo de json valido mas sem a propriedade mascaraBinarioEditorConfiguracoes 
     */
    if ( empty($sMascaraBinario) ) {
      throw new Exception("Mascara para binario do editor não encontrado, verifique arquivo de configuração");
    }

    $aParametrosMascara = String::tokenize($sMascaraBinario);
    $sBinario           = array_shift($aParametrosMascara);

    if ( empty($sBinario) ) {
      throw new Exception("Arquivo binário para editor não encontrado");
    }

    /**
     * Percorre os parametros e substitui [arquivo] pelo arquivo de configuracoes
     */
    foreach ($aParametrosMascara as $sParametro) {

      if ( $sParametro == '[arquivo]' ) {
        $sParametro = $sArquivoConfiguracoes;
      }
      
      $aParametrosEditor[] = $sParametro;
    }

    return pcntl_exec($sBinario, $aParametrosEditor);
  }

}
