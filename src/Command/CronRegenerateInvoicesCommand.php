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

class CronRegenerateInvoicesCommand extends Command
{
    use LockableTrait;


    private const PROCESS_MAX = 5;
    protected static $defaultName = 'cron:regenerate';

    private $container;
    private $manager;
    private $drive;
    private $generator;
    private $mailer;
    private $params;
    private $twig;

    private $pdf_dir;
    private $pdf_logo;
    private $invoice_number;
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
        $this->invoice_number = 5812;
        $this->pdf_dir = $this->params->get('pdf_tmp_dir');
        $this->pdf_logo = $this->params->get('pdf_logo_path');

        $this->mail_from = $this->params->get('mail_from');
    }

    protected function configure()
    {
        $this
            ->setDescription('Cron command used to regenerate the invoices')
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

        $this->noMail = $input->getOption('no-mail');
        if ($this->areMailsDisabled()) {
            $io->note('Mails are disabled');
        }

        $this->dryRun = $input->getOption('dry-run');
        if ($this->isDryRun()) {
            $this->pdf_dir = $this->pdf_dir . '/dry';
            $io->note('Dry run');
        }

        setlocale(LC_TIME, 'fr_FR.utf8', 'French', 'French_France'); // dates in french

      

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

        $d = new DateTime('First day of next month');

        $this->date = [
            'current_day'   => strftime('%A %e %B %Y'),
            'current_month' => date('m'),
            'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
            'month'         => strftime('%B', $d->getTimestamp()),
            'month_n'       => $d->format('m'),
            'year'          => $d->format('Y'),
        ];

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
      
          function processNumber(int $number)
          {
              static $assignedNumbers = []; // Keeps track of numbers and their corresponding results
              static $nextNumber = 	7309; // Starting value for the first number
          
              // Check if the number has already been assigned a value
              if (isset($assignedNumbers[$number])) {
                  return $assignedNumbers[$number];
              }
          
              // Assign the current $nextNumber to the input $number
              $assignedNumbers[$number] = $nextNumber;
          
              // Increment $nextNumber for the next new number
              $nextNumber++;
          
              // Return the assigned number
              return $assignedNumbers[$number];
          }
          
        

          
        if (date('d') >= 20) {
        
            


            $io->comment('Creating avoirs');
            $quittances = $this->manager
                ->getRepository(Invoice::class)
                ->findAvoirsTogenerate(50);
                $number=7309;
            foreach ($quittances as $quittance) {
                
                $data=$quittance->getData();
                // $data["number_int"]=$number;
                $property=$quittance->getProperty();
                $this->generateAvoir($io, $data, $parameters, $property,$quittance,$number);
                
                $number+=1;
            }
                
            
        

/*
code de janvier 2024

 if (date('d') == 17) {
        
            


            $io->comment('Creating avoirs');
            $quittances = $this->manager
                ->getRepository(Invoice::class)
                ->findAvoirsTogenerate(50);
                $old_number=0;
                //$number=5002;
                $number=5052;
                //$number=5102;
                //$number=5105;
            foreach ($quittances as $quittance) {
                $data=$quittance->getData();
                $existing= $this->manager
                ->getRepository(Invoice::class)
                ->findSimilarInvoice($data["number"]);
                if($existing){
                    $old_number=$existing[0]->getNumber();
                    $io->note("la facture ".$existing[0]->getId()." a le meme numero ");
                    
                }else{
                   
                    $old_number=$number;
                   
                }
                
                // $data["number_int"]=$number;
                $property=$quittance->getProperty();
                $this->generateAvoir($io, $data, $parameters, $property,$quittance,$old_number);
                if($existing){
                    $number+=0;
                    
                }else{
                    $number+=1;
                }
                
            }
            $io->comment('Recreating invoices');
            $invoices = $this->manager
                ->getRepository(Invoice::class)
                ->findInvoicesToRegenerate(50);
                //$number=5105;
                //$number=5155;
                //$number=5205;
                $number=5208;
                $old_number=0;
            foreach ($invoices as $inv) {
                $data=$inv->getData();
                $existing= $this->manager
                ->getRepository(Invoice::class)
                ->findSimilarInvoice2($data["number"]);
                if($existing){
                    $old_number=$existing[0]->getNumber();
                    $io->note("la facture ".$existing[0]->getId()." a le meme numero ");
                    
                }else{
                   
                    $old_number=$number;
                   
                }
               // $data["number_int"]=$number;
                $property=$inv->getProperty();
                $this->regenerateAvis($io, $data, $parameters, $property,$inv,$old_number);
                if($existing){
                    $number+=0;
                    
                }else{
                    $number+=1;
                }
            }
*/

             /*
            $io->comment('Recreating payed quittances');
            $invoices = $this->manager
                ->getRepository(Invoice::class)
                ->findQuittancesToRegenerate(50);
                
                //$number=5208;
                //$number=5258;
                //$number=5308;
                $number=5311;
                $old_number=0;
            foreach ($invoices as $inv) {
                $data=$inv->getData();
                $existing= $this->manager
                ->getRepository(Invoice::class)
                ->findSimilarInvoice2($data["number"]);
                if($existing){
                    $old_number=$existing[0]->getNumber();
                    $io->note("la facture ".$existing[0]->getId()." a le meme numero ");
                    
                }else{
                   
                    $old_number=$number;
                   
                }
               // $data["number_int"]=$number;
                $property=$inv->getProperty();
                $this->regenerateQuittance($io, $data, $parameters, $property,$inv,$old_number);
                if($existing){
                    $number+=0;
                    
                }else{
                    $number+=1;
                }
            }
*/

            $this->manager->flush();
         }
    }



    public function generateAvoir(SymfonyStyle &$io, array $data, array $parameters, Property $property,Invoice $invoice,int $number)
    {
        try {   
			$number=processNumber($data["number_int"]);
          $type=$invoice->getFile()?"rente":"honoraire";
        $io->note("generation d'avoir avec le numero ".$number." sur la facture ".$data["number_int"]." du fichier de type ".$type." avec le numéro ".$number);
        
        $invoice2 = new Invoice();
        $invoice2->setCategory(Invoice::CATEGORY_AVOIR);
        $invoice2->setType(Invoice::TYPE_AVOIR);
        $data["old_number_int"]=$data["number_int"];
        $data["old_number"]=$data["number"];
        $invoice2->setNumber($number);
        $data["number_int"]=$number;
        $data["number"]="AV".substr($data["number"],2,-4).$number;
        $data["date"]["current_day"]=strftime('%A %e %B %Y');
        $data["date"]["current_month"]=date('m');
        $data["property"]['firstname' ] = $property->getFirstname1();
        $data["property"]['lastname'  ] = $property->getLastname1();
        $data["property"]['firstname2'] = $property->getFirstname2();
        $data["property"]['lastname2' ] = $property->getLastname2();
        $data["property"]['address'   ] = formatter_adresse($property->getGoodAddress());
        $data["property"]['postalcode'] = $property->getPostalCode();
        $data["property"]['city'      ] = $property->getCity();
        $data['warrant'] = [
            'id'         => $property->getWarrant()->getId(),
            'type'       => $property->getWarrant()->getType(),
            'firstname'  => $property->getWarrant()->getFirstname(),
            'lastname'   => $property->getWarrant()->getLastname(),
            'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
            'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
            'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
        ];
        if( $property->getDebirentierDifferent()){
            $debirentier    = [
                'nom_debirentier'         =>  $property->getNomDebirentier(),
                'prenom_debirentier'       =>  $property->getPrenomDebirentier(),
                'addresse_debirentier'  =>  $property->getAddresseDebirentier(),
                'code_postal_debirentier'   =>  $property->getCodePostalDebirentier(),
                'ville_debirentier'    =>  $property->getVilleDebirentier(),
            ];
            $data["debirentier"]=$debirentier;
            $data["debirentier_different"]= $property->getDebirentierDifferent();
        }
        if($property->getWarrant()->getType() === Warrant::TYPE_SELLERS) {
            $data['buyer'] = [
                'firstname'  => $property->getBuyerFirstname(),
                'lastname'   => $property->getBuyerLastname(),
                'address'    => $property->getBuyerAddress(),
                'postalcode' => $property->getBuyerPostalCode(),
                'city'       => $property->getBuyerCity(),
            ];
        }
        $invoice2->setData($data);
        $data = $invoice2->getData();
        $invoice2->setDate(new DateTime());
        $invoice2->setStatus(Invoice::STATUS_SENT);
        $invoice2->setProperty($property);
       // $invoice2->setData($data);
        $invoice->date_generation_avoir=new DateTime();
       
        
        if($invoice->getCategory() == Invoice::CATEGORY_ANNUITY) {
            $filePath = $this->generator->generateAvoirFile($data, $parameters);
            $filePath2= $this->generator->generateAvoirFile2($data, $parameters);
        }
        else if($invoice->getCategory() == Invoice::CATEGORY_REGULE_CONDOMINIUM_FEES) {
            $io->note("Un seul fichier pour le type ".$invoice->getCategory()." et le montant ".$data['montantttc']);
            $data['amount']=$data['montantttc'];
            $filePath = $this->generator->generateAvoirFile($data, $parameters);
            $filePath2= -1;
        }
        else{
            $filePath = $this->generator->generateAvoirFile($data, $parameters);
            $filePath2= -1;
        }
        

        $cond_h_n=($filePath2 != -1)?true:false; //honoraires nuls ?
        $cond_r_n=($filePath != -1)?true:false; //rente nulle ?

        if($cond_r_n){
            $file = new File();
            $file->setType(File::TYPE_INVOICE);
            if($data['recursion'] ==Invoice::RECURSION_MONTHLY){
                $file->setName("{$data['number']} - R");
            }else{
                $file->setName("{$invoice->getTypeString()} {$data['date']['month_n']}-{$data['date']['year']} #{$property->getId()}");
            }
            $file->setWarrant($property->getWarrant());
            $file->setDriveId($this->drive->addFile($file->getName(), $filePath, File::TYPE_INVOICE, $property->getWarrant()->getId()));
            $this->manager->persist($file);
            $io->note("Fichier avoir rente crée ".$filePath);
        }
        
        //second fichier
        if($cond_h_n){
            $file2 = new File();
            $file2->setType(File::TYPE_INVOICE);
            if($data['recursion'] ==Invoice::RECURSION_MONTHLY){
                $file2->setName("{$data['number']} - H");
            }else{
                $file2->setName("{$invoice->getTypeString()} {$data['date']['month_n']}-{$data['date']['year']} #{$invoice->getId()} - R file2");
            }
            $file2->setWarrant($property->getWarrant());
            /** @noinspection PhpUnhandledExceptionInspection */
            $file2->setDriveId($this->drive->addFile($file2->getName(), $filePath2, File::TYPE_INVOICE, $property->getWarrant()->getId()));
            $this->manager->persist($file2);
            $io->note("Fichier avoir honoraire crée ".$filePath2);
        }
        //$invoice->setFile($file);
      
        
        if($cond_r_n){
            $invoice2->setFile($file);
        }
        if($cond_h_n){
            $invoice2->setFile2($file2);
        }
        $this->manager->persist($invoice2);
        $this->manager->persist($invoice);
     
                
            
            if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    $message = (new Swift_Message('Avoir '.$invoice->getMailSubject()))
                        ->setFrom($this->mail_from)
                        ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                        ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                        ->setBody($this->twig->render('invoices/emails/notice_avoir.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                        ->attach(Swift_Attachment::fromPath($filePath));
                       
                }else{
                    $message = (new Swift_Message('Avoir '.$invoice->getMailSubject()))
                        ->setFrom($this->mail_from)
                        ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                        ->setTo($invoice->getProperty()->getMail1())
                        ->setBody($this->twig->render('invoices/emails/notice_avoir.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
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
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                ->setBody($this->twig->render('invoices/emails/notice_avoir.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ->attach(Swift_Attachment::fromPath($filePath2));
                               
                        }
                        //envoyer la rente au buyer /acquereur/acheteur
                        if($cond_r_n){
                            if($invoice->getProperty()->getDebirentierDifferent()){
                                $mailTarget_r=$invoice->getProperty()->getEmailDebirentier();
                                $nomTarget_r=$invoice->getProperty()->getNomDebirentier().' '.$invoice->getProperty()->getPrenomDebirentier();
                               
                            }else{
                                $mailTarget_r=$invoice->getProperty()->getWarrant()->getMail1();
                                $nomTarget_r=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                              
                            }
                            $message2 = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->mail_from)
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($mailTarget_r)
                                ->setBody($this->twig->render('invoices/emails/notice_avoir.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ->attach(Swift_Attachment::fromPath($filePath));
                              
                        }
                    }else{
                        //si mandat acquereur                                
                        $io->note($invoice->getProperty()->getId()." mandat acquereur");
                        $message = (new Swift_Message('Avoir '.$invoice->getMailSubject()))
                                ->setFrom($this->mail_from)
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                ->setBody($this->twig->render('invoices/emails/notice_avoir.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ;
                        if($cond_h_n){
                            $message->attach(Swift_Attachment::fromPath($filePath2));
                             
                        }
                        if($cond_r_n){
                            $message->attach(Swift_Attachment::fromPath($filePath));
                             
                        }
                    }
            }
            if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    if($cond_h_n){
                        if(!empty($invoice->getMailCc())) {
                            $message1->setCc($invoice->getMailCc());
                        }
        
                        if (!$this->areMailsDisabled() && $this->mailer->send($message1)) {
                            $invoice->setStatus(Invoice::STATUS_SENT);
                            $io->note("mail avoir vendeur envoyé avec les honoraires aux mandants ".$invoice->getProperty()->getWarrant()->getMail1()." et ".$invoice->getMailCc());
                        } else {
                            $invoice->setStatus(Invoice::STATUS_UNSENT);
                        }
                    }
                    if($cond_r_n){
                        if (!$this->areMailsDisabled() && $this->mailer->send($message2)) {
                            $invoice->setStatus(Invoice::STATUS_SENT);
                            $io->note("mail avoir vendeur envoyé avec la rente au buyer /acquereur/acheteur ".$invoice->getProperty()->getBuyerMail1());
                        } else {
                            $invoice->setStatus(Invoice::STATUS_UNSENT);
                        }
                    }
                    
                    
            }else if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() != Warrant::TYPE_SELLERS){
                if($cond_h_n || $cond_r_n){
                    if(!empty($invoice->getMailCc())) {
                        $message->setCc($invoice->getMailCc());
                    }
    
                    if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                        $invoice->setStatus(Invoice::STATUS_SENT);
                        $io->note("mail envoyé ");
                    } else {
                        $invoice->setStatus(Invoice::STATUS_UNSENT);
                    }
                }    
                
              }else if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                    if(!empty($invoice->getMailCc())) {
                        $message->setCc($invoice->getMailCc());
                    }
    
                    if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                        $invoice->setStatus(Invoice::STATUS_SENT);
                        $io->note("mail envoyé ");
                    } else {
                        $invoice->setStatus(Invoice::STATUS_UNSENT);
                    }
            }
           
    }
            //@unlink($this->pdf_dir . $fileName);
         catch (Exception $e) {
            $io->error($e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        }
		
    }



    public function regenerateAvis(SymfonyStyle &$io, array $data, array $parameters, Property $property,Invoice $invoice,int $number)
    {
        try {   
			
          $type=$invoice->getFile()?"rente":"honoraire";
        $io->note("Suppression et Recréation sur la facture ".$data["number_int"]." du fichier de type ".$type." avec le numéro ".$number." a l'addresse ".utf8_decode($data['property']['address']));
        $data["old_number_int"]=$data["number_int"];
        $data["old_number"]=$data["number"];
        $data['property']['address']=utf8_decode($data['property']['address']);
        $data["number_int"]=$number;
        $data["number"]=substr($data["number"],0,-4).$number;
       
        $data["date"]["current_day"]=strftime('%A %e %B %Y');
        $data["date"]["current_month"]=date('m');
        $data["property"]['firstname' ] = $property->getFirstname1();
        $data["property"]['lastname'  ] = $property->getLastname1();
        $data["property"]['firstname2'] = $property->getFirstname2();
        $data["property"]['lastname2' ] = $property->getLastname2();
        $data["property"]['address'   ] = formatter_adresse($property->getGoodAddress());
        $data["property"]['postalcode'] = $property->getPostalCode();
        $data["property"]['city'      ] = $property->getCity();
        $data['warrant'] = [
            'id'         => $property->getWarrant()->getId(),
            'type'       => $property->getWarrant()->getType(),
            'firstname'  => $property->getWarrant()->getFirstname(),
            'lastname'   => $property->getWarrant()->getLastname(),
            'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
            'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
            'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
        ];
        if( $property->getDebirentierDifferent()){
            $debirentier    = [
                'nom_debirentier'         =>  $property->getNomDebirentier(),
                'prenom_debirentier'       =>  $property->getPrenomDebirentier(),
                'addresse_debirentier'  =>  $property->getAddresseDebirentier(),
                'code_postal_debirentier'   =>  $property->getCodePostalDebirentier(),
                'ville_debirentier'    =>  $property->getVilleDebirentier(),
            ];
            $data["debirentier"]=$debirentier;
            $data["debirentier_different"]= $property->getDebirentierDifferent();
        }
        if($property->getWarrant()->getType() === Warrant::TYPE_SELLERS) {
            $data['buyer'] = [
                'firstname'  => $property->getBuyerFirstname(),
                'lastname'   => $property->getBuyerLastname(),
                'address'    => $property->getBuyerAddress(),
                'postalcode' => $property->getBuyerPostalCode(),
                'city'       => $property->getBuyerCity(),
            ];
        }
        $invoice2 = new Invoice();
        $invoice2->setCategory($invoice->getCategory());
        $invoice2->setType($invoice->getType());
        $invoice->date_regeneration=new DateTime();
        $invoice2->setData($data);
        $data = $invoice2->getData();
        $filePath = $this->generator->generateFile($data, $parameters);
        $filePath2= $this->generator->generateFile2($data, $parameters);

        if ($this->isDryRun()) {
            return;
        }
        
        
        

        $cond_h_n=($filePath2 != -1)?true:false; //honoraires nuls ?
        $cond_r_n=($filePath != -1)?true:false; //rente nulle ?
       
        if($cond_r_n){
            $file = new File();
            $file->setType(File::TYPE_INVOICE);
            if($data['recursion'] ==Invoice::RECURSION_MONTHLY){
                $file->setName("{$data['number']} - R");
            }else{
                $file->setName("{$invoice->getTypeString()} {$data['date']['month_n']}-{$data['date']['year']} #{$property->getId()}");
            }
            $file->setWarrant($property->getWarrant());
            $file->setDriveId($this->drive->addFile($file->getName(), $filePath, File::TYPE_INVOICE, $property->getWarrant()->getId()));
            $this->manager->persist($file);
            $io->note("Fichier rente recrée ".$filePath);
        }
        
        //second fichier
        if($cond_h_n){
            $file2 = new File();
            $file2->setType(File::TYPE_INVOICE);
            if($data['recursion'] ==Invoice::RECURSION_MONTHLY){
                $file2->setName("{$data['number']} - H");
            }else{
                $file2->setName("{$invoice->getTypeString()} {$data['date']['month_n']}-{$data['date']['year']} #{$invoice->getId()} - R file2");
            }
            $file2->setWarrant($property->getWarrant());
            /** @noinspection PhpUnhandledExceptionInspection */
            $file2->setDriveId($this->drive->addFile($file2->getName(), $filePath2, File::TYPE_INVOICE, $property->getWarrant()->getId()));
            $this->manager->persist($file2);
            $io->note("Fichier honoraire recrée ".$filePath2);
        }
        //$invoice->setFile($file);
        $invoice2->setNumber($data['number_int']);
        
        if($cond_r_n){
            $invoice2->setFile($file);
        }
        if($cond_h_n){
            $invoice2->setFile2($file2);
        }
        $invoice2->setDate(new DateTime());
        //$invoice2->setData($data);
        $invoice2->setProperty($property);
        $invoice2->setStatus(Invoice::STATUS_TREATED);
            $this->manager->persist($invoice2);
            $this->manager->persist($invoice);

            if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    $message = (new Swift_Message($invoice->getMailSubject()))
                        ->setFrom($this->mail_from)
                        ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                        ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                        ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                        ->attach(Swift_Attachment::fromPath($filePath));
                       
                }else{
                    $message = (new Swift_Message($invoice->getMailSubject()))
                        ->setFrom($this->mail_from)
                        ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                        ->setTo($invoice->getProperty()->getMail1())
                        ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
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
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ->attach(Swift_Attachment::fromPath($filePath2));
                               
                        }
                        //envoyer la rente au buyer /acquereur/acheteur
                        if($cond_r_n){
                            if($invoice->getProperty()->getDebirentierDifferent()){
                                $mailTarget_r=$invoice->getProperty()->getEmailDebirentier();
                                $nomTarget_r=$invoice->getProperty()->getNomDebirentier().' '.$invoice->getProperty()->getPrenomDebirentier();
                               
                            }else{
                                $mailTarget_r=$invoice->getProperty()->getWarrant()->getMail1();
                                $nomTarget_r=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                              
                            }
                            $message2 = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->mail_from)
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($mailTarget_r)
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ->attach(Swift_Attachment::fromPath($filePath));
                              
                        }
                    }else{
                        //si mandat acquereur                                
                        $io->note($invoice->getProperty()->getId()." mandat acquereur");
                        $message = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->mail_from)
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ;
                        if($cond_h_n){
                            $message->attach(Swift_Attachment::fromPath($filePath2));
                             
                        }
                        if($cond_r_n){
                            $message->attach(Swift_Attachment::fromPath($filePath));
                             
                        }
                    }
            }
            if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    if($cond_h_n){
                        if(!empty($invoice->getMailCc())) {
                            $message1->setCc($invoice->getMailCc());
                        }
        
                        if (!$this->areMailsDisabled() && $this->mailer->send($message1)) {
                            $io->note("mail mandat vendeur regeneré envoyé avec les honoraires aux mandants ".$invoice->getProperty()->getWarrant()->getMail1()." et ".$invoice->getMailCc());
                        } else {
                            $io->note("erreur envoi mail");
                        }
                    }
                    if($cond_r_n){
                        if (!$this->areMailsDisabled() && $this->mailer->send($message2)) {
                            $io->note("mail mandat vendeur regeneré envoyé avec la rente au buyer /acquereur/acheteur ".$invoice->getProperty()->getBuyerMail1());
                        } else {
                            $io->note("erreur envoi mail");                        }
                    }
                    
                    
            }else if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() != Warrant::TYPE_SELLERS){
                if($cond_h_n || $cond_r_n){
                    if(!empty($invoice->getMailCc())) {
                        $message->setCc($invoice->getMailCc());
                    }
    
                    if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                        $io->note("mail envoyé ");
                    } else {
                        $io->note("erreur envoi mail");                    }
                }    
                
              }else if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                    if(!empty($invoice->getMailCc())) {
                        $message->setCc($invoice->getMailCc());
                    }
    
                    if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                        $io->note("mail envoyé ");
                    } else {
                        $io->note("erreur envoi mail");                    }
            }
        
        
            //@unlink($this->pdf_dir . $fileName);
        } catch (Exception $e) {
            $io->error($e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        }
		
    }


   
    public function regenerateQuittance(SymfonyStyle &$io, array $data, array $parameters, Property $property,Invoice $invoice,int $number)
    {
        try {   
			
          $type=$invoice->getFile()?"rente":"honoraire";
        $io->note("Suppression et Recréation sur la facture ".$data["number_int"]." du fichier de type ".$type." avec le numéro ".$number." a l'addresse ".utf8_decode($data['property']['address']));
        $data["old_number_int"]=$data["number_int"];
        $data["old_number"]=$data["number"];
        $data['property']['address']=utf8_decode($data['property']['address']);
        $data["number_int"]=$number;
        $data["number"]=substr($data["number"],0,-4).$number;
       
        $data["date"]["current_day"]=strftime('%A %e %B %Y');
        $data["date"]["current_month"]=date('m');
        $filePath = $this->generator->generateFile($data, $parameters);
        $filePath2= $this->generator->generateFile2($data, $parameters);
        $data["property"]['firstname' ] = $property->getFirstname1();
        $data["property"]['lastname'  ] = $property->getLastname1();
        $data["property"]['firstname2'] = $property->getFirstname2();
        $data["property"]['lastname2' ] = $property->getLastname2();
        $data["property"]['address'   ] = utf8_encode(formatter_adresse($property->getGoodAddress()));
        $data["property"]['postalcode'] = $property->getPostalCode();
        $data["property"]['city'      ] = $property->getCity();
        $data['warrant'] = [
            'id'         => $property->getWarrant()->getId(),
            'type'       => $property->getWarrant()->getType(),
            'firstname'  => $property->getWarrant()->getFirstname(),
            'lastname'   => $property->getWarrant()->getLastname(),
            'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
            'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
            'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
        ];
        if( $property->getDebirentierDifferent()){
            $debirentier    = [
                'nom_debirentier'         =>  $property->getNomDebirentier(),
                'prenom_debirentier'       =>  $property->getPrenomDebirentier(),
                'addresse_debirentier'  =>  $property->getAddresseDebirentier(),
                'code_postal_debirentier'   =>  $property->getCodePostalDebirentier(),
                'ville_debirentier'    =>  $property->getVilleDebirentier(),
            ];
            $data["debirentier"]=$debirentier;
            $data["debirentier_different"]= $property->getDebirentierDifferent();
        }
        if($property->getWarrant()->getType() === Warrant::TYPE_SELLERS) {
            $data['buyer'] = [
                'firstname'  => $property->getBuyerFirstname(),
                'lastname'   => $property->getBuyerLastname(),
                'address'    => $property->getBuyerAddress(),
                'postalcode' => $property->getBuyerPostalCode(),
                'city'       => $property->getBuyerCity(),
            ];
        }
        if ($this->isDryRun()) {
            return;
        }
        $invoice2 = new Invoice();
        $invoice2->setCategory($invoice->getCategory());
        $invoice2->setType($invoice->getType());
        $invoice->date_regeneration=new DateTime();
        
        

        $cond_h_n=($filePath2 != -1)?true:false; //honoraires nuls ?
        $cond_r_n=($filePath != -1)?true:false; //rente nulle ?
       
        if($cond_r_n){
            $file = new File();
            $file->setType(File::TYPE_INVOICE);
            if($data['recursion'] ==Invoice::RECURSION_MONTHLY){
                $file->setName("{$data['number']} - R");
            }else{
                $file->setName("{$invoice->getTypeString()} {$data['date']['month_n']}-{$data['date']['year']} #{$property->getId()}");
            }
            $file->setWarrant($property->getWarrant());
            $file->setDriveId($this->drive->addFile($file->getName(), $filePath, File::TYPE_INVOICE, $property->getWarrant()->getId()));
            $this->manager->persist($file);
            $io->note("Fichier rente recrée ".$filePath);
        }
        
        //second fichier
        if($cond_h_n){
            $file2 = new File();
            $file2->setType(File::TYPE_INVOICE);
            if($data['recursion'] ==Invoice::RECURSION_MONTHLY){
                $file2->setName("{$data['number']} - H");
            }else{
                $file2->setName("{$invoice->getTypeString()} {$data['date']['month_n']}-{$data['date']['year']} #{$invoice->getId()} - R file2");
            }
            $file2->setWarrant($property->getWarrant());
            /** @noinspection PhpUnhandledExceptionInspection */
            $file2->setDriveId($this->drive->addFile($file2->getName(), $filePath2, File::TYPE_INVOICE, $property->getWarrant()->getId()));
            $this->manager->persist($file2);
            $io->note("Fichier honoraire recrée ".$filePath2);
        }
        //$invoice->setFile($file);
        $invoice2->setNumber($data['number_int']);
        
        if($cond_r_n){
            $invoice2->setFile($file);
        }
        if($cond_h_n){
            $invoice2->setFile2($file2);
        }
        $invoice2->setDate(new DateTime());
        $invoice2->setData($data);
        $invoice2->setProperty($property);
       
            $this->manager->persist($invoice2);
            $this->manager->persist($invoice);

            if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    $message = (new Swift_Message($invoice->getMailSubject()))
                        ->setFrom($this->mail_from)
                        ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                        ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                        ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                        ->attach(Swift_Attachment::fromPath($filePath));
                       
                }else{
                    $message = (new Swift_Message($invoice->getMailSubject()))
                        ->setFrom($this->mail_from)
                        ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                        ->setTo($invoice->getProperty()->getMail1())
                        ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
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
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ->attach(Swift_Attachment::fromPath($filePath2));
                               
                        }
                        //envoyer la rente au buyer /acquereur/acheteur
                        if($cond_r_n){
                            if($invoice->getProperty()->getDebirentierDifferent()){
                                $mailTarget_r=$invoice->getProperty()->getEmailDebirentier();
                                $nomTarget_r=$invoice->getProperty()->getNomDebirentier().' '.$invoice->getProperty()->getPrenomDebirentier();
                               
                            }else{
                                $mailTarget_r=$invoice->getProperty()->getWarrant()->getMail1();
                                $nomTarget_r=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                              
                            }
                            $message2 = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->mail_from)
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($mailTarget_r)
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ->attach(Swift_Attachment::fromPath($filePath));
                              
                        }
                    }else{
                        //si mandat acquereur                                
                        $io->note($invoice->getProperty()->getId()." mandat acquereur");
                        $message = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->mail_from)
                                ->setBcc(["agonsallo@gmail.com", $this->mail_from])
                                ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['numero'=> "{$data['old_number']}",'type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                ;
                        if($cond_h_n){
                            $message->attach(Swift_Attachment::fromPath($filePath2));
                             
                        }
                        if($cond_r_n){
                            $message->attach(Swift_Attachment::fromPath($filePath));
                             
                        }
                    }
            }
            if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    if($cond_h_n){
                        if(!empty($invoice->getMailCc())) {
                            $message1->setCc($invoice->getMailCc());
                        }
        
                        if (!$this->areMailsDisabled() && $this->mailer->send($message1)) {
                            $invoice->setStatus(Invoice::STATUS_SENT);
                            $io->note("mail mandat vendeur regeneré envoyé avec les honoraires aux mandants ".$invoice->getProperty()->getWarrant()->getMail1()." et ".$invoice->getMailCc());
                        } else {
                            $invoice->setStatus(Invoice::STATUS_UNSENT);
                        }
                    }
                    if($cond_r_n){
                        if (!$this->areMailsDisabled() && $this->mailer->send($message2)) {
                            $invoice->setStatus(Invoice::STATUS_SENT);
                            $io->note("mail mandat vendeur regeneré envoyé avec la rente au buyer /acquereur/acheteur ".$invoice->getProperty()->getBuyerMail1());
                        } else {
                            $invoice->setStatus(Invoice::STATUS_UNSENT);
                        }
                    }
                    
                    
            }else if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() != Warrant::TYPE_SELLERS){
                if($cond_h_n || $cond_r_n){
                    if(!empty($invoice->getMailCc())) {
                        $message->setCc($invoice->getMailCc());
                    }
    
                    if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                        $invoice->setStatus(Invoice::STATUS_SENT);
                        $io->note("mail envoyé ");
                    } else {
                        $invoice->setStatus(Invoice::STATUS_UNSENT);
                    }
                }    
                
              }else if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                    if(!empty($invoice->getMailCc())) {
                        $message->setCc($invoice->getMailCc());
                    }
    
                    if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                        $invoice->setStatus(Invoice::STATUS_SENT);
                        $io->note("mail envoyé ");
                    } else {
                        $invoice->setStatus(Invoice::STATUS_UNSENT);
                    }
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
