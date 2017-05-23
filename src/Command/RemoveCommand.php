<?php
namespace Cvsgit\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

use \Cvsgit\Model\ArquivoModel;

class RemoveCommand extends Command {

  public function configure() {

    $this->setName('rm');
    $this->addArgument('arquivos', InputArgument::IS_ARRAY, 'Arquivos para remover');
    $this->setDescription('Remove arquivos da lista de commit');
    $this->setHelp('Remove arquivos da lista de commit');
  }

  public function execute($oInput, $oOutput) {

    $oArquivoModel = new ArquivoModel();
    $aArquivos = $oArquivoModel->getAdicionados();
    $aArquivosInformados = $oInput->getArgument('arquivos');
    $aArquivosRemover = array();

    if ( empty($aArquivos) ) {

      $oOutput->writeln('<error>Nenhum arquivo para remover</error>');
      return 1;
    } 

    foreach( $aArquivosInformados as $sArquivo ) {

      if ( empty($aArquivos[$sArquivo]) && empty($aArquivos[getcwd() . '/' . $sArquivo]) ) {

        $oOutput->writeln("<error>Arquivo não encontrado na lista: $sArquivo</error>");
        continue;
      }

      $aArquivosRemover[] = $sArquivo;
    }

    // Nenhum arquivo informado, pergunta se pode remover todos 
    if (empty($aArquivosInformados)) {

        $helper = $this->getHelper('question');
        $question = new Question('Nenhum arquivo inforamado, remover todos da lista? (s/N): ');

        $sRemoverTodos = $helper->ask($oInput, $oOutput, $question);

        if ( strtoupper($sRemoverTodos) == 'S' ) {
            $aArquivosRemover = array_keys($aArquivos);
        }
    }

    if (!empty($aArquivosRemover)) {

      foreach( $aArquivosRemover as $sArquivoRemovido ) {

        $lRemovido = $oArquivoModel->removerArquivo($sArquivoRemovido);

        if ( !$lRemovido ) {
          throw new \Exception('Não foi possivel remover arquivo da lista: ' . $sArquivoRemovido);
        }

        $sArquivoRemovido = $this->getApplication()->clearPath($sArquivoRemovido);
        $oOutput->writeln("<info>Arquivo removido da lista: $sArquivoRemovido</info>");
      }
    }
  }

}
