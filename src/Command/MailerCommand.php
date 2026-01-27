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

        if (!$this->lock()) {
            $io->error('The command is already running in another process.');
            return 1;
        }

        $this->dryRun = $input->getOption('dry-run');
        if ($this->isDryRun()) {
            $io->note('Dry run');
        }

        setlocale(LC_TIME, 'fr_FR.utf8', 'French', 'French_France'); // dates in french

        $twig = new Environment(new StringLoader());

        $io->note('Processing new mailings');
        $mailings = $this->manager->getRepository(Mailing::class)->findAwaiting();
        /** @var Mailing $mailing */
        foreach ($mailings as $mailing) {
            $io->note(' => ' . $mailing->getId());
            if($mailing->type_envoi==1){//par groupe de mandants
                $io->note('par groupe de mandants  =>' . $mailing->getObject());
                $warrants = $this->manager->getRepository(Warrant::class)->findByType($mailing->getTarget());
                /** @var Warrant $warrant */
                foreach ($warrants as $warrant) {
                    if(empty($warrant->getMail1()) && empty($warrant->getMail2())) {
                        continue;
                    }

                    try {
                        $mail = new Mail();
                        $mail->setObject($mailing->getObject());
                        $mail->setWarrant($warrant);
                        $mail->setContent($twig->render($mailing->getContent(), Mail::getReplacers($warrant)));
                        $mail->setMailing($mailing);
                        $mail->setEtat(0);
                        $mail->setUser($this->security->getUser());
                        $this->manager->persist($mail);

                        $mailing->addTotal();
                    } catch (LoaderError $e) {
                        $io->error($e->getMessage());
                    } catch (RuntimeError $e) {
                        $io->error($e->getMessage());
                    } catch (SyntaxError $e) {
                        $io->error($e->getMessage());
                    }
                }
                $mailing->setStatus(Mailing::STATUS_GENERATED);
                $this->manager->flush();
            }else if($mailing->type_envoi==2){//par mandant individuel
                $io->note('par mandant individuel  =>' . $mailing->getObject());
                $warrant = $this->manager->getRepository(Warrant::class)->find($mailing->single_target_id);
                /** @var Warrant $warrant */
                if(!empty($warrant->getMail1()) && !empty($warrant->getMail2())) {
                    try {
                        $mail = new Mail();
                        $mail->setObject($mailing->getObject());
                        $mail->setWarrant($warrant);
                        $mail->setContent($twig->render($mailing->getContent(), Mail::getReplacers($warrant)));
                        $mail->setMailing($mailing);
                        $mail->setEtat(0);
                        $mail->setUser($this->security->getUser());
                        $this->manager->persist($mail);

                        $mailing->addTotal();
                    } catch (LoaderError $e) {
                        $io->error($e->getMessage());
                    } catch (RuntimeError $e) {
                        $io->error($e->getMessage());
                    } catch (SyntaxError $e) {
                        $io->error($e->getMessage());
                    }
                }
                $mailing->setStatus(Mailing::STATUS_GENERATED);
                $this->manager->flush();
            }else if($mailing->type_envoi==3){//par email direct
                $io->note('par email direct  =>' . $mailing->getObject());
                try {
                    $mail = new Mail();
                    $mail->setObject($mailing->getObject());
                    $mail->setWarrant(null);
                    $mail->setContent($twig->render($mailing->getContent(), Mail::getReplacers(new Warrant())));
                    $mail->setMailing($mailing);
                    $mail->setEtat(0);
                    $mail->setUser($this->security->getUser());
                    $this->manager->persist($mail);

                    $mailing->addTotal();
                } catch (LoaderError $e) {
                    $io->error($e->getMessage());
                } catch (RuntimeError $e) {
                    $io->error($e->getMessage());
                } catch (SyntaxError $e) {
                    $io->error($e->getMessage());
                }
                $mailing->setStatus(Mailing::STATUS_GENERATED);
                $this->manager->flush();
            }
           
        }

        $io->note('Processing sendings');
        /*$message = (new \Swift_Message('Hello Email'))
        ->setFrom('send@example.com')
        ->setTo('heroku05072022@gmail.com')
        ->setBody("hello", 'text/html');
        
        $this->mailer->send($message);*/
        $mails = $this->manager->getRepository(Mail::class)->findGenerated(self::PROCESS_MAX);
        /** @var Mail $mail */
        foreach ($mails as $mail) {
            $mailing=$mail->getMailing();
            try {
                $io->note(' => ' . $mail->getId());
                if($mailing->type_envoi==3){
                    if (empty($mail->getMailing()->single_target_email)) {
                        $io->error('Empty recipient');
                        $mail->setStatus(Mail::STATUS_UNSENT);
                        $this->manager->flush();
                        continue;
                    }
                }else{
                    if (empty($mail->getWarrant()->getMail1()) && empty($mail->getWarrant()->getMail2())) {
                        $io->error('Empty recipient');
                        $mail->setStatus(Mail::STATUS_UNSENT);
                        $this->manager->flush();
                        continue;
                    }
                }
                
                

                $message = (new Swift_Message($mail->getObject()))
                    ->setFrom($this->mail_from)
                    ->setBcc($this->mail_from)
                    ->setBody($mail->getContent(), 'text/html');
                if($mailing->type_envoi==3){
                    if (!empty($mail->getMailing()->single_target_email)) {
                        $message->setTo($mail->getMailing()->single_target_email);
                    }
                }else{
                    if (!empty($mail->getWarrant()->getMail1())) {
                        $message->setTo($mail->getWarrant()->getMail1());
                    }

                    if (!empty($mail->getWarrant()->getMail2())) {
                        $message->setCc($mail->getWarrant()->getMail2());
                    }
                }
                if($mailing->getPieceJointeDriveId()){
                    $file=$mailing->getPieceJointe();
                    //$path = $this->driveManager->getFile($file);
                   // $pj = $this->manager->getRepository(PieceJointe::class)->findByDriveID($mail->getMailing()->getPieceJointeDriveId());
                    $filePath = $this->tmp_files_dir.'/'.$file->getName();
                    $io->note('possède une piece jointe: '.$filePath);
                    //$message->attach(\Swift_Attachment::fromPath($filePath));
                    $message->attach(Swift_Attachment::fromPath($filePath));

                }

                if ($this->mailer->send($message)) {
                    if($mailing->type_envoi==3){
                        $io->note('Mail sent to '.$mail->getMailing()->single_target_email);
                    }
                    $mail->setStatus(Mail::STATUS_SENT);
                } else {
                    $io->error('Sending error');
                    $mail->setStatus(Mail::STATUS_UNSENT);
                }

                /*$email = new Email();
                $email->from($this->mail_from);

                if(!empty($mail->getWarrant()->getMail1())) {
                    $email->to($mail->getWarrant()->getMail1());
                }

                if(!empty($mail->getWarrant()->getMail2())) {
                    $email->cc($mail->getWarrant()->getMail2());
                }

                $email->subject($mail->getObject());
                $email->html($mail->getContent());

                try {
                    $this->mailer->send($email);
                    $mail->setStatus(Mail::STATUS_SENT);
                } catch (TransportExceptionInterface $e) {
                    $mail->setStatus(Mail::STATUS_UNSENT);
                }*/

                if(!empty($mail->getMailing()))
                    $mail->getMailing()->addSent();

                $this->manager->flush();
            } catch (Exception $e) {
                $io->error($e->getMessage());
            }
        }

        return 0;
    }

    private function isDryRun()
    {
        return $this->dryRun;
    }
}
