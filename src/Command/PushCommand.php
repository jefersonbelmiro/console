<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Exception;

use \Cvsgit\Model\Arquivo;
use \Cvsgit\Model\PushModel;
use \Cvsgit\Model\ArquivoModel;
use \Cvsgit\Library\Table;
use \Cvsgit\Library\Encode;

class PushCommand extends Command {

  private $oInput;
  private $oOutput;
  private $oConfig;
  private $aArquivos;
  private $sTituloPush;
  private $aMensagemErro;

  /**
   * Configura comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('push');
    $this->setDescription('Envia modificações para repositório');
    $this->setHelp('Envia modificações para repositório');
    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para enviar para o repositorio');
    $this->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Mensagem de log do envio' );
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

    $this->oInput  = $oInput;
    $this->oOutput = $oOutput;
    $this->oConfig = $this->getApplication()->getConfig();

    $this->processarParametros();
    $this->validar();
    $this->exibirComandos();
    $this->enviar();
  }

  private function processarParametros() {

    $this->aArquivos = array();
    $this->sTituloPush = $this->oInput->getOption('message');

    $oArquivoModel = new ArquivoModel();
    $aArquivosAdicionados = $oArquivoModel->getAdicionados();
    $aArquivosParaCommit = $this->oInput->getArgument('arquivos');

    foreach($aArquivosParaCommit as $sArquivo ) {

      $sArquivo = realpath($sArquivo);

      if ( empty($aArquivosAdicionados[ $sArquivo ]) ) {
        throw new Exception("Arquivo não encontrado na lista para commit: " . $this->getApplication()->clearPath($sArquivo));
      }

      $this->aArquivos[ $sArquivo ] = $aArquivosAdicionados[ $sArquivo ];
    }

    if ( empty($aArquivosParaCommit) ) {
      $this->aArquivos = $aArquivosAdicionados;
    }
  }

  private function validar() {

    if ( empty($this->aArquivos) ) {
      throw new Exception("Nenhum arquivo para comitar");
    }

    $this->aMensagemErro = array();
    $oTabela = new Table();
    $oTabela->setHeaders(array('Arquivo', 'Tag Arquivo', 'Tag Mensagem', 'Mensagem', 'Tipo'));
    $aLinhas = array();
    $aComandos = array();
    $iErros  = 0;

    /**
     * Valida configuracoes do commit
     */
    foreach ( $this->aArquivos as $oCommit ) {

        $sArquivo  = $this->getApplication()->clearPath($oCommit->getArquivo());

        $iTagMensagem = $oCommit->getTagMensagem();
        $iTagArquivo = $oCommit->getTagArquivo();

        $sMensagem = $oCommit->getMensagem();
        $sTipo     = $oCommit->getTipo();
        $sErro     = '<error>[x]</error>';

        /**
         * Valida arquivo
         * @todo, se arquivo nao existir usar cvs status para saber se deve deixar arquivo
         */
        if ( !file_exists($oCommit->getArquivo()) ) {

          $sArquivo = $sErro . ' ' . $sArquivo;
          $this->aMensagemErro[$sArquivo][] = "Arquivo não existe";
          $iErros++;
        }

        /**
         * Valida mensagem
         */
        if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR ) {

          if ( empty($sMensagem) ) {

            $this->aMensagemErro[$sArquivo][] = "Mensagem não informada";
            $sMensagem = $sErro;
            $iErros++;
          }
        }

        /**
         * Tipo de commit não informado
         */
        if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR ) {

          if ( empty($sTipo) ) {

            $this->aMensagemErro[$sArquivo][] = "Tipo de commit não informado";
            $sTipo = $sErro;
            $iErros++;
          }
        }

        if ($oCommit->getComando() === Arquivo::COMANDO_ADICIONAR_TAG || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR) {

          $aProjetosTagArquivo = $this->getApplication()->getConfig('tag')->tag_arquivo;
          $aProjetosTagMensagem = $this->getApplication()->getConfig('tag')->tag_mensagem;
          $sProjeto = $this->getApplication()->getModel()->getProjeto()->name;

          if (in_array($sProjeto, $aProjetosTagArquivo)) {
            if (empty($iTagArquivo)) {

              $this->aMensagemErro[$sArquivo][] = "Tag do arquivo não informada";
              $iTagArquivo = $sErro;
              $iErros++;
            }
          }

          if ($oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR && in_array($sProjeto, $aProjetosTagMensagem)) {
            if (empty($iTagMensagem)) {

              $this->aMensagemErro[$sArquivo][] = "Tag da mensagem não informada";
              $iTagMensagem = $sErro;
              $iErros++;
            }
          }
        }

        $this->validarConteudoArquivo($oCommit->getArquivo());

        $oTabela->addRow(array($sArquivo, $iTagArquivo, $iTagMensagem, $sMensagem, $sTipo));
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

      $this->oOutput->writeln($oTabela->render());
      exit(1);
    }
  }

  private function exibirComandos() {

    $this->oOutput->writeln('');

    /**
     * Exibe comandos que serão executados
     */
    foreach($this->aArquivos as $sArquivo => $oCommit) {

      $sMensagemAviso  = "";
      $sMensagemCommit = $oCommit->getMensagem();
      $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->getArquivo());

      if ( !empty($this->aMensagemErro[$sArquivoCommit]) ) {
        $sMensagemAviso = '"' . implode(" | " , $this->aMensagemErro[$sArquivoCommit]). '"';
      }

      $this->oOutput->writeln("-- <comment>$sArquivoCommit:</comment> $sMensagemAviso");

      /**
       * Commitar e tagear
       */
      if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR ) {

        if (!$this->checkFileExists($oCommit)) {

          foreach($this->addNewDirectories($sArquivoCommit) as $sCommando) {
            $this->oOutput->writeln("   " . $sCommando);
          }
        }

        $this->oOutput->writeln('   ' . $this->commitArquivo($oCommit));
      }

      /**
       * tagear
       */
      if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_ADICIONAR_TAG || $oCommit->getComando() === Arquivo::COMANDO_REMOVER_TAG ) {
        $this->oOutput->writeln('   ' . $this->tagArquivo($oCommit));
      }

      $this->oOutput->writeln('');
    }

  }

  private function enviar() {

    $helper = $this->getHelper('question');
    $question = new Question('Commitar?: (s/N): ');

    $sConfirma = $helper->ask($this->oInput, $this->oOutput, $question);

    if (strtoupper($sConfirma) != 'S') {
        return 0;
    }

    $this->oOutput->writeln('');
    $aArquivosCommitados = array();

    foreach($this->aArquivos as $oCommit) {

        $sArquivoCommit = $this->getApplication()->clearPath($oCommit->getArquivo());

        $aComandosExecutados = array();

        if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_COMMITAR ) {

          if (!$this->checkFileExists($oCommit)) {

            foreach($this->addNewDirectories($sArquivoCommit) as $sCommando) {

              $oComandoAdd = $this->getApplication()->execute($sCommando);
              $aRetornoComandoAdd = $oComandoAdd->output;
              $iStatusComandoAdd = $oComandoAdd->code;

              if ( $iStatusComandoAdd > 0 ) {

                $this->getApplication()->displayError("Erro ao adicionar arquivo: {$sArquivoCommit}", $this->oOutput);
                continue 2;
              }

            }

            $aComandosExecutados[] = 'Adicionado';
          }

          $oComandoCommit = $this->getApplication()->execute($this->commitArquivo($oCommit));
          $aRetornoComandoCommit = $oComandoCommit->output;
          $iStatusComandoCommit = $oComandoCommit->code;

          if ( $iStatusComandoCommit > 0 ) {

            $this->getApplication()->displayError("Erro ao commitar arquivo: {$sArquivoCommit}", $this->oOutput);
            continue;
          }

          $aComandosExecutados[] = 'Commitado';
        }

        if ( $oCommit->getComando() === Arquivo::COMANDO_COMMITAR_TAGGEAR || $oCommit->getComando() === Arquivo::COMANDO_ADICIONAR_TAG || $oCommit->getComando() === Arquivo::COMANDO_REMOVER_TAG ) {

          $oComandoTag = $this->getApplication()->execute($this->tagArquivo($oCommit));
          $aRetornoComandoTag = $oComandoTag->output;
          $iStatusComandoTag = $oComandoTag->code;

          if ( $iStatusComandoTag > 0 ) {

            $this->getApplication()->displayError("Erro ao por tag no arquivo: {$sArquivoCommit}", $this->oOutput);
            continue;
          }

          $aComandosExecutados[] = 'Taggeado';
        }

        $this->oOutput->writeln("<info> - Arquivo " . implode(', ', $aComandosExecutados). ": $sArquivoCommit</info>");
        $aArquivosCommitados[] = $oCommit;
    }

    /**
     * - Salva arquivos commitados
     * - Remove arquivos já commitados
     */
    if ( !empty($aArquivosCommitados) ) {

      $oPushModel = new PushModel();
      $oPushModel->setTitulo($this->sTituloPush);
      $oPushModel->adicionar($aArquivosCommitados);
      $oPushModel->salvar();
    }

    $this->oOutput->writeln('');
  }

  private function tagArquivo($oCommit) {

    $iTag = $oCommit->getTagArquivo();

    if ( empty($iTag) ) {

      $iTagRelease = $this->oConfig->get('tag')->release;

      if ( !empty($iTagRelease) ) {
        $iTag = $iTagRelease;
      }
    }

    if ( empty($iTag) ) {
      return;
    }

    $oPrefixosTag = $this->getApplication()->getConfig('tag')->prefixo;
    $sProjeto = $this->getApplication()->getModel()->getProjeto()->name;

    /**
     * Prefixo das tag do prejeto
     */
    if (isset($oPrefixosTag->$sProjeto)) {
      $iTag = $oPrefixosTag->$sProjeto . $iTag;
    }

    $sArquivoCommit = $this->getApplication()->clearPath($oCommit->getArquivo());

    /**
     * Forçar se tag existir
     */
    $sComandoTag = '-F';

    if ( $oCommit->getComando() === Arquivo::COMANDO_REMOVER_TAG ) {
      $sComandoTag = '-d';
    }

    return Encode::toUTF8("cvs tag {$sComandoTag} {$iTag} " . escapeshellarg($sArquivoCommit));
  }

  private function addNewDirectories($path) {

    $data = array();
    $path = rtrim($path, '/');

    if ($path == '.') {
      return $data;
    }

    if (file_exists($path)) {
      $data[] = Encode::toUTF8("cvs add " . escapeshellarg($path));
    }
    else if (!file_exists($path . '/CVS/Repository')) {
      $data[] = Encode::toUTF8("cvs add " . escapeshellarg($path));
    }

    return array_merge( $this->addNewDirectories(dirname($path)), $data);
  }

  private function checkFileExists($oCommit) {

    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->getArquivo());
    $oCommand = $this->getApplication()->execute(Encode::toUTF8("cvs status " . escapeshellarg($sArquivoCommit)));

    // diretorio nao adicionado
    if ($oCommand->code === 1) {
      return false;
    }

    // erro
    if ($oCommand->code > 1 || empty($oCommand->output)) {

      $this->getApplication()->displayError("Erro ao validar arquivo: {$sArquivoCommit}", $this->oOutput);
      return false;
    }

    // arquivo nao adicionado
    if (strpos(strtolower($oCommand->output[1]), 'unknown') !== false) {
      return false;
    }

    // arquivo adicionado
    return true;
  }

  private function addArquivo($oCommit) {

    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->getArquivo());
    return Encode::toUTF8("cvs add " . escapeshellarg($sArquivoCommit));
  }

  private function commitArquivo($oCommit) {

    $sTagMensagem = $oCommit->getTagMensagem();
    $iTagArquivo = $oCommit->getTagArquivo();

    if( !empty($sTagMensagem) ){
      $sTagMensagem = " #" . $sTagMensagem;
    }

    $sTipoAbreviado = $oCommit->getTipo();
    $sTipoCompleto  = null;

    switch ($sTipoAbreviado) {

      case 'ADD' :
        $sTipoCompleto = 'added';
      break;

      /**
       * Commit para modificacoes do layout ou documentacao
       */
      case 'STYLE' :
        $sTipoCompleto = 'style';
      break;

      /**
       * Commit para correcao de erros
       */
      case 'FIX' :
        $sTipoCompleto = 'fixed';
      break;

      /**
       * Commit para melhorias
       */
      case 'ENH' :
        $sTipoCompleto = 'enhanced';
      break;

      default :
        throw new Exception("Tipo não abreviado de commit não encontrado para tipo: $sTipoAbreviado");
      break;
    }

    $sMensagemCommit = "$sTipoAbreviado: " . $oCommit->getMensagem() . " ({$sTipoCompleto}$sTagMensagem)";
    $sMensagemCommit = str_replace("'", '"', $sMensagemCommit);
    $sArquivoCommit  = $this->getApplication()->clearPath($oCommit->getArquivo());
    return Encode::toUTF8("cvs commit -m '$sMensagemCommit' " . escapeshellarg($sArquivoCommit));
  }

  private function validarConteudoArquivo($sArquivo) {

  }

}
