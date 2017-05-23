<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception;
use \Cvsgit\Model\ArquivoModel;
use \Cvsgit\Library\Table;
use \Cvsgit\Model\Arquivo;

/**
 * StatusCommand
 *
 * @uses Command
 * @package cvs
 * @version 1.0
 */
class StatusCommand extends Command {

  /**
   * Tipos de commit
   */
  public $aTiposCommit = array(

    /**
     * Tem arquivo no projeto mas nao no servidor, cvs add
     */
    '?' => 'Novo',

    /**
     * Tem alteracoes que nao tem no servidor
     */
    'M' => 'Modificado',

    /**
     * Conflito, e cvs tentou fazer merge
     * cvs altera arquivo colocando as diferencas
     */
    'C' => 'Conflito',

    /**
     * modificado no servidor
     * versao do servidor ? maior que a do projeto
     */
    'U' => 'Atualizado',

    /**
     * Igual U, diferenca que servidor manda um path
     */
    'P' => 'Atualizado',

    /**
     * Apos dar cvs add, arquivo pronto para ser comitado
     */
    'A' => 'Adicionado',

    /**
     * Apos remover arquivo do projeto, ira remover do servidor se for commitado
     */
    'R' => 'Arquivo marcado para remover',

    /**
     * Identifica um arquivo removido localmente
     */
    '-' => 'Removido local'

  );

  /**
   * Configura comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('status');
    $this->setDescription('Lista diferenças com o repositorio');
    $this->setHelp('Lista diferenças com o repositorio');

    $this->addOption('push',     'p', InputOption::VALUE_NONE, 'Arquivos prontos para commit');
    $this->addOption('table',    't', InputOption::VALUE_NONE, 'Exibe diferenças em tabela' );
    $this->addOption('new',      '', InputOption::VALUE_NONE, 'Arquivos criados, não existem no repositorio');
    $this->addOption('modified', 'm', InputOption::VALUE_NONE, 'Arquivos modificados');
    $this->addOption('conflict', 'c', InputOption::VALUE_NONE, 'Arquivos com conflito');
    $this->addOption('update',   'u', InputOption::VALUE_NONE, 'Arquivos para atualizar, versão do repositorio é maior que a local');
    $this->addOption('added',    'a', InputOption::VALUE_NONE, 'Arquivos adicionados pelo comando "cvs add" e ainda não commitados');
    $this->addOption('removed',  'r', InputOption::VALUE_NONE, 'Arquivos removidos pelo comando "cvs rm" e ainda não commitados');
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

    $lTabela      = false;
    $lCriados     = false;
    $lModificados = false;
    $lConflitos   = false;
    $lAtulizados  = false;
    $lAdicionados = false;
    $lRemovidos   = false;
    $lPush        = false;

    $lPesquisaCvs = true;

    $aArquivosParaCommit   = array();
    $aTabelaModificacoes   = array();
    $aModificacoes         = array();
    $aRetornoComandoUpdate = array();

    $aModificados  = array();
    $aCriados      = array();
    $aAtualizados  = array();
    $aConflitos    = array();
    $aAdicionados  = array();
    $aRemovidos    = array();
    $aRemovidosLocal = array();

    $sStatusOutput       = "";
    $sStatusOutputTabela = "";
    $sListaUpdate        = "";
    $sListaArquivos      = "";

    $iParametros = 0;

    foreach ( $oInput->getOptions() as $sArgumento => $sValorArgumento ) {

      if ( empty($sValorArgumento) ) {
        continue;
      }

      switch ( $sArgumento ) {

        /**
         * Exibe modificacoes em tabela
         */
        case 'table' :
          $lTabela = true;
          $iParametros++;
        break;

        /**
         * Criados
         */
        case 'new' :
          $lCriados = true;
          $iParametros++;
        break;

        /**
         * Modificados
         */
        case 'modified' :
          $lModificados = true;
          $iParametros++;
        break;

        /**
         * Conflitos
         */
        case 'conflict';
          $lConflitos = true;
          $iParametros++;
        break;

        case 'update';
          $lAtulizados = true;
          $iParametros++;
        break;

        case 'added';
          $lAdicionados = true;
          $iParametros++;
        break;

        case 'removed';
          $lRemovidos = true;
          $iParametros++;
        break;

        case 'push';
          $lPush = true;
          $iParametros++;
        break;

      }
    }

    /**
     * Passou somente parametro --push
     * - Nao pesquisa cvs(commando cvs -qn update)
     */
    if ( $iParametros == 1 && $lPush ) {
      $lPesquisaCvs = false;
    }

    /**
     * Passou parametros --push e --table
     * - Nao pesquisa cvs(commando cvs -qn update)
     */
    if ( $iParametros == 2 && $lPush && $lTabela ) {
      $lPesquisaCvs = false;
    }

    /**
     * - Nenhum parametro informado
     * - Passou somente parametro --table
     */
    if ( $iParametros == 0 || ( $lTabela && $iParametros == 1 ) ) {

      $lCriados     = true;
      $lModificados = true;
      $lConflitos   = true;
      $lAtulizados  = true;
      $lAdicionados = true;
      $lRemovidos   = true;
      $lPush        = true;
    }

    /**
     * Model do comando
     */
    $oArquivoModel = new ArquivoModel();

    /**
     * lista dos arquivos adicionados para commit
     */
    $aArquivos = $oArquivoModel->getAdicionados();

    foreach ($aArquivos as $oCommit) {
      $aArquivosParaCommit[] = $this->getApplication()->clearPath($oCommit->getArquivo());
    }

    /**
     * Pesquisa modificacoes no cvs apenas se:
     *  - nenhum parametro informado
     *  - não passou somente parametro push
     */
    if ( $lPesquisaCvs ) {

      $oComando = $this->getApplication()->execute('cvs -qn update -dR');
      $aRetornoComandoUpdate = $oComando->output;
      $iStatusComandoUpdate  = $oComando->code;

      /**
       * Verificação mair que 1 pois quando existem merge cvs retorna status 1
       * - e merge nao é erro, e sim aviso
       */
      if ( $iStatusComandoUpdate > 1 ) {
        throw new Exception(
          'Erro nº ' . $iStatusComandoUpdate. ' ao execurar cvs -qn update -dR:' . "\n" .
          $this->getApplication()->getLastError()
        );
      }
    }

    /**
     * Arquivos para ignorar
     */
    $aArquivosIgnorar = array();
    $aIgnorar         = $this->getApplication()->getConfig('ignore');

    /**
     * Verifica arquivos ignorados no .csvignore do projeto
     */
    if ( file_exists('.cvsignore') ) {

      $oArquivoCvsIgnore = new \SplFileObject('.cvsignore');

      foreach ($oArquivoCvsIgnore as $iNumeroLinha => $sLinha) {

        $sLinha = trim($sLinha);
        if (!empty($sLinha) && !in_array($sLinha, $aIgnorar)) {
          $aIgnorar[] = $sLinha;
        }
      }
    }

    foreach ($aIgnorar as $sIgnore) {

      $sOperador = "*";
      if (is_file($sIgnore)) {
        $sOperador = "";
      }

      $aFiles           = $this->getApplication()->glob($sOperador, GLOB_BRACE, $sIgnore, true);
      $aArquivosIgnorar = array_merge($aArquivosIgnorar , $aFiles);
    }

    /**
     * Parse no retorno do comando cvs update
     */
    foreach ($aRetornoComandoUpdate as $sLinhaUpdate) {

      $aLinha = explode(' ', $sLinhaUpdate);
      $oLinha = new \StdClass();
      $sTipo  = trim(array_shift($aLinha));

      /**
       * Linha não é um tipo de commit: U, ?, C...
       */
      if ( !in_array($sTipo, array_keys($this->aTiposCommit)) ) {
        continue;
      }

      $oLinha->sTipo    = $sTipo;
      $oLinha->sArquivo = trim(implode(' ',$aLinha));

      /**
       * Arquivo está na lista dos ignorados, pula
       */
      if ( !empty($aIgnorar) && in_array($oLinha->sArquivo, $aArquivosIgnorar) ) {
        continue;
      }

      /**
       * Array com todas as modificaos
       */
      $aModificacoes[ $sTipo ][] = $oLinha;

      /**
       * Lista com os erros do comando update
       */
      $aLinhasErros = explode("\n", $this->getApplication()->getLastError());

      /**
       * Arquivo removido localmente
       * Percorre as linhas de erro procurando o arquivo
       *
       * @todo - arquivo com ultima versao no cvs como removido nao aparece no update
       */
      foreach ( $aLinhasErros as $sLinhaErro ) {

        /**
         * Encontrou arquivo na linh atual
         */
        if ( strpos($sLinhaErro, "`{$oLinha->sArquivo}'") !== false ) {

          /**
           * Contei a string lost na linha atual do arquivo
           */
          if ( strpos($sLinhaErro, "lost") !== false ) {

            $sTipo = "-";
            break;
          }
        }

      }

      /**
       * Separa em arrays as modificacoes pelo tipo de commit
       */
      switch ( $sTipo ) {

        /**
         * Novo
         */
        case '?' :
          $aCriados[] = $oLinha;
        break;

        /**
         * Modificado
         */
        case 'M' :
          $aModificados[] = $oLinha;
        break;

        /**
         * Conflito
         */
        case 'C' :
          $aConflitos[] = $oLinha;
        break;

        /**
         * Atualizado
         */
        case 'U' :
        case 'P' :
          $aAtualizados[] = $oLinha;
        break;

        /**
         * Adicionado e nao commitado
         */
        case 'A' :
          $aAdicionados[] = $oLinha;
        break;

        /**
         * Removido e nao commitado
         */
        case 'R' :
          $aRemovidos[] = $oLinha;
        break;

        /**
         * Removido no projeto local
         */
        case '-' :
          $aRemovidosLocal[] = $oLinha;
        break;

      }

    }

    /**
     * Novos
     * - arquivos criados e nao adicionados para commit
     */
    if ( $lCriados ) {

      $sArquivosCriados = '';

      foreach ( $aCriados as $oArquivoCriado ) {

        if ( in_array($oArquivoCriado->sArquivo, $aArquivosParaCommit) ) {
          continue;
        }

        $sArquivosCriados          .= "\n " . $oArquivoCriado->sArquivo;
        $aTabelaModificacoes['?'][] = $oArquivoCriado->sArquivo;
      }

      if ( !empty($sArquivosCriados) ) {

        $sStatusOutput .= "\n- Arquivos criados: ";
        $sStatusOutput .= "\n <comment>$sArquivosCriados</comment>\n";
      }
    }

    /**
     * Modificados
     * - arquivos modificados e nao adicionados para commit
     */
    if ( $lModificados ) {

      $sArquivosModificados = '';

      foreach ( $aModificados as $oArquivoModificado ) {

        if ( in_array($oArquivoModificado->sArquivo, $aArquivosParaCommit) ) {
          continue;
        }

        $sArquivosModificados       .= "\n " . $oArquivoModificado->sArquivo;
        $aTabelaModificacoes['M'][] = $oArquivoModificado->sArquivo;
      }

      if ( !empty($sArquivosModificados) ) {

        $sStatusOutput .= "\n- Arquivos modificados: ";
        $sStatusOutput .= "\n <error>$sArquivosModificados</error>\n";
      }
    }

    /**
     * Conflitos
     * - arquivos com conflito
     */
    if ( $lConflitos ) {

      $sArquivosConflito = '';

      foreach ( $aConflitos as $oArquivoConflito ) {


        $sArquivosConflito         .= "\n " . $oArquivoConflito->sArquivo;
        $aTabelaModificacoes['C'][] = $oArquivoConflito->sArquivo;
      }

      if ( !empty($sArquivosConflito) ) {

        $sStatusOutput .= "\n- Arquivos com conflito: ";
        $sStatusOutput .= "\n <error>$sArquivosConflito</error>\n";
      }
    }

    /**
     * Atualizados
     * - arquivos atualizados no repository e nao local
     */
    if ( $lAtulizados ) {

      $sArquivosAtualizados = '';

      foreach ( $aAtualizados as $oArquivoAtualizado ) {

        if ( in_array($oArquivoAtualizado->sArquivo, $aArquivosParaCommit) ) {
          continue;
        }

        $sArquivosAtualizados      .= "\n " . $oArquivoAtualizado->sArquivo;
        $aTabelaModificacoes['U'][] = $oArquivoAtualizado->sArquivo;
      }

      if ( !empty($sArquivosAtualizados) ) {

        $sStatusOutput .= "\n- Arquivos Atualizados: ";
        $sStatusOutput .= "\n <info>$sArquivosAtualizados</info>\n";
      }
    }

    /**
     * Adicionados
     * - arquivos adicionados e ainda n?o commitados
     */
    if ( $lAdicionados ) {

      $sArquivosAdicionados = '';

      foreach ( $aAdicionados as $oArquivoAdicionado ) {

        if ( in_array($oArquivoAdicionado->sArquivo, $aArquivosParaCommit) ) {
          continue;
        }

        $sArquivosAdicionados      .= "\n " . $oArquivoAdicionado->sArquivo;
        $aTabelaModificacoes['A'][] = $oArquivoAdicionado->sArquivo;
      }

      if ( !empty($sArquivosAdicionados) ) {

        $sStatusOutput .= "\n- Arquivos adicionados: ";
        $sStatusOutput .= "\n  <info>$sArquivosAdicionados</info>\n";
      }
    }

    /**
     * Removidos
     * - arquivos removidos e ainda não commitados
     */
    if ( $lRemovidos ) {

      $sArquivosRemovidos = '';

      foreach ( $aRemovidos as $oArquivoRemovido ) {

        if ( in_array($oArquivoRemovido->sArquivo, $aArquivosParaCommit) ) {
          continue;
        }

        $sArquivosRemovidos        .= "\n " . $oArquivoRemovido->sArquivo;
        $aTabelaModificacoes['R'][] = $oArquivoRemovido->sArquivo;
      }

      if ( !empty($sArquivosRemovidos) ) {

        $sStatusOutput .= "\n- Arquivos marcados como removido: ";
        $sStatusOutput .= "\n <info>$sArquivosRemovidos</info>\n";
      }

      $sArquivosRemovidosLocal = '';
      foreach ($aRemovidosLocal as $oArquivoRemovidoLocal) {

        if ( in_array($oArquivoRemovidoLocal->sArquivo, $aArquivosParaCommit) ) {
          continue;
        }

        $sArquivosRemovidosLocal        .= "\n " . $oArquivoRemovidoLocal->sArquivo;
        $aTabelaModificacoes['-'][] = $oArquivoRemovidoLocal->sArquivo;
      }

      if ( !empty($sArquivosRemovidosLocal) ) {

        $sStatusOutput .= "\n- Arquivos removidos do projeto local: ";
        $sStatusOutput .= "\n <error>{$sArquivosRemovidosLocal}</error>\n";
      }
    }

    /**
     * Tabela
     * - Lista modificações em tableas
     */
    if ( $lTabela ) {

      $oTabela = new Table();
      $oTabela->setHeaders(array('Tipo', 'Arquivo'));

      foreach ($aTabelaModificacoes as $sTipo => $aArquivosModificacao) {

        $sTipoModificacao = "[$sTipo] " . strtr($sTipo, $this->aTiposCommit);

        foreach($aArquivosModificacao as $sArquivoModificacao) {
          $oTabela->addRow(array($sTipoModificacao, $sArquivoModificacao));
        }
      }

      if ( !empty($aTabelaModificacoes) ) {

        $sStatusOutputTabela .= "\nModificações nao tratadas: \n";
        $sStatusOutputTabela .= $oTabela->render();
      }

    }

    /**
     * Push
     * - arquivos para commit
     */
    if ( $lPush ) {

      /**
       * Tabela
       * - Lista arquivos prontos para commit em tabela
       */
      if ( $lTabela ) {

        $oTabelaCommit = new Table();
        $oTabelaCommit->setHeaders(array('Arquivo', 'Tag Mensagem', 'Tag Arquivo', 'Mensagem', 'Tipo'));

        foreach ($aArquivos as $oCommit) {

            $sTipo = $oCommit->getTipo();

            switch ( $oCommit->getComando() ) {

              case Arquivo::COMANDO_ADICIONAR_TAG :
                $sTipo = 'Adicionar tag';
              break;

              case Arquivo::COMANDO_REMOVER_TAG :
                $sTipo = 'Remover tag';
              break;
            }

            $oTabelaCommit->addRow(array(
              $this->getApplication()->clearPath($oCommit->getArquivo()), $oCommit->getTagMensagem(), $oCommit->getTagArquivo(), $oCommit->getMensagem(), $sTipo
            ));
        }

        if ( !empty($aArquivos) ) {

          $sStatusOutputTabela .= "\nArquivos prontos para commit: \n";
          $sStatusOutputTabela .= $oTabelaCommit->render();
        }
      }

      /**
       * Sem tabela
       * - Lista arquivos prontos para commit em linha
       */
      if ( !$lTabela) {

        foreach ($aArquivos as $oCommit) {
          $sListaArquivos .= "\n " . $this->getApplication()->clearPath($oCommit->getArquivo()) . " ";
        }

        if ( !empty($sListaArquivos) ) {

          $sStatusOutput .= "\n- Arquivos prontos para commit: ";
          $sStatusOutput .= "\n <info>$sListaArquivos</info>\n";
        }
      }

    }

    /**
     * Nenhuma modifiação encontrada
     */
    if ( empty($sStatusOutput) && empty($sStatusOutputTabela) ) {

      $oOutput->writeln('Nenhuma modificação encontrada');
      return 0;
    }

    if ( $lTabela ) {
      $sStatusOutput = $sStatusOutputTabela;
    }

    $sStatusOutput = ltrim($sStatusOutput, "\n");

    $oOutput->writeln($sStatusOutput);
  }

}
