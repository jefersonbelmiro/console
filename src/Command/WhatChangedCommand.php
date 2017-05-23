<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use \Cvsgit\Model\ArquivoModel;
use \Cvsgit\Library\Table;

class WhatChangedCommand extends Command {

    private $sParametroData;

    /**
     * Configuracoes do commit
     *
     * @access public
     * @return void
     */
    public function configure() {

        $this->setName('whatchanged');
        $this->setDescription('what changed');
        $this->setHelp('what changed');
        $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos commitados');
        $this->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Data dos commites' );
        $this->addOption('tag', 't', InputOption::VALUE_REQUIRED, 'Tag existente nos commites');
        $this->addOption('message', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Mensagem do arquivo');
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

        $oParametros = new \StdClass();
        $oParametros->aArquivos  = $oInput->getArgument('arquivos');
        $oParametros->iTag       = ltrim(strtoupper($oInput->getOption('tag')), 'T');
        $oParametros->aMensagens = $oInput->getOption('message');
        $oParametros->sData      = $oInput->getOption('date');

        $oArquivoModel = new ArquivoModel();
        $aArquivosCommitados = $oArquivoModel->getCommitados($oParametros); 

        if ( empty($aArquivosCommitados) ) {
            throw new \Exception("Nenhum arquivo encontrado.");
        }

        $oOutput->writeln("");

        $oBuscaOutputFormatter = new OutputFormatterStyle('red', null, array());	
        $oOutput->getFormatter()->setStyle('busca', $oBuscaOutputFormatter);

        foreach ( $aArquivosCommitados as $oDadosCommit ) {

            $sTitulo = "- <comment>" . date('d/m/Y', strtotime($oDadosCommit->date)) . "</comment> as " . date('H:s:i', strtotime($oDadosCommit->date));
            $sTitulo .= " " . count($oDadosCommit->aArquivos) . " arquivo(s) commitado(s)";
            $oOutput->writeln($sTitulo);

            if( !empty($oDadosCommit->title) ) {
                $oOutput->writeln("\n  " . $oDadosCommit->title);
            }

            $oTabela = new Table();
            $oTabela->setHeaders(array('1','1','1','1'));

            foreach ( $oDadosCommit->aArquivos as $oArquivo ) {

                $sArquivo = $this->getApplication()->clearPath($oArquivo->name);
                $sTag     = $oArquivo->tag;
                $sMensagem = $oArquivo->message;

                foreach($oParametros->aArquivos as $sParametroArquivo) {
                    $sArquivo = $this->colorirBusca($sArquivo, $sParametroArquivo, 'busca');
                }

                if ( !empty($oParametros->iTag) ) {
                    $sTag = $this->colorirBusca($oArquivo->tag, $oParametros->iTag, 'busca');
                }

                if ( !empty($oParametros->aMensagens) ) {

                    foreach($oParametros->aMensagens as $sMensagemBuscar) {
                        $sMensagem = $this->colorirBusca($sMensagem, $sMensagemBuscar, 'busca');
                    }
                }

                $oTabela->addRow(array($oArquivo->type, " $sArquivo", " $sTag", " $sMensagem"));
            }

            $oOutput->writeln("  " . str_replace("\n" , "\n  ", $oTabela->render(true)));
        }

    }

    public function colorirBusca($sConteudo, $sBusca, $sTag = 'info') {

        $sBuscaColorida = $sConteudo;

        foreach( explode('%', $sBusca) as $sColorir ) {

            if ( empty($sColorir) ) {
                continue;
            }

            $iPosicao = strpos(strtolower($sConteudo), strtolower($sColorir));

            if ( $iPosicao  === false ) {
                return $sConteudo;
            }

            $sConteudoEncontrado = substr($sConteudo, $iPosicao, strlen($sColorir));
            $sBuscaColorida = str_ireplace($sConteudoEncontrado, "<$sTag>$sConteudoEncontrado</$sTag>", $sBuscaColorida);
        }

        return $sBuscaColorida;
    }

}
