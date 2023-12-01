<?php

namespace App\Command;

use App\Service\DriveManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GapiInitCommand extends Command
{
    protected static $defaultName = 'gapi-init';

    private $_manager;

    public function __construct(DriveManager $manager)
    {
        $this->_manager = $manager;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setHidden(true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->_manager->listFiles();

        /*$arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        if ($input->getOption('option1')) {
            // ...
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');*/
        return 0;
    }
}
