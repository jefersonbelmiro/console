<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Exception, Stdclass;
use \Cvsgit\Library\Encode;
use \Cvsgit\Library\Table;
use \Cvsgit\Library\Shell;

class HistoryCommand extends Command {

  private $oOutput;
  private $oInput;
  private $oModel;
  private $oDataBase;

  /**
   * Configura o comando
   *
   * @access public
   * @return void
   */
  public function configure() {

    $this->setName('history');
    $this->setDescription('Exibe histórico do repositorio');
    $this->setHelp('Exibe histórico do repositorio');

    $this->addArgument('arquivos', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Arquivo(s) para exibir histórico');

    $this->addOption('tag',     't', InputOption::VALUE_REQUIRED, 'Tag do commite');
    $this->addOption('date',    'd', InputOption::VALUE_REQUIRED, 'Data dos commites');
    $this->addOption('user',    'u', InputOption::VALUE_REQUIRED, 'Usuário, autor do commit');
    $this->addOption('message', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Mensagem de log do commit');
    $this->addOption('import',  'i', InputOption::VALUE_NONE, 'Importar historioco de alterações do CVS');
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

    $lImportarHistorico = $oInput->getOption('import');

    $oParametros = new StdClass();
    $oParametros->aArquivos  = $oInput->getArgument('arquivos');
    $oParametros->iTag       = $oInput->getOption('tag');
    $oParametros->aMensagens = $oInput->getOption('message');
    $oParametros->sUsuario   = $oInput->getOption('user');
    $oParametros->sData      = $oInput->getOption('date');

    $this->oOutput   = $oOutput;
    $this->oInput    = $oInput;
    $this->oModel    = $this->getApplication()->getModel();
    $this->oDataBase = $this->oModel->getDataBase();

    if ( !empty($lImportarHistorico) ) {
      return $this->importarHistorico();
    }

    $aHistorico = $this->getHistorico($oParametros);

    if ( empty($aHistorico) ) {
      throw new Exception("Histórico não encontrado.");
    }

    $oTabela = new Table();
    $oTabela->setHeaders(array('Arquivo', 'Autor', 'Data', 'Hora', 'Versão', 'Tag', 'Mensagem'));

    foreach( $aHistorico as $oArquivo ) { 

      $sArquivo  = $this->getApplication()->clearPath($oArquivo->name);
      $sAutor    = $oArquivo->author;
      $sData     = date('d/m/Y', strtotime($oArquivo->date));
      $sHora     = date('H:i:s', strtotime($oArquivo->date));
      $sVersao   = $oArquivo->revision;
      $sTags     = implode(',', $oArquivo->tags);
      $sMensagem = Encode::toUTF8($oArquivo->message);

      $oTabela->addRow(array($sArquivo, $sAutor, $sData, $sHora, $sVersao, $sTags, $sMensagem));
    }

    $sOutput = $oTabela->render();
    $iColunas  = array_sum($oTabela->getWidths()); 
    $iColunas += count($oTabela->getWidths()) * 2;
    $iColunas += count($oTabela->getWidths()) - 1 ;

    if ( $iColunas > Shell::columns() ) {

      $this->getApplication()->less($sOutput);
      return;
    }

    $oOutput->writeln($sOutput);
  }

  /**
   * Retorna a data para importacao
   * - retorna null quando nao foi importado ainda
   * - retorna data, caso informado, do parametro --date
   *
   * @todo - separar logica do metodo, get que da update?
   * @access public
   * @return string
   */
  public function getDataImportacao() {

    $sParametroData = $this->oInput->getOption('date');

    if ( !empty($sParametroData) ) {
      return date('Y-m-d', strtotime(str_replace('/', '-', $sParametroData)));
    }

    $oDataBase = $this->oDataBase;
    $iProjeto  = $this->oModel->getProjeto()->id;

    $oDataUpdateHistorico = $oDataBase->select('select date from history where project_id = ' . $iProjeto);

    if ( empty($oDataUpdateHistorico) ) {

      $oDataBase->insert('history', array('date' => date('Y-m-d'), 'project_id' => $iProjeto));
      return null;
    }

    $oDataBase->update('history', array('date' => date('Y-m-d')), 'project_id = ' . $iProjeto);

    $oDataUpdateHistorico = $oDataBase->select('select date from history where project_id = ' . $iProjeto);
    return $oDataUpdateHistorico->date;
  }

  /**
   * Importa historicos do CVS para usar em consultas locais
   *
   * @access public
   * @return boolean
   */
  public function importarHistorico() {

    $oModel    = $this->oModel;
    $oDataBase = $this->oDataBase;
    $oDataBase->begin();

    $aLogRepositorio = $this->getLogRepository();

    $iTotalArquivos = count($aLogRepositorio);
    $iArquivosImportados = 0;

    foreach( $aLogRepositorio as $sArquivo => $oDadosArquivo ) {

      try { 

        $iArquivosImportados++;
        $sArquivoBusca = getcwd() . '/' . $sArquivo;
        $sArquivoBusca = strtr($sArquivo, array(
          ' ' => '\ ',
          '(' => '\(',
          ')' => '\)', 
          "'" => "\'",
        ));

        $this->oOutput->write(
          "\r" . str_repeat(' ', Shell::columns()) .
          "\r[$iArquivosImportados/$iTotalArquivos] Processando arquivo: $sArquivoBusca"
        );

        $aDadosHistoricoArquivoSalvo = $oDataBase->selectAll("
          SELECT id 
            FROM history_file 
           WHERE project_id = " . $oModel->getProjeto()->id . "
             AND name = '{$sArquivoBusca}' 
        ");

        if ( !empty($aDadosHistoricoArquivoSalvo) ) {

          foreach( $aDadosHistoricoArquivoSalvo as $oDadosHistoricoArquivoSalvo ) {

            $oDataBase->delete('history_file', 'id = ' . $oDadosHistoricoArquivoSalvo->id);
            $oDataBase->delete('history_file_tag', 'history_file_id = ' . $oDadosHistoricoArquivoSalvo->id);
          }
        }

        foreach ( $oDadosArquivo->aLog as $iVersao => $oDadosVersao ) {

          $data = str_replace('/', '-', $oDadosVersao->sData . ' ' . $oDadosVersao->sHora);
          $data = strtotime($data);
          $sData = date('Y-m-d H:s:i', $data);

          $sMensagem = str_replace("'", '"', $oDadosVersao->sMensagem);

          $iHistorico = $oDataBase->insert('history_file', array(
            'project_id' => $oModel->getProjeto()->id, 
            'name'       => $sArquivoBusca, 
            'revision'   => $oDadosVersao->iVersao, 
            'message'    => $sMensagem,
            'author'     => $oDadosVersao->sAutor,
            'date'       => $sData 
          ));

          foreach ( $oDadosVersao->aTags as $sTag ) {

            $oDataBase->insert('history_file_tag', array(
              'history_file_id' => $iHistorico,
              'tag' => $sTag 
            ));
          } 

        }

      } catch (\PDOException $oErro) {

        $this->oOutput->write(
          "\r" . str_repeat(' ', Shell::columns()) .
          "\r\n<error>  - Arquivo não processado: $sArquivo\n  {$oErro->getMessage()}</error>\n\n"
        );
      }

    } // endforeach - dados do log

    $oDataBase->commit();

    $this->oOutput->write(
      "\r" . str_repeat(' ', Shell::columns()) .
      "\r<info>Histórico de $iArquivosImportados arquivo(s) importado.</info>\n"
    );

    return true;
  }

  /**
   * Retorna o historico do repositorio atual
   *
   * @param StdClass $oParametros
   * @access public
   * @return array
   */
  public function getHistorico(StdClass $oParametros = null) {

    $aArquivosHistorico = array();
    $sWhere = null;

    /**
     * Busca commites que contenham arquivos enviados por parametro 
     */
    if ( !empty($oParametros->aArquivos) ) {

      $sWhere .= " and ( ";

      foreach ( $oParametros->aArquivos as $iIndice => $sArquivo ) {

        if ( $iIndice  > 0 ) {
          $sWhere .= " or ";
        }

        $sWhere .= " name like '%$sArquivo%' ";
      }

      $sWhere .= " ) ";
    }

    /**
     * Busca commites com tag 
     */
    if ( !empty($oParametros->iTag) ) {
      $sWhere .= " and tag like '%$oParametros->iTag%'";
    }
 
    /**
     * Busca commites por usuario
     */
    if ( !empty($oParametros->sUsuario) ) {
      $sWhere .= " and author like '%$oParametros->sUsuario%'";
    }

    /**
     * Busca commites por data 
     */
    if ( !empty($oParametros->sData) ) {

      $oParametros->sData = date('Y-m-d', strtotime(str_replace('/', '-', $oParametros->sData)));
      $sWhere .= " and date between '$oParametros->sData 00:00:00' and '$oParametros->sData 23:59:59' ";
    }

    /**
     * Busca commites contendo mensagem 
     */
    if ( !empty($oParametros->aMensagens) ) {

      $sWhere .= " and ( ";

      foreach ( $oParametros->aMensagens as $iIndice => $sMensagem ) {

        if ( $iIndice  > 0 ) {
          $sWhere .= " or ";
        }

        $sWhere .= " message like '%$sMensagem%' ";
      }

      $sWhere .= " ) ";
    }

    $oModel    = $this->oModel;
    $oProjeto  = $oModel->getProjeto();
    $oDataBase = $oModel->getDataBase();

    $sSqlHistorico = "
        SELECT history_file.id, name, revision, message, author, date
          FROM history_file
               LEFT JOIN history_file_tag on history_file_tag.history_file_id = history_file.id
         WHERE project_id = {$oProjeto->id} $sWhere
      ORDER BY name, date DESC 
    ";

    $aHistoricos = $oDataBase->selectAll($sSqlHistorico);

    foreach ( $aHistoricos as $oHistorico ) {

      $oArquivo = new StdClass();
      $oArquivo->name     = $oHistorico->name;
      $oArquivo->revision = $oHistorico->revision;
      $oArquivo->message  = $oHistorico->message; 
      $oArquivo->date     = $oHistorico->date; 
      $oArquivo->author   = $oHistorico->author; 
      $oArquivo->tags     = array(); 

      $aTagsPorVersao = $oDataBase->selectAll("
        SELECT tag FROM history_file_tag WHERE history_file_id = '{$oHistorico->id}'
      ");

      foreach ( $aTagsPorVersao as $oTag ) {
        $oArquivo->tags[] = $oTag->tag;
      }

      $aArquivosHistorico[ $oHistorico->id ] = $oArquivo;
    }

    return $aArquivosHistorico;
  }

  /**
   * Retorna de todos os arquivos do repositorio atual
   * - Retorna um array com as informacoes de cada arquivo
   *
   * @access public
   * @return array
   */
  public function getLogRepository() {

    $sDataBuscaHistorico = $this->getDataImportacao();

    if ( !empty($sDataBuscaHistorico) ) {
      $sDataBuscaHistorico = "-d'>=$sDataBuscaHistorico'";
    }

    /**
     * Comando cvs para buscar log de todos os arquivos 
     * - jogando erros para arquivo /tmp/cvsgit_last_error
     */
    $sComandoLog = 'cvs log -S ' . $sDataBuscaHistorico;

    /**
     * Lista somenta as tags
     */
    $oComando = $this->getApplication()->execute($sComandoLog);
    $aRetornoComando = $oComando->output;
    $iStatusComando = $oComando->code;

    if ( $iStatusComando > 0 ) {
      throw new Exception( 'Erro ao execurar: ' . $sComandoLog . PHP_EOL . $this->getApplication()->getLastError() );
    }

    $aLogArquivo       = array();
    $aTagsPorVersao    = array();
    $sArquivo          = null;
    $iVersao           = 0;
    $lLinhaVersaoAtual = false;
    $lLinhaTags        = false;
    $lLinhaVersao      = false;
    $lLinhaDataAutor   = false;
    $lLinhaMensagem    = false;

    /**
     * Percorre o retorno do comando e fas os parse das linhas de cada arquivo
     */
    foreach ( $aRetornoComando as $sLinhaLog ) {

      /**
       * Arquivo
       * - Pega nome do arquivo
       */
      if ( strpos($sLinhaLog, 'Working file:') !== false ) {

        $sArquivo = trim(str_replace('Working file:', '', $sLinhaLog));
        $aLogArquivo[ $sArquivo ] = new StdClass(); 
        $aLogArquivo[ $sArquivo ]->sArquivo = $sArquivo;

        $lLinhaVersaoAtual = true;
        continue;
      }

      /**
       * Linhas invalidas, nao achou arquivo ainda 
       */
      if ( empty($sArquivo) ) {
        continue;
      }

      /**
       * Versao atual
       * - Pega versao atual do fonte 
       */
      if ( $lLinhaVersaoAtual ) {

        if ( strpos($sLinhaLog, 'head:') !== false ) {

          $aLogArquivo[ $sArquivo ]->iVersaoAtual = trim(str_replace('head:', '', $sLinhaLog));
          $aTagsPorVersao = array();
        }
      }

      /**
       * Tags
       * - Inicio das tags 
       */
      if ( strpos($sLinhaLog, 'symbolic names:') !== false ) {

        $lLinhaTags = true;
        continue;
      }
      
      /**
       * Tags
       * - parse das linhas tags
       */
      if ( $lLinhaTags ) {

        /**
         * Fim do parse das tags 
         */
        if ( strpos($sLinhaLog, 'keyword substitution') !== false ) {

          $lLinhaTags = false;
          continue;
        }

        /**
         * Fim do parse das tags 
         */
        if ( strpos($sLinhaLog, 'total revisions') !== false ) {

          $lLinhaTags = false;
          continue;
        }

        $aLinhaTag  = explode(':', $sLinhaLog);
        $iVersaoTag = trim($aLinhaTag[1]);
        $sTag       = trim($aLinhaTag[0]);

        $aTagsPorVersao[$iVersaoTag][] = $sTag;
      }

      /**
       * Versao
       * Inicio do parse das versoes 
       */
      if ( strpos($sLinhaLog, 'revision') !== false && strpos($sLinhaLog, 'revision') === 0 ) {
        $lLinhaVersao = true;
      }

      /**
       * Log - Versao
       * Parse na linha para pegar vesao do commit
       */
      if ( $lLinhaVersao ) {

        $iVersao = trim(str_replace('revision', '', $sLinhaLog));
        $aTagsVersao = array();

        if ( !empty($aTagsPorVersao[$iVersao]) ) {
          $aTagsVersao = $aTagsPorVersao[$iVersao];
        }

        $oLogArquivo = new StdClass();
        $oLogArquivo->iVersao = $iVersao; 
        $oLogArquivo->aTags = $aTagsVersao; 

        $aLogArquivo[ $sArquivo ]->aLog[ $iVersao ] = $oLogArquivo; 

        $lLinhaVersao    = false;
        $lLinhaDataAutor = true;
        continue;
      }

      /**
       * Log - Data e autor
       * Parse na linha da data e autor do commit
       */
      if ( $lLinhaDataAutor ) {

        $sLinhaDataAutor = strtr($sLinhaLog, array('date:' => '', 'author:' => ''));
        $aLinhaInformacoes = explode(';', $sLinhaDataAutor);
        $sLinhaData = array_shift($aLinhaInformacoes);
        $aLinhaData = explode(' ', $sLinhaData);

        $sData  = implode('/', array_reverse(explode('-', $aLinhaData[1])));
        $sHora  = $aLinhaData[2];

        $sAutor = trim(array_shift($aLinhaInformacoes));

        $oLogArquivo = $aLogArquivo[ $sArquivo ]->aLog[ $iVersao ];
        $oLogArquivo->sData = $sData;
        $oLogArquivo->sHora = $sHora;
        $oLogArquivo->sAutor = $sAutor;

        $lLinhaDataAutor = false;
        $lLinhaMensagem  = true;

        continue;
      }

      /**
       * Log - Mensagem
       * Parse na linha para pegar mensagem de log do commit
       */
      if ( $lLinhaMensagem ) {
        
        $oLogArquivo = $aLogArquivo[ $sArquivo ]->aLog[ $iVersao ];
        $oLogArquivo->sMensagem = $sLinhaLog;

        $iVersao = 0;
        $aTagsVersao = array();
        $lLinhaMensagem = false;
      }

    } 

    return $aLogArquivo;
  }

}
