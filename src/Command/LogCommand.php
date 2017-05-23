<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;
use \Cvsgit\Library\Encode;
use \Cvsgit\Library\Table;
use \Cvsgit\Library\Shell;

class LogCommand extends Command {

  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('log');
    $this->setDescription('Exibe log dos commits de um arquivo');
    $this->setHelp('Exibe log dos commits de um arquivo');
    $this->addArgument('arquivo', InputArgument::REQUIRED, 'Arquivo para exibir log dos commits');
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

    $sArquivo = $oInput->getArgument('arquivo');
    $sArquivo = $this->getApplication()->clearPath($sArquivo);

    /**
     * Lista somenta as tags
     */
    $oComando = $this->getApplication()->execute('cvs log -h ' . escapeshellarg($sArquivo));
    $aRetornoComandoTags = $oComando->output;
    $iStatusComandoTags = $oComando->code;

    if ( $iStatusComandoTags > 0 ) {
      throw new Exception('Erro ao execurar cvs log -h ' . escapeshellarg($sArquivo) . PHP_EOL . $this->getApplication()->getLastError(), $iStatusComandoTags);
    }

    $aTagsPorVersao = array();
    $iVersaoAtual = 0;
    $lInicioListaTags = false;

    foreach( $aRetornoComandoTags as $iIndiceTags => $sLinhaRetornoTag ) {

      if ( strpos($sLinhaRetornoTag, 'head:') !== false ) {

        $iVersaoAtual = trim(str_replace('head:', '', $sLinhaRetornoTag));
        continue;
      }

      if ( strpos($sLinhaRetornoTag, 'symbolic names:') !== false ) {

        $lInicioListaTags = true;
        continue;
      }

      if ( $lInicioListaTags ) {

        if ( strpos($sLinhaRetornoTag, 'keyword substitution') !== false ) {
          break;
        }

        if ( strpos($sLinhaRetornoTag, 'total revisions') !== false ) {
          break;
        }

        $aLinhaTag = explode(':', $sLinhaRetornoTag);
        $iVersao   = trim($aLinhaTag[1]);
        $sTag      = trim($aLinhaTag[0]);

        $aTagsPorVersao[$iVersao][] = $sTag;
      }
    }

    /**
     * Lista informacoes do commit, sem as tags
     */
    $oComando = $this->getApplication()->execute('cvs log -N ' . escapeshellarg($sArquivo));
    $aRetornoComandoInformacoes = $oComando->output;
    $iStatusComandoInformacoes = $oComando->code;

    if ( $iStatusComandoInformacoes > 0 ) {
      throw new Exception(
        'Erro ao execurar cvs log -N ' . escapeshellarg($sArquivo) . PHP_EOL . $this->getApplication()->getLastError(), 
        $iStatusComandoInformacoes
      );
    }

    $aLog = array();
    $iLinhaInformacaoCommit = 0;

    $oTabela = new Table();
    $oTabela->setHeaders(array('Autor', 'Data', 'Versao', 'Tag', 'Mensagem'));
    $aLinhas = array();

    $iVersao   = null;
    $sAutor    = null;
    $sData     = null;
    $sMensagem = null;

    foreach ( $aRetornoComandoInformacoes as $iIndice => $sLinhaRetorno ) {

      if ( strpos($sLinhaRetorno, '------') !== false ) {
        continue;
      } 

      if ( $iLinhaInformacaoCommit == 0 && $iIndice > 11 ) {

        $sTagsPorVersao = null;

        if ( !empty($aTagsPorVersao[$iVersao]) ) {
          $sTagsPorVersao = implode(', ', $aTagsPorVersao[$iVersao]);
        }

        $oTabela->addRow(array($sAutor, $sData, $iVersao, $sTagsPorVersao, Encode::toUTF8($sMensagem)));
        $iVersao   = '';
        $sAutor    = '';
        $sData     = '';
        $sMensagem = '';
      }

      if ( $iLinhaInformacaoCommit > 0 ) {
        $iLinhaInformacaoCommit--;
      } 

      /**
       * Versao
       */
      if ( strpos($sLinhaRetorno, 'revision') !== false && strpos($sLinhaRetorno, 'revision') === 0 ) {
        $iLinhaInformacaoCommit = 2;
      } 

      /**
       * Versao
       */
      if ( $iLinhaInformacaoCommit == 2 ) {

        $iVersao = trim(str_replace('revision', '', $sLinhaRetorno));
        continue;
      }

      /**
       * Data e autor 
       */
      if ( $iLinhaInformacaoCommit == 1 ) {

        $sLinhaRetorno = strtr($sLinhaRetorno, array('date:' => '', 'author:' => ''));
        $aLinhaInformacoesCommit = explode(';', $sLinhaRetorno);
        $sLinhaData = array_shift($aLinhaInformacoesCommit);
        $aLinhaData = explode(' ', $sLinhaData);
        $sData .= implode('/', array_reverse(explode('-', $aLinhaData[1])));

        $sAutor = trim(array_shift($aLinhaInformacoesCommit));
        continue;
      } 

      /**
       * Mensagem 
       */
      if ( $iLinhaInformacaoCommit == 0 ) {
        $sMensagem = $sLinhaRetorno;
      }
    }

    $sOutput = Encode::toUTF8($oTabela->render());

    $iColunas  = array_sum($oTabela->getWidths()); 
    $iColunas += count($oTabela->getWidths()) * 2;
    $iColunas += count($oTabela->getWidths()) - 1 ;

    if ( $iColunas > Shell::columns() ) {

      $this->getApplication()->less($sOutput);
      return;
    }

    $oOutput->writeln($sOutput);
  }

}
