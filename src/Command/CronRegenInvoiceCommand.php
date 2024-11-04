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

class CronRegenInvoiceCommand extends Command
{
    use LockableTrait;

    private const PROCESS_MAX = 50;
    protected static $defaultName = 'cron:regen-invoice';

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
            'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
            'current_month' => date('m'),
            'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
            'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
            'month_n'       => $d->format('m'),
            'year'          => $d->format('Y'),
        ];

        $last_number = $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_number']);
        $io->comment('jour: '.date('d').' date totale:'.date('Y-m-d H:i:s'));
        

       
        $id = $io->ask('Propriété à regénérer : ');

        if ($id) {
            // Quarterly invoices ce sont les charges de copro
            

            $io->comment('Processing invoices');
            $property = $this->manager
                ->getRepository(Property::class)
                ->findInvoiceToDo($id)[0];

            // $last_month = new DateTime('last day of last month');
            // fin charges de copro
            $done = 0;
            /** @var Property $property */
            if ($property) {
                

                /*
                $annuity          = ($property->getRevaluationIndex() > 0) ? $property->getRevaluationIndex() / $property->getInitialIndex() * $property->getInitialAmount() : $property->getInitialAmount();
                $honoraryRates    = ($property->hasHonorariesDisabled()) ? 0.0 : $annuity * $property->getHonoraryRates();
                $honoraryRatesTax = ($property->hasHonorariesDisabled()) ? 0.0 : $honoraryRates / (100 + $parameters['tva']) * $parameters['tva'];
                */
                //($property->honorary_rates_object)?(($property->getInitialAmount() * $property->honorary_rates_object->getValeur())/100):0.0,
                if($property->getIndexationOG2I() && $property->valeur_indice_ref_og2_i_object){
                    $startDate = \DateTime::createFromFormat('d-n-Y', "01-".date('m')."-".date('Y'));
                    $startDate->setTime(0, 0 ,0);
                    $endDate = \DateTime::createFromFormat('d-n-Y', "01-".(date('m')+1)."-".date('Y'));
                    $endDate->setTime(0, 0, 0);
                    $month_og2i=$property->valeur_indice_ref_og2_i_object->getDate()->format('m');
                    $endDate_og2i = \DateTime::createFromFormat('d-n-Y', "31-".$month_og2i."-".date('Y'));
                    $endDate_og2i->setTime(0, 0, 0);
                    if($property->getId()==109){
                        //recuperer 
                        $qb4=$this->manager->createQueryBuilder()
                        ->select("rh")
                        ->from('App\Entity\RevaluationHistory', 'rh')
                        ->where('rh.type LIKE :key and rh.date <= :end')
                        ->andWhere('rh.date like  :endmonth')
                        ->setParameter('key', 'OGI')
                        ->setParameter('end', $endDate_og2i)
                        ->setParameter('endmonth',  "%-%".$month_og2i."-%")
                            ->orderBy('rh.date', 'DESC');
                        $query4 = $qb4->getQuery();
                        // Execute Query
                    }else{
                        //recuperer 
                        $qb4=$this->manager->createQueryBuilder()
                        ->select("rh")
                        ->from('App\Entity\RevaluationHistory', 'rh')
                        ->where('rh.type LIKE :key and rh.date <= :end')
                        ->andWhere('rh.date like  :endmonth')
                        ->setParameter('key', 'OGI')
                        ->setParameter('end', $endDate_og2i)
                        ->setParameter('endmonth',  "%-%".$month_og2i."-%")
                            ->orderBy('rh.date', 'ASC');
                        $query4 = $qb4->getQuery();
                        // Execute Query
                    }
                    $indice_og2i_ma = $query4->getResult()[0]; 

                    $annuity_base=( $indice_og2i_ma->getValue()/$property->initial_index_object->getValue())* $property->getInitialAmount() ;
                    $annuity=$annuity_base*$property->valeur_indexation_normale/$property->valeur_indice_ref_og2_i_object->getValue();
                    $plaf=(1+($property->plafonnement_index_og2_i/100))*$annuity_base;
                    if($annuity<$plaf){
                        $annuity= $annuity;
                    }else{
                        $annuity=$plaf;
                    }
                    $honoraryRates    = ($property->hasHonorariesDisabled()) ? 0.0 : $annuity_base * $property->honorary_rates_object->getValeur()/100;
                    $honoraryRatesTax = ($property->hasHonorariesDisabled()) ? 0.0 : $honoraryRates / (100 + $parameters['tva']) * $parameters['tva'];
                    //$honoraryRates    +=$honoraryRatesTax;
                }else if(!$property->getIndexationOG2I()){
                    $annuity=$property->valeur_indexation_normale / $property->initial_index_object->getValue() * $property->getInitialAmount() ;
                    $honoraryRates    = ($property->hasHonorariesDisabled()) ? 0.0 : $annuity * $property->honorary_rates_object->getValeur()/100;
                    $honoraryRatesTax = ($property->hasHonorariesDisabled()) ? 0.0 : $honoraryRates / (100 + $parameters['tva']) * $parameters['tva'];
                    //$honoraryRates    +=$honoraryRatesTax;
                }else{
                    $annuity          = ($property->getRevaluationIndex() > 0) ? $property->getRevaluationIndex() / $property->getInitialIndex() * $property->getInitialAmount() : $property->getInitialAmount();
                    $honoraryRates    = ($property->hasHonorariesDisabled()) ? 0.0 : $annuity * $property->honorary_rates_object->getValeur()/100;
                    $honoraryRatesTax = ($property->hasHonorariesDisabled()) ? 0.0 : $honoraryRates / (100 + $parameters['tva']) * $parameters['tva'];
                    //$honoraryRates    +=$honoraryRatesTax;
                }
                

                if ($annuity <= 0) {
                    $io->note("Skipping property {$property->getId()}, annuity = 0");
                    //continue;
                }

                if($property->getWarrant()->getType() === Warrant::TYPE_SELLERS) {
                    $data_full = [
                        'date'       => $this->date,
                        'type'       => Invoice::TYPE_NOTICE_EXPIRY,
                        'recursion'  => Invoice::RECURSION_MONTHLY,
                        'tva'        => $parameters['tva'],
                        'property'   => [
                            'id'         => $property->getId(),
                            'firstname'  => $property->getFirstname1(),
                            'lastname'   => $property->getLastname1(),
                            'firstname2' => $property->getFirstname2(),
                            'lastname2'  => $property->getLastname2(),
                            'address'    => formatter_adresse($property->getGoodAddress()),
                            'postalcode' => $property->getPostalCode(),
                            'city'       => $property->getCity(),
                            'is_og2i'       => $property->getClauseOG2I(),
                            'annuity'          => $annuity,
                            'honoraryRates'    => $honoraryRates,
                            'honoraryRatesTax' => $honoraryRatesTax,
                        ],
                        'buyer'    =>null,
                        'warrant'    => [
                            'id'         => $property->getWarrant()->getId(),
                            'type'       => $property->getWarrant()->getType(),
                            'firstname'  => $property->getWarrant()->getFirstname(),
                            'lastname'   => $property->getWarrant()->getLastname(),
                            'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                            'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                            'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                        ]
                    ];

                    
                        $number = $last_number->getValue() + ($this->isDryRun() ? $i : 1);

                        $data = $data_full;
                        $data['number'] = Invoice::formatNumber($number, Invoice::TYPE_NOTICE_EXPIRY);
                        $data['number_int'] = $number;

                       
                            $data['property']['honoraryRates'] = $honoraryRates;
                            $data['property']['honoraryRatesTax'] = $honoraryRatesTax;
                            $data['seller'] = [
                                'firstname'  => $property->getWarrant()->getFirstname(),
                                'lastname'   => $property->getWarrant()->getLastname(),
                            ];
                            $data['buyer'] = [
                                'firstname'  => $property->getBuyerFirstname(),
                                'lastname'   => $property->getBuyerLastname(),
                                'address'    => $property->getBuyerAddress(),
                                'postalcode' => $property->getBuyerPostalCode(),
                                'city'       => $property->getBuyerCity(),
                            ];
                    
                            $data['property']['annuity'] = $annuity;
                            $data["debirentier"]= null;
                            $data["debirentier_different"]= null;
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
                        if (!$this->isDryRun()) {
                            $last_number->setValue($number);
                        }

                        if (($data['property']['honoraryRates'] > 0) || ( $data['property']['annuity'] > 0)) {
                            $this->generateInvoice($io, $data, $parameters, $property);

                            $io->note("Invoice ({$data['type']}) generated for id {$property->getId()} with annuity {$data['property']['annuity']} and honorary {$data['property']['honoraryRates']}");
                        }
                        else {
                            $io->note("Invoice skipped ({$data['type']} ) for id {$property->getId()}, amount was 0");
                        }
                    

                   
                    $this->manager->flush();
                }
                else {
                    $number = $last_number->getValue() + 1;

                    $data = [
                        'date'       => $this->date,
                        'type'       => Invoice::TYPE_NOTICE_EXPIRY,
                        'recursion'  => Invoice::RECURSION_MONTHLY,
                        'number'     => Invoice::formatNumber($number, Invoice::TYPE_NOTICE_EXPIRY),
                        'number_int' => $number,
                        'amount'=> $property->getInitialAmount(),
                        'montantht'    => ($property->honorary_rates_object)?(($property->getInitialAmount() * $property->honorary_rates_object->getValeur())/100):0.0,
                        'tva'        => $parameters['tva'],
                        'property'   => [
                            'id'         => $property->getId(),
                            'firstname'  => $property->getFirstname1(),
                            'lastname'   => $property->getLastname1(),
                            'firstname2' => $property->getFirstname2(),
                            'lastname2'  => $property->getLastname2(),
                            'address'    => formatter_adresse($property->getGoodAddress()),
                            'postalcode' => $property->getPostalCode(),
                            'is_og2i'       => $property->getClauseOG2I(),
                            'city'       => $property->getCity(),
                            'annuity'          => $annuity,
                            'honoraryRates'    => $honoraryRates,
                            'honoraryRatesTax' => $honoraryRatesTax,
                        ],
                        'debirentier'          => null,
                        'debirentier_different'          => null,
                        'warrant'    => [
                            'id'         => $property->getWarrant()->getId(),
                            'type'       => $property->getWarrant()->getType(),
                            'firstname'  => $property->getWarrant()->getFirstname(),
                            'lastname'   => $property->getWarrant()->getLastname(),
                            'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                            'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                            'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                        ]
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
                    if (!$this->isDryRun()) {
                        $last_number->setValue($number);
                    }

                    $this->generateInvoice($io, $data, $parameters, $property);

                   
                    $this->manager->flush();

                    $io->note("Invoice ({$data['type']}) generated for id {$property->getId()}");
                }
                $done++;
            }
        }

        
        $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'last_cron'])->setValue(date("Y-m-d H:i:s"));
        if (!$this->isDryRun()) {
            $this->manager->persist(new Cron(Cron::TYPE_DAILY, microtime(true) - $start));
        }

        $this->manager->flush();

        $io->success('Job finished.');
        $this->release();

        return 0;
    }

    public function generateInvoice(SymfonyStyle &$io, array $data, array $parameters, Property $property, int $category = Invoice::CATEGORY_ANNUITY)
    {
        try {   
			//$data['date']["month"]=utf8_decode($data['date']["month"]);
            if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                $io->note("quaterly files trying to be created ");

            }  
            $filePath = $this->generator->generateFile($data, $parameters);
            if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                $filePath2= -1;
            }else{
                $filePath2= $this->generator->generateFile2($data, $parameters);

            }
            if ($this->isDryRun()) {
                return;
            }
            
            if ($this->isDryRun()) {
                return;
            }

            $invoice = new Invoice();
            $invoice->setCategory($category);
            $invoice->setType($data['type']);
            $invoice2 = new Invoice();
            $invoice2->setCategory($category);
            $invoice2->setType($data['type']);
            

            $cond_h_n=($filePath2 != -1)?true:false; //honoraires non nuls ?
            $cond_r_n=($filePath != -1)?true:false; //rente non nulle ?
           
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
            }
			//$invoice->setFile($file);
            $invoice->setNumber($data['number_int']);
            $invoice2->setNumber($data['number_int']);
            $data_h=$data;
            $data_r=$data;
            $data_h["amount"]=0;
            $data_h['property']['annuity']=0;
            $data_r['property']['honoraryRates']=0;
            $data_r['property']['honoraryRatesTax']=0;
            $data_r["montantht"]=0;
            $invoice->setData($data_r);
            $invoice2->setData($data_h);
            if($cond_r_n){
                $invoice->setFile($file);
            }
            if($cond_h_n){
                $invoice2->setFile2($file2);
            }
            $invoice->setDate(new DateTime());
            $invoice->setProperty($property);
            $invoice2->setDate(new DateTime());
            $invoice2->setProperty($property);
            if($cond_r_n){
                $this->manager->persist($invoice);
            }
            if($cond_h_n){
                $this->manager->persist($invoice2);
            }
            if(!$cond_h_n && !$cond_r_n){
				if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){	
                $io->note("no file created ");
                }
            }
             if(!$cond_r_n && $cond_h_n){
                if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                 $io->note("Only honoraires created ");
                }
            }
             if(!$cond_h_n && $cond_r_n){
                if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                  $io->note("Only rente created ");
                }
            }
             if($cond_h_n && $cond_r_n){
                if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                  $io->note("Both rente and honoraires created ");
                }
                   
            }
            
            if ((!empty($data['separation_type']) && ($data['separation_type'] == Property::BUYERS_ANNUITY) && !empty($property->getBuyerMail1())) || !empty($property->getWarrant()->getMail1())) {
                
                $destinataire_address_r="";
                $destinataire_postalcode_r="";
                $destinataire_city_r="";
                $destinataire_mail_r="";
                $destinataire_name_r="";
                $destinataire_type_r=0;
                $destinataire_address_h="";
                $destinataire_postalcode_h="";
                $destinataire_city_h="";
                $destinataire_mail_h="";
                $destinataire_name_h="";
                $destinataire_type_h=0;
                if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                    if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                        $message = (new Swift_Message($invoice->getMailSubject()))
                            ->setFrom($this->mail_from)
                            ->setBcc($this->mail_from)
                            ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                            //->setTo("roquetigrinho@gmail.com")
                            ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                            ->attach(Swift_Attachment::fromPath($filePath));
                            $destinataire_mail_r=$invoice->getProperty()->getWarrant()->getMail1();
                            $destinataire_name_r=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                            $destinataire_type_r=DestinataireFacture::TYPE_MANDANT;
                            $destinataire_address_r=$invoice->getProperty()->getWarrant()->getAddress();
                            $destinataire_postalcode_r=$invoice->getProperty()->getWarrant()->getPostalCode();
                            $destinataire_city_r=$invoice->getProperty()->getWarrant()->getCity();
                    }else{
                        $message = (new Swift_Message($invoice->getMailSubject()))
                            ->setFrom($this->mail_from)
                            ->setBcc($this->mail_from)
                            ->setTo($invoice->getProperty()->getMail1())
                            //->setTo("roquetigrinho@gmail.com")
                            ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                            ->attach(Swift_Attachment::fromPath($filePath));
                            $destinataire_mail_r=$invoice->getProperty()->getMail1();
                            $destinataire_name_r=$invoice->getProperty()->getFirstname1()." ".$invoice->getProperty()->getLastname1();
                            $destinataire_type_r=DestinataireFacture::TYPE_CREDIRENTIER;
                            $destinataire_address_r=$invoice->getProperty()->getAddress();
                            $destinataire_postalcode_r=$invoice->getProperty()->getPostalCode();
                            $destinataire_city_r=$invoice->getProperty()->getCity();
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
                                    ->setBcc($this->mail_from)
                                    ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                    //->setTo("roquetigrinho@gmail.com")
                                    ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                    ->attach(Swift_Attachment::fromPath($filePath2));
                                    $destinataire_mail_h=$invoice->getProperty()->getWarrant()->getMail1();
                                    $destinataire_name_h=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                                    $destinataire_type_h=DestinataireFacture::TYPE_MANDANT;
                                    $destinataire_address_h=$invoice->getProperty()->getWarrant()->getAddress();
                                    $destinataire_postalcode_h=$invoice->getProperty()->getWarrant()->getPostalCode();
                                    $destinataire_city_h=$invoice->getProperty()->getWarrant()->getCity();
                            }
                            //envoyer la rente au buyer /acquereur/acheteur
                            if($cond_r_n){
                                if($invoice->getProperty()->getDebirentierDifferent()){
                                    $mailTarget_r=$invoice->getProperty()->getEmailDebirentier();
                                    $nomTarget_r=$invoice->getProperty()->getNomDebirentier().' '.$invoice->getProperty()->getPrenomDebirentier();
                                    $destinataire_type_r=DestinataireFacture::TYPE_DEBIRENTIER;
                                    $destinataire_address_r=$invoice->getProperty()->getAddresseDebirentier();
                                    $destinataire_postalcode_r=$invoice->getProperty()->getCodePostalDebirentier();
                                    $destinataire_city_r=$invoice->getProperty()->getVilleDebirentier();
                                }else{
                                    $mailTarget_r=$invoice->getProperty()->getWarrant()->getMail1();
                                    $nomTarget_r=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                                    $destinataire_type_r=DestinataireFacture::TYPE_MANDANT;
                                    $destinataire_address_r=$invoice->getProperty()->getWarrant()->getAddress();
                                    $destinataire_postalcode_r=$invoice->getProperty()->getWarrant()->getPostalCode();
                                    $destinataire_city_r=$invoice->getProperty()->getWarrant()->getCity();
                                }
                                $message2 = (new Swift_Message($invoice->getMailSubject()))
                                    ->setFrom($this->mail_from)
                                    ->setBcc($this->mail_from)
                                    //->setTo("roquetigrinho@gmail.com")
                                    ->setTo($mailTarget_r)
                                    ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html')
                                    ->attach(Swift_Attachment::fromPath($filePath));
                                    $destinataire_mail_r=$mailTarget_r;
                                     $destinataire_name_r=$nomTarget_r;
                                     
                            }
                        }else{
                            //si mandat acquereur                                
                            $io->note($invoice->getProperty()->getId()." mandat acquereur");
                            
                                $message = (new Swift_Message($invoice->getMailSubject()))
                                    ->setFrom($this->mail_from)
                                    ->setBcc($this->mail_from)
                                    ->setTo($invoice->getProperty()->getWarrant()->getMail1())
                                    //->setTo("roquetigrinho@gmail.com")
                                    ->setBody($this->twig->render('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                                    if($cond_r_n){
                                        $message->attach(Swift_Attachment::fromPath($filePath));
                                    }
                                    if($cond_h_n){
                                        $message->attach(Swift_Attachment::fromPath($filePath2));
                                    }
                                    $destinataire_mail_r=$invoice->getProperty()->getWarrant()->getMail1();
                                    $destinataire_name_r=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                                    $destinataire_type_r=DestinataireFacture::TYPE_MANDANT;
                                    $destinataire_address_r=$invoice->getProperty()->getWarrant()->getAddress();
                                    $destinataire_postalcode_r=$invoice->getProperty()->getWarrant()->getPostalCode();
                                    $destinataire_city_r=$invoice->getProperty()->getWarrant()->getCity();
                                    $destinataire_mail_h=$invoice->getProperty()->getWarrant()->getMail1();
                                    $destinataire_name_h=$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname();
                                    $destinataire_type_h=DestinataireFacture::TYPE_MANDANT;
                                    $destinataire_address_h=$invoice->getProperty()->getWarrant()->getAddress();
                                    $destinataire_postalcode_h=$invoice->getProperty()->getWarrant()->getPostalCode();
                                    $destinataire_city_h=$invoice->getProperty()->getWarrant()->getCity();
                            
                        }
                  }
                  if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                        if($cond_h_n){
                            if(!empty($invoice->getMailCc())) {
                                $message1->setCc($invoice->getMailCc());
                            }
            
                            if (!$this->areMailsDisabled() && $this->mailer->send($message1)) {
                                $invoice->setStatus(Invoice::STATUS_SENT);
                                $invoice2->setStatus(Invoice::STATUS_SENT);
                                $io->note("mail mandat vendeur envoyé avec les honoraires aux mandants ".$invoice->getProperty()->getWarrant()->getMail1()." et ".$invoice->getMailCc());
                            } else {
                                $invoice->setStatus(Invoice::STATUS_UNSENT);
                                $invoice2->setStatus(Invoice::STATUS_UNSENT);
                            }
                        }
                        if($cond_r_n){
                            if (!$this->areMailsDisabled() && $this->mailer->send($message2)) {
                                $invoice->setStatus(Invoice::STATUS_SENT);
                                $invoice2->setStatus(Invoice::STATUS_SENT);
                                $io->note("mail mandat vendeur envoyé avec la rente au buyer /acquereur/acheteur ".$invoice->getProperty()->getBuyerMail1());
                            } else {
                                $invoice->setStatus(Invoice::STATUS_UNSENT);
                                $invoice2->setStatus(Invoice::STATUS_UNSENT);
                            }
                        }
                        
                        
                  }else if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() != Warrant::TYPE_SELLERS){
                    if($cond_h_n || $cond_r_n){
                        if(!empty($invoice->getMailCc())) {
                            $message->setCc($invoice->getMailCc());
                        }
        
                        if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                            $invoice->setStatus(Invoice::STATUS_SENT);
                            $invoice2->setStatus(Invoice::STATUS_SENT);
                            $io->note("mail envoyé ");
                        } else {
                            $invoice->setStatus(Invoice::STATUS_UNSENT);
                            $invoice2->setStatus(Invoice::STATUS_UNSENT);
                        }
                    }    
                    
                  }else if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                        if(!empty($invoice->getMailCc())) {
                            $message->setCc($invoice->getMailCc());
                        }
        
                        if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                            $invoice->setStatus(Invoice::STATUS_SENT);
                            $invoice2->setStatus(Invoice::STATUS_SENT);
                            $io->note("mail envoyé ");
                        } else {
                            $invoice->setStatus(Invoice::STATUS_UNSENT);
                            $invoice2->setStatus(Invoice::STATUS_UNSENT);
                        }
                  }
                  if($cond_r_n && $invoice->getType()==Invoice::TYPE_NOTICE_EXPIRY){
                        $dest=$this->manager->getRepository(DestinataireFacture::class)->findOneBy(['name' => $destinataire_name_r]);
                        if($dest){
                            $destinataireFacture = $dest;
                            $io->note("Le destinataire ".$destinataire_mail_r." existe déja");
                        }else{
                            $destinataireFacture = new DestinataireFacture();
                            $io->note("Le destinataire ".$destinataire_mail_r." n'existe pas");
                        }
                        
                        $destinataireFacture->setEmail($destinataire_mail_r);
                        $destinataireFacture->setType($destinataire_type_r);
                        $destinataireFacture->setName($destinataire_name_r);
                        $destinataireFacture->setAddress($destinataire_address_r);
                        $destinataireFacture->setPostalCode($destinataire_postalcode_r);
                        $destinataireFacture->setCity($destinataire_city_r);
                        $factureMensuelle = new FactureMensuelle();
                        $factureMensuelle->setNumber($data['number']);
                        $factureMensuelle->setInvoice($invoice);
                        if($data['property']['annuity']){
                            $factureMensuelle->setAmount($data['property']['annuity']);
                           $factureMensuelle->setType(FactureMensuelle::TYPE_RENTE);
                       }else if($data['property']['condominiumFees']){
                                $factureMensuelle->setAmount($data['property']['condominiumFees']);
                           $factureMensuelle->setType(FactureMensuelle::TYPE_COPRO);
                       }else{
                           $factureMensuelle->setAmount(0);
                           $factureMensuelle->setType(FactureMensuelle::TYPE_RENTE);
                       }
                        $factureMensuelle->setDestinataire($destinataireFacture);
                       
                        $factureMensuelle->setProperty($invoice->getProperty());
                        $factureMensuelle->setFile($file);
                        $destinataireFacture->addFactureMensuelle($factureMensuelle);
                        $this->manager->persist($factureMensuelle);
                $this->manager->persist($destinataireFacture);
                $this->manager->flush();
                    }
                if($cond_h_n && $invoice->getType()==Invoice::TYPE_NOTICE_EXPIRY){
                    $dest=$this->manager->getRepository(DestinataireFacture::class)->findOneBy(['name' => $destinataire_name_h]);
                    if($dest){
                        $destinataireFacture2 = $dest;
                        $io->note("Le destinataire ".$destinataire_mail_h." existe déja");
                    }else{
                        $destinataireFacture2 = new DestinataireFacture();
                        $io->note("Le destinataire ".$destinataire_mail_h." n'existe pas");
                    }
                    $destinataireFacture2->setEmail($destinataire_mail_h);
                    $destinataireFacture2->setType($destinataire_type_h);
                    $destinataireFacture2->setName($destinataire_name_h);
                    $destinataireFacture2->setAddress($destinataire_address_h);
                    $destinataireFacture2->setPostalCode($destinataire_postalcode_h);
                    $destinataireFacture2->setCity($destinataire_city_h);
                    $factureMensuelle2 = new FactureMensuelle();
                    $factureMensuelle2-> setNumber($data['number']);
                    $factureMensuelle2->setInvoice($invoice2);
                    if($data['property']['honoraryRates']){
                        $factureMensuelle2->setAmount($data['property']['honoraryRates']);
                   }else if($data['property']['condominiumFees']){
                            $factureMensuelle2->setAmount($data['property']['condominiumFees']);
                   }else{
                       $factureMensuelle2->setAmount(0);
                   }
                    $factureMensuelle2->setDestinataire($destinataireFacture2);
                    $factureMensuelle2->setFile2($file2);
                    $factureMensuelle2->setType(FactureMensuelle::TYPE_HONORAIRES);
                    $factureMensuelle2->setProperty($invoice->getProperty());
                    $destinataireFacture2->addFactureMensuelle($factureMensuelle2);
                    $this->manager->persist($factureMensuelle2);
                $this->manager->persist($destinataireFacture2);
                $this->manager->flush();
                }
                
                if($cond_r_n){
                    $this->manager->persist($invoice);
                }
                if($cond_h_n){
                    $this->manager->persist($invoice2);
                }
            
        } else {
            $invoice->setStatus(Invoice::STATUS_UNSENT);
            $invoice2->setStatus(Invoice::STATUS_UNSENT);
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
