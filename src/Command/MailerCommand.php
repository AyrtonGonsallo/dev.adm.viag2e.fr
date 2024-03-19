<?php

namespace App\Command;

use App\Entity\Mail;
use App\Entity\Mailing;
use App\Entity\PieceJointe;
use App\Entity\Warrant;
use App\Twig\StringLoader;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\DriveManager;
use Exception;
use Swift_Mailer;
use Swift_Attachment;
use Swift_Message;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Symfony\Component\Security\Core\Security;

class MailerCommand extends Command
{
    /**
     * @var Security
     */
    private $security;

    

    use LockableTrait;
    private const PROCESS_MAX = 5;

    protected static $defaultName = 'app:mailer';

    private $dryRun;

    private $mailer;
    private $manager;
    private $driveManager;
    private $mail_from;
    private $tmp_files_dir;
    private $pdf_tmp_dir;
    public function __construct(Security $security, DriveManager $driveManager_r,ManagerRegistry $doctrine, Swift_Mailer $mailer /*MailerInterface $mailer*/, ParameterBagInterface $params)
    {
        parent::__construct();
        $this->security = $security;
        $this->mailer = $mailer;
        $this->manager = $doctrine->getManager();
        $this->driveManager = $driveManager_r;
        $this->mail_from = $params->get('mail_from');
        $this->tmp_files_dir = $params->get('tmp_files_dir');
        $this->pdf_tmp_dir = $params->get('pdf_tmp_dir');
    }

    protected function configure()
    {
        $this
            ->setDescription('Command to send emails')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Disable mails and save for testing purposes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

       

        $io->note('Processing sendings');
        $message = (new Swift_Message("Test de l'ancienne configuration smtp"))
            ->setFrom($this->getParameter('mail_from'))
            ->setBcc($this->getParameter('mail_from'))
            ->setTo("ayrtongonsallo444@gmail.com")
            ->setBody("Ceci est un mail de test. S'il est parvenu à vous c'est que l'ancienne configuration Smtp est bien faite.", 'text/html');
		$message->setCc("roquetigrinho@gmail.com");
        $mailer->send($message);
       
        return 0;
    }

    private function isDryRun()
    {
        return $this->dryRun;
    }
}
