<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use \Cvsgit\Model\ArquivoModel;
use \Cvsgit\Model\Arquivo;

class AddCommand extends Command {

  private $oArquivoModel;
  private $aArquivos;
  private $aArquivosAdicionar;
  private $oConfiguracaoCommit;
  private $oInput;
  private $oOutput;

  public function configure() {

    $this->setName('add');
    $this->setDescription('Adicinar arquivos para commitar');

    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para commit');

    $this->addOption('message',    'm', InputOption::VALUE_REQUIRED, 'Mensagem de log' );
    $this->addOption('tag',        't', InputOption::VALUE_REQUIRED, 'Tag' );
    $this->addOption('tag-commit', 'T', InputOption::VALUE_REQUIRED, 'Usa mesma tag nos comandos commit e tag' );

    $this->addOption('added',          'a', InputOption::VALUE_NONE, 'Tipo de commit: Adicionar arquivo' );
    $this->addOption('enhanced',       'e', InputOption::VALUE_NONE, 'Tipo de commit: Melhoria' );
    $this->addOption('fixed',          'f', InputOption::VALUE_NONE, 'Tipo de commit: Correção de bug' );
    $this->addOption('style',          's', InputOption::VALUE_NONE, 'Tipo de commit: Modificações estéticas no fonte' );
    $this->addOption('force',          'F', InputOption::VALUE_NONE, 'Força o commit do fonte, ignorando encode' );
    $this->addOption('force-syntax',   'S', InputOption::VALUE_NONE, 'Força o commit do fonte, sem verificar syntax' );

    $this->setHelp('Adiciona e configura arquivos para enviar ao repositório CVS.');
  }

  public function execute($oInput, $oOutput) {

    $this->oInput  = $oInput;
    $this->oOutput = $oOutput;

    $this->oArquivoModel = new ArquivoModel();

    $this->aArquivos = $this->oArquivoModel->getAdicionados();

    /**
     * Procura os arquivos para adicionar
     */
    foreach($this->oInput->getArgument('arquivos') as $sArquivo) {

      if (!file_exists($sArquivo)) {
        continue;
      }

      if (is_dir($sArquivo)) {
        continue;
      }

      // Valida o Encode quando existir no config
      if (!$this->validaEncodeArquivos($sArquivo) && !$this->oInput->getOption('force')) {
        return 1;
      }

      // Valida erros de sintaxe no arquivo
      if (!$this->validaSintaxeArquivos($sArquivo) && $this->oInput->getOption('force-syntax')) {
        return 1;
      }
    }

    $this->processarArquivos();
  }

  public function processarArquivos() {

    $aArquivos = array();
    $sMensagem = '<info>Arquivo %s: %s</info>';

    /**
     * Procura os arquivos para adicionar
     */
    foreach( $this->oInput->getArgument('arquivos') as $sArquivo ) {

      if ( !file_exists($sArquivo) ) {
        continue;
      }

      if ( is_dir($sArquivo) ) {
        continue;
      }

      $aArquivos[] = realpath($sArquivo);
    }

    if ( empty($aArquivos) ) {
      $aArquivos = array_keys($this->aArquivos);
    }

    foreach ( $aArquivos as $sArquivo ) {

      $lAdicionado = true;

      if ( !empty($this->aArquivos[$sArquivo]) ) {

        $lAdicionado = false;
        $oArquivo = $this->aArquivos[$sArquivo];

        if ( in_array($oArquivo->getComando(), array(Arquivo::COMANDO_ADICIONAR_TAG, Arquivo::COMANDO_REMOVER_TAG)) ) {

          $oArquivo = new Arquivo();
          $oArquivo->setArquivo($sArquivo);
        }

      } else {

        $oArquivo = new Arquivo();
        $oArquivo->setArquivo($sArquivo);
      }

      foreach ( $this->oInput->getOptions() as $sArgumento => $sValorArgumento ) {

        if ( empty($sValorArgumento) ) {
          continue;
        }

        switch ( $sArgumento ) {

          /**
           * Mensagem do commit
           */
          case 'message' :
            $oArquivo->setMensagem($this->oInput->getOption('message'));
          break;

          /**
           * Tag do commit
           */
          case 'tag' :
            $oArquivo->setTagMensagem($this->oInput->getOption('tag'));
          break;

          /**
           * Tag do commit
           */
          case 'tag-commit' :

            $iTag = $this->oInput->getOption('tag-commit');
            $oArquivo->setTagMensagem($iTag);
            $oArquivo->setTagArquivo($iTag);
            $oArquivo->setComando(Arquivo::COMANDO_COMMITAR_TAGGEAR);

          break;

          /**
           * Commit para adicionar fonte ou funcionalidade
           */
          case 'added' :
            $oArquivo->setTipo('ADD');
          break;

          /**
           * Commit para modificacoes do layout ou documentacao
           */
          case 'style' :
            $oArquivo->setTipo('STYLE');
          break;

          /**
           * Commit para correcao de erros
           */
          case 'fixed' :
            $oArquivo->setTipo('FIX');
          break;

          /**
           * Commit para melhorias
           */
          case 'enhanced' :
            $oArquivo->setTipo('ENH');
          break;

        }
      }

      $iTagArquivo = $oArquivo->getTagArquivo();
      $iTagRelease = $this->getApplication()->getConfig()->get('tag')->release;

      if ( empty($iTagArquivo) && !empty($iTagRelease) ) {
        $oArquivo->setTagArquivo($iTagRelease);
      }

      $this->aArquivos[$sArquivo] = $oArquivo;

      if ( $lAdicionado ) {
        $this->oOutput->writeln(sprintf($sMensagem, 'adicionado a lista', $this->getApplication()->clearPath($sArquivo)));
      } else {
        $this->oOutput->writeln(sprintf($sMensagem, 'atualizado', $this->getApplication()->clearPath($sArquivo)));
      }
    }

    if ( !empty($this->aArquivos) ) {
      $this->oArquivoModel->salvarAdicionados($this->aArquivos);
    }
  }

  /**
   * Valida encoding nos arquivos a comitar
   * @param  String $sArquivo
   * @return Boolean
   */
  public function validaEncodeArquivos($sArquivo) {

    $lEncodeInvalido           = false;
    $sArquivoAdicionado        = $this->getApplication()->clearPath($sArquivo);
    $sCharsetArquivo           = preg_replace("/.*charset=(.*)/i", "$1", exec('file -i '.$sArquivoAdicionado));
    $sCharsetArquivoAdicionado = str_replace("-", "", $sCharsetArquivo);
    $sMensagemArquivoInvalido  = '<error>Encode inválido: %s. %s</error>';

    if ((int)$this->getApplication()->getConfig('encodeArquivo')) {
      $aEncodeSuportados         = $this->getApplication()->getConfig('encodeArquivo');

      if ( !in_array($sCharsetArquivoAdicionado, $aEncodeSuportados) && (!(int)$this->oInput->getOption('force')) ) {
        $this->oOutput->writeln(sprintf($sMensagemArquivoInvalido, $sCharsetArquivo, $sArquivoAdicionado));
        return false;
      }
    }

    return true;
  }

  /**
   * Valida erros de sintaxe nos arquivos a comitar
   * @param  String $sArquivo
   * @return Boolean
   */
  public function validaSintaxeArquivos($sArquivo) {

    $sExtensaoArquivo          = preg_replace("/([^ ]*)(\.)([\d\w]*)$/m", "$3", $sArquivo);
    $sNomeArquivo              = preg_replace("/([^ ]*)(\.)([\d\w]*)$/m", "$1", $sArquivo);
    $sMensagemArquivoInvalido  = "<error>Erro de sintaxe no arquivo: %s</error>";

    if ((int)$this->getApplication()->getConfig('acceptSyntax')) {

      $aSintaxesSuportadas         = $this->getApplication()->getConfig('acceptSyntax');

      if ( in_array($sExtensaoArquivo, $aSintaxesSuportadas) && (!(int)$this->oInput->getOption('force-syntax')) ) {

        switch ($sExtensaoArquivo) {

          case "php":

              $sMensagemValidacaoSintaxe = preg_replace("/(.*)( ". preg_quote($sArquivo, '/') .")/m", "$1", exec("php -l ".$sArquivo));
              if(strpos(strtolower($sMensagemValidacaoSintaxe), "no syntax errors")) {
                $this->oOutput->writeln(sprintf($sMensagemArquivoInvalido, $sArquivo));
                return false;
              }
            break;
        }
      }
    }

    return true;
  }
}
