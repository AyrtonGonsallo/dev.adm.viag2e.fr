<?php

namespace App\Command;

use App\Entity\BankExport;
use App\Entity\Cron;
use App\Entity\File;
use App\Entity\Invoice;
use App\Entity\Parameter;
use App\Entity\PendingInvoice;
use App\Entity\Property;
use App\Entity\DestinataireFacture;
use App\Entity\FactureMensuelle;
use App\Entity\Warrant;
use App\Entity\RevaluationHistory;
use App\Service\Bank;
use App\Service\DriveManager;
use App\Service\InvoiceGenerator;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;

use \Swift_Mailer;
use Swift_Message;
use Swift_Attachment;

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use \Exception;

class CronResendInvoicesCommand extends Command
{
    use LockableTrait;

    private const PROCESS_MAX = 300;
    protected static $defaultName = 'cron:resend-invoices';

    private $container;
    private $manager;
    private $drive;
    private $generator;
    private $mailer;
    private $params;
    private $twig;

    private $pdf_dir;
    private $pdf_logo;

    private $mail_from;

    private $date;

    private $dryRun;
    private $noMail;

    public function __construct(ContainerInterface $container, ManagerRegistry $doctrine, Swift_Mailer $mailer, DriveManager $drive, InvoiceGenerator $generator, ParameterBagInterface $params)
    {
        parent::__construct();

        $this->container = $container;
        $this->manager = $doctrine->getManager();
        $this->drive = $drive;
        $this->generator = $generator;
        $this->mailer = $mailer;
        $this->params = $params;
        $this->twig = $this->container->get('twig');

        $this->pdf_dir = $this->params->get('pdf_tmp_dir');
        $this->pdf_logo = $this->params->get('pdf_logo_path');

        $this->mail_from = $this->params->get('mail_from');
    }

    protected function configure()
    {
        $this
            ->setDescription('Cron command used to generate the invoices')
            ->addOption('no-mail', 'm', InputOption::VALUE_NONE, 'Disable mails')
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

        $start = microtime(true);

        $this->noMail = false;
        if ($this->areMailsDisabled()) {
            $io->note('Mails are disabled');
        }

        $this->dryRun = $input->getOption('dry-run');
        if ($this->isDryRun()) {
            $this->pdf_dir = $this->pdf_dir . '/dry';
            $io->note('Dry run');
        }

        setlocale(LC_TIME, 'fr_FR.utf8', 'French', 'French_France'); // dates in french

        $io->comment('Clearing folder');
        $files = ($this->isDryRun()) ? glob($this->pdf_dir . '/dry/*') : glob($this->pdf_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $parameters = [
            'tva'        => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'tva'])->getValue(),
            'footer'     => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];

        $d = new DateTime('First day of this month');

        $this->date = [
            'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
            'current_month' => date('m'),
            'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
            'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
            'month_n'       => $d->format('m'),
            'year'          => $d->format('Y'),
        ];

        $last_number = $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_number']);
        $io->comment('jour: '.date('d').' date totale:'.date('Y-m-d H:i:s'));
        
       

     
        
        
        function formatter_adresse($sequence){

            $banII_regexp ="/[0-9][0-9][0-9][0-9][0-9].*/";
            preg_match_all($banII_regexp, $sequence, $matches, PREG_OFFSET_CAPTURE);
            if($matches[0]){
                $position=$matches[0][0][1];
                $texte=$matches[0][0][0];
                //echo "<p>Position: ".$position;
                //echo "<br>";
                //echo "Texte: ".$texte."</p>";
                //echo "<br>";
                //echo "Résultats: <br>".substr($sequence, 0, $position)."<br>".$texte."</p>";
                $res=substr($sequence, 0, $position)."<br>".$texte;
                return $res;
            }else{
                return $sequence;
            }
            
          }
        if (date('d') <= 20) {
            

            $io->comment('Renvoi des mails');
            $idmin = $io->ask('id min : ');
            $idmax = $io->ask('id max : ');
            $properties = $this->manager
                ->getRepository(Property::class)
                ->findInvoicesToSend($idmin,$idmax);

            // $last_month = new DateTime('last day of last month');
            // fin charges de copro
            $done = 0;
            /** @var Property $property */
            foreach ($properties as $property) {
                $io->note("current property {$property->getId()} - {$property->getTitle()}");
                if ($done == self::PROCESS_MAX) {
                    break;
                }

                if (!$property->getWarrant()->isActive()) {
                    continue;
                }

                if ($property->hasAnnuitiesDisabled()) {
                    $io->note("Skipping property {$property->getId()}, annuities disabled");
                    continue;
                }
                
                $invoice_r = $this->manager->getRepository(Invoice::class)
                ->getLastPropertyRente($property->getId())[0];
                $data = $invoice_r->getData();

                $io->note("Rente ".$invoice_r->getId());
                $this->resendInvoice($io, $data, $parameters, $property, $invoice_r);
              

                $invoice_h = $this->manager->getRepository(Invoice::class)
                ->getLastPropertyHonoraires($property->getId())[0];
                $data = $invoice_h->getData();

                $io->note("Honoraires ".$invoice_h->getId());
                $this->resendInvoice($io, $data, $parameters, $property, $invoice_h);
                
                $done++;
            }
        }

        

        $io->success('Job finished.');
        $this->release();

        return 0;
    }

    public function resendInvoice(SymfonyStyle &$io, array $data, array $parameters, Property $property,Invoice $invoice, int $category = Invoice::CATEGORY_ANNUITY)
    {
        try {   
			//$data['date']["month"]=utf8_decode($data['date']["month"]);
            if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                $io->note("quaterly files trying to be created ");

            }  
            $filePath = $invoice->getFile() ?  $this->drive->getFile($invoice->getFile()) : -1;
            $filePath2= $invoice->getFile2() ? $this->drive->getFile($invoice->getFile2()) : -1;
            
            

            $cond_h_n=($filePath2 != -1)?true:false; //honoraires non nuls ?
            $cond_r_n=($filePath != -1)?true:false; //rente non nulle ?
           
            if($cond_r_n){
                $file = $invoice->getFile();
                $io->note("envoi de la rente ");
            }
            
			//second fichier
            if($cond_h_n){
                $file2 = $invoice->getFile2();
                $io->note("envoi des honoraires ");
                
            }
			
            if(!$cond_h_n && !$cond_r_n){
				if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){	
                $io->note("no file found ");
                }
            }
             if(!$cond_r_n && $cond_h_n){
                if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                 $io->note("Only honoraires found ");
                }
            }
             if(!$cond_h_n && $cond_r_n){
                if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                  $io->note("Only rente found ");
                }
            }
             if($cond_h_n && $cond_r_n){
                if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                  $io->note("Both rente and honoraires found ");
                }
                   
            }
            
            if ((!empty($data['separation_type']) && ($data['separation_type'] == Property::BUYERS_ANNUITY) && !empty($property->getBuyerMail1())) || !empty($property->getWarrant()->getMail1())) {
                
               
                if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                    if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                        $message = (new Swift_Message($invoice->getMailSubject()))
                            ->setFrom($this->mail_from)
                            //->setBcc($this->mail_from)
                            ->setBcc(["roquetigrinho@gmail.com",$this->mail_from])
                            //->setTo("roquetigrinho@gmail.com")
                            ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                            ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                            ->attach(Swift_Attachment::fromPath($filePath));
                           }else{
                        $message = (new Swift_Message($invoice->getMailSubject()))
                            ->setFrom($this->mail_from)
                            //->setBcc($this->mail_from)
                            ->setBcc(["roquetigrinho@gmail.com",$this->mail_from])   

                            //->setTo("roquetigrinho@gmail.com")
                            ->setTo($invoice->getProperty()->getMail1())
                            ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                            ->attach(Swift_Attachment::fromPath($filePath));
                             if(!empty($invoice->getProperty()->getMail2())) {
                                $message->setCc($invoice->getProperty()->getMail2());
                            }
                    }
                    
                }else{
                    if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                        //si mandat vendeur
                        //envoyer les honoraires aux mandant
                        $io->note($invoice->getProperty()->getId()." mandat vendeur");
                        if($cond_h_n){
                            $message1 = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->mail_from)
                                //->setBcc($this->mail_from)
                                ->setBcc(["roquetigrinho@gmail.com",$this->mail_from])
                                //->setTo("roquetigrinho@gmail.com")
                                ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ->attach(Swift_Attachment::fromPath($filePath2));
                                }
                        //envoyer la rente au buyer /acquereur/acheteur
                        $mailTarget2_r=null;
                        if($cond_r_n){
                            if($invoice->getProperty()->getDebirentierDifferent()){
                                $mailTarget_r=$invoice->getProperty()->getEmailDebirentier();
                                $mailTarget2_r=$invoice->getProperty()->getEmailDebirentier2();
                                $nomTarget_r=$invoice->getProperty()->getNomDebirentier().' '.$invoice->getProperty()->getPrenomDebirentier();
                                }else{
                                $mailTarget_r=$invoice->getProperty()->getWarrant()->getMail1();
                                $nomTarget_r=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                                }
                            
                            $message2 = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->mail_from);
                                if($mailTarget2_r){
                                    //$message2->setBcc($mailTarget2_r);
                                    $message2->setBcc(["roquetigrinho@gmail.com",$this->mail_from]);
                                }
                                $message2//->setBcc($this->mail_from)
                                ->setTo($mailTarget_r)
                                ->setBcc(["roquetigrinho@gmail.com",$this->mail_from])
                                //->setTo("roquetigrinho@gmail.com")
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ->attach(Swift_Attachment::fromPath($filePath));
                                
                                    
                        }
                    }else{
                        //si mandat acquereur                                
                        $io->note($invoice->getProperty()->getId()." mandat acquereur");
                        
                            $message = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->mail_from)
                                //->setBcc($this->mail_from)
                                ->setBcc(["roquetigrinho@gmail.com",$this->mail_from])
                                //->setTo("roquetigrinho@gmail.com")
                                ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                                if($cond_r_n){
                                    $message->attach(Swift_Attachment::fromPath($filePath));
                                }
                                if($cond_h_n){
                                    $message->attach(Swift_Attachment::fromPath($filePath2));
                                }
                                
                    }
                }
                  
                if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    if($cond_h_n){
                        if(!empty($invoice->getMailCc())) {
                            $message1->setCc($invoice->getMailCc());
                        }
        
                        if (!$this->areMailsDisabled() && $this->mailer->send($message1)) {
                            
                            $io->note("mail mandat vendeur envoyé avec les honoraires aux mandants ".$invoice->getProperty()->getWarrant()->getMail1()." et ".$invoice->getMailCc());
                        } else {
                            $io->note("mail mandat vendeur non envoyé");
                        }
                    }
                    if($cond_r_n){
                        if (!$this->areMailsDisabled() && $this->mailer->send($message2)) {
                            $io->note("mail mandat vendeur envoyé avec la rente au buyer /acquereur/acheteur ".$invoice->getProperty()->getBuyerMail1());
                        } else {
                            $io->note("mail mandat vendeur non envoyé ");

                        }
                    }
                        
                        
                }else if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() != Warrant::TYPE_SELLERS){
                    if($cond_h_n || $cond_r_n){
                        if(!empty($invoice->getMailCc())) {
                            $message->setCc($invoice->getMailCc());
                        }
        
                        if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                            
                            $io->note("mail envoyé ");
                        } else {
                            $io->note("mail non envoyé ");
                        }
                    }    
                    
                }else if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                    if(!empty($invoice->getMailCc())) {
                        $message->setCc($invoice->getMailCc());
                    }
    
                    if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                        
                        $io->note("mail envoyé ");
                    } else {
                        $io->note("mail non envoyé ");
                    }
                }
                 
                
              
            
        } else {
           
            $io->note("mail non envoyé ");
        }

            //@unlink($this->pdf_dir . $fileName);
        } catch (Exception $e) {
            $io->error($e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        }
		
    }


	
    private function getTrimester()
    {
        if($this->date['current_month'] <= 3)
            return [
                'text' => 'avril à juin',
                'start' => '01/04/'. $this->date['year'],
                'end' => '30/06/'. $this->date['year']
            ];
        elseif ($this->date['current_month'] <= 6)
            return [
                'text' => 'juillet à septembre',
                'start' => '01/07/'. $this->date['year'],
                'end' => '30/09/'. $this->date['year']
            ];
        elseif ($this->date['current_month'] <= 9)
            return [
                'text' => 'octobre à décembre',
                'start' => '01/10/'. $this->date['year'],
                'end' => '31/12/'. $this->date['year']
            ];

        return [
            'text' => 'janvier à mars',
            'start' => '01/01/'. $this->date['year'],
            'end' => '31/03/'. $this->date['year']
        ];
    }

    private function areMailsDisabled()
    {
        return $this->noMail;
    }

    private function isDryRun()
    {
        return $this->dryRun;
    }
}
