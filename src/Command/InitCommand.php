<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;
use \Cvsgit\Library\FileDataBase;

class InitCommand extends Command {

  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('init');
    $this->setDescription('Inicializa diretório atual');
    $this->setHelp('Inicializa diretório atual');
    $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Forçar inicialização do diretório');
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

    if ( !file_exists('CVS/Repository') ) {
      throw new Exception('Diretório atual não é um repositorio CVS.');
    }

    $sDiretorioAtual = getcwd();
    $sRepositorio    = trim(file_get_contents('CVS/Repository'));
    $sArquivoBanco   = CONFIG_DIR . $sRepositorio .  '.db';
    $sArquivoConfig  = CONFIG_DIR . $sRepositorio . '_config.json';

    /**
     * Força inicialização do projeto 
     */
    if ( $oInput->getOption('force') ) {

      /**
       * Remove arquivo do banco de dados 
       */
      if ( file_exists($sArquivoBanco) ) {
        if ( !unlink($sArquivoBanco) ) {
          throw new Exception("Não foi possivel remover banco de dados: " . $sArquivoBanco);
        }
      }

      /**
       * Remove arquivo de configuração 
       */
      if ( file_exists($sArquivoConfig) ) {
        if ( !unlink($sArquivoConfig) ) {
          throw new Exception("Não foi possivel remover configurações: " . $sArquivoConfig);
        }
      }
    }

    /**
     * Arquivo já existe, verifica se projeto já foi inicializado 
     */
    if ( file_exists($sArquivoBanco) ) {

      $oDataBase = new FileDataBase($sArquivoBanco);
      $aProjetos = $oDataBase->selectAll("select name, path from project where name = '$sRepositorio' or path = '$sDiretorioAtual'");

      /**
       * Diretorio atual ja inicializado 
       */
      foreach( $aProjetos as $oProjeto ) {

        if ( $oProjeto->name == $sRepositorio || $oProjeto->path == $sDiretorioAtual ) {

          $oOutput->writeln(sprintf('<info>"%s" já inicializado</info>', $sRepositorio));
          return true;
        }
      }   

    }

    /**
     * Diretório onde aplicação guarda arquivos de configuracão e banco de dados 
     */
    if ( !is_dir(CONFIG_DIR) && !mkdir(CONFIG_DIR) ) {
      throw new Exception('Não foi possivel criar diretório: ' . CONFIG_DIR);
    }

    /**
     * Cria copia do arquivo do banco
     */
    $lArquivoConfiguracoes = copy(APPLICATION_DIR . 'src/install/config.json', $sArquivoConfig);

    if ( !$lArquivoConfiguracoes ) {
      throw new Exception("Não foi possivel criar arquivo de configurações no diretório: " . $sArquivoConfig);
    }

    /**
     * Cria copia do arquivo de configuracao
     */
    $lArquivoBancoDados = copy(APPLICATION_DIR . 'src/install/cvsgit.db', $sArquivoBanco);

    if ( !$lArquivoBancoDados ) {
      throw new Exception("Não foi possivel criar arquivo do banco de dados no diretório: " . CONFIG_DIR );
    }

    $oDataBase = new FileDataBase($sArquivoBanco);
    $oDataBase->begin();

    $oDataBase->insert('project', array(
      'name' => $sRepositorio,
      'path' => $sDiretorioAtual, 
      'date' => date('Y-m-d H:i:s')
    ));

    $oOutput->writeln(sprintf('<info>"%s" inicializado</info>', $sRepositorio));
    $oDataBase->commit();
  }

}
