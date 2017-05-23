<?php
define('APPLICATION_DIR', dirname(__DIR__) . '/');
define('CONFIG_DIR', getenv('HOME') . '/.cvsgit/');

if (!file_exists(APPLICATION_DIR . 'vendor/autoload.php')) {
    die('Instalação não executada.' . PHP_EOL);
}

require_once APPLICATION_DIR . 'vendor/autoload.php';

try {

  /**
   * Instancia app e define arquivo de configração
   */
  $oCVS = new Cvsgit\CVSApplication();

  /**
   * Adiciona programas 
   */
  $oCVS->addCommands(array(
    new \Cvsgit\Command\HistoryCommand(),
    new \Cvsgit\Command\InitCommand(),
    new \Cvsgit\Command\PushCommand(),
    new \Cvsgit\Command\AddCommand(),
    new \Cvsgit\Command\TagCommand(),
    new \Cvsgit\Command\RemoveCommand(),
    new \Cvsgit\Command\StatusCommand(),
    new \Cvsgit\Command\PullCommand(),
    new \Cvsgit\Command\DiffCommand(),
    new \Cvsgit\Command\LogCommand(),
    new \Cvsgit\Command\ConfigCommand(),
    new \Cvsgit\Command\WhatChangedCommand(),
    new \Cvsgit\Command\AnnotateCommand(),
    new \Cvsgit\Command\DieCommand(),
    new \Cvsgit\Command\CheckoutCommand(),
  ));

  /**
   * Executa aplicacao 
   */
  $oCVS->run();

} catch(Exception $oErro) {

  $oOutput = new \Symfony\Component\Console\Output\ConsoleOutput();
  $oOutput->writeln("<error>\n [You Tá The Brinqueichon Uite Me, cara?]\n " . $oErro->getMessage() . "\n</error>");
}
