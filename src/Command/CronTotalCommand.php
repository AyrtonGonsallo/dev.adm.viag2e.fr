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
use App\Entity\TotalFactureMensuelle;
use App\Entity\Warrant;
use App\Entity\RevaluationHistory;
use App\Service\Bank;
use App\Service\DriveManager;
use App\Service\TotalGenerator;
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

class CronTotalCommand extends Command
{
    use LockableTrait;


    private const PROCESS_MAX = 5;
    protected static $defaultName = 'cron:total';

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

    public function __construct(ContainerInterface $container, ManagerRegistry $doctrine, Swift_Mailer $mailer, DriveManager $drive, TotalGenerator $generator, ParameterBagInterface $params)
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
		//$this->noMail = true;
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

         //$d = new DateTime('First day of last month');//novembre
        //$d = new DateTime('First day of this month');//decembre
        $d = new DateTime('First day of next month');//janvier

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
       
        if (date('d') >= 21) {
          //if (date('d') == 7) {   
            
            $io->comment('Processing total invoices');
			// Obtenez la date du 20 du mois actuel
			$dateLimite = date('Y-m-19');
			//$dateLimite = date('Y-04-20');

			// Créez une requête personnalisée pour récupérer les factures dont la date est supérieure au 20 de ce mois
			$queryBuilder = $this->manager->createQueryBuilder();
			$factureMensuelles = $queryBuilder->select('f')
			->from(FactureMensuelle::class, 'f')
			->join('f.property', 'p') // Rejoindre la table Property
			->where('f.date > :dateLimite')
			->andWhere('p.warrant = 16')
			->setParameter('dateLimite', $dateLimite)
			->orderBy('p.title', 'ASC') // Tri par la propriété "title" de l'objet Property
			->getQuery()
			->getResult();
				$this->generateTotal($io, $parameters, $factureMensuelles);
               
			
            
        }
    }
	
	 public function addInvoices(SymfonyStyle &$io, array $factureMensuelles)
    {
        
        
            foreach ($factureMensuelles as $fact) {
                if($fact->getType()==FactureMensuelle::TYPE_RENTE){
                    $queryBuilder = $this->manager->createQueryBuilder();
                    $invoice_data = $queryBuilder->select('i')
                    ->from(Invoice::class, 'i')
                    ->where('i.number = :num')
                    ->andWhere('i.file2 is Null')
                    //->andWhere("i.date > '2024-04-19'")
                    ->setParameter('num', substr($fact->getNumber(), -4))
                    ->orderBy('i.date', 'DESC')
                    ->getQuery()
                    ->getResult();
                    $invoice=$invoice_data[0];
                    $montant=round($invoice->getData()['property']['annuity'],2);
                    $fact->setInvoice($invoice);
                    $fact->setAmount($montant);
                    $io->note('Facture rente n '.substr($fact->getNumber(), -4).' correspondant a l\'invoice '.$invoice->getId().' de montant '.$montant);
                }else if($fact->getType()==FactureMensuelle::TYPE_HONORAIRES ){
                    $queryBuilder = $this->manager->createQueryBuilder();
                    $invoice_data = $queryBuilder->select('i')
                    ->from(Invoice::class, 'i')
                    ->where('i.number = :num')
                    ->andWhere('i.file is Null')
                    //->andWhere("i.date > '2024-04-19'")
                    ->setParameter('num', substr($fact->getNumber(), -4))
                    ->orderBy('i.date', 'DESC')
                    ->getQuery()
                    ->getResult();
                    $invoice=$invoice_data[0];
                    $montant=round($invoice->getData()['property']['honoraryRates'],2);
                    $fact->setInvoice($invoice);
                    $fact->setAmount($montant);

                    $io->note('Facture honoraires n '.substr($fact->getNumber(), -4).' correspondant a l\'invoice '.$invoice->getId().' de montant '.$montant);
                }
                $this->manager->persist($fact);
                
               
            }
            $this->manager->flush();
		
    }
       
    public function generateTotal(SymfonyStyle &$io, array $parameters, array $factureMensuelles)
    {
        try {   
			$somme_h=0;
            $somme_r=0;
            $date_h=null;
            $date_r=null;
            $dest_r=$this->manager->getRepository(DestinataireFacture::class)->findOneBy(['id' => 32]);
            $dest_h=$this->manager->getRepository(DestinataireFacture::class)->findOneBy(['id' => 18]);
            foreach ($factureMensuelles as $fact) {
                if($fact->getType()==FactureMensuelle::TYPE_RENTE){
                    $somme_r+=round($fact->getInvoice()->getData()['property']['annuity'], 2);
                    $date_r=utf8_encode(strftime("%B %Y",strtotime($fact->getDate()->format('Y-m-d H:i:s'). ' +1 month')));
                    $warrant_r = $fact->getProperty()->getWarrant();
                }else if($fact->getType()==FactureMensuelle::TYPE_HONORAIRES ){
                    $somme_h+=round($fact->getInvoice()->getData()['property']['honoraryRates'], 2);
                    $date_h=utf8_encode(strftime("%B %Y",strtotime($fact->getDate()->format('Y-m-d H:i:s'). ' +1 month')));
                    $warrant_h = $fact->getProperty()->getWarrant();
                }
               
            }
            $somme_h = round($somme_h, 2);
            $somme_r = round($somme_r, 2);
			  $d = new DateTime('First day of next month');
            if($somme_r>0){
                $filePath= $this->generator->generateFile($dest_r,$somme_r,$factureMensuelles, $parameters,$date_r,utf8_encode(strftime('%A %e %B %Y')));
                $totalFactureMensuelle= new TotalFactureMensuelle();
                $totalFactureMensuelle->setAmount($somme_r);
                $totalFactureMensuelle->setDestinataire($dest_r);
                $totalFactureMensuelle->setType(TotalFactureMensuelle ::TYPE_RENTE);

                $file = new File();
                $file->setType(File::TYPE_DOCUMENT);
                $file->setName("TOTAL_RENTES_{$dest_r->getName()}");
                $file->setWarrant($warrant_r);
                $file->setDriveId($this->drive->addFile($file->getName(), $filePath, File::TYPE_INVOICE, $warrant_r->getId()));
                $this->manager->persist($file);
                $totalFactureMensuelle->setFile($file);
                $this->manager->persist($totalFactureMensuelle);
                $this->manager->flush();
				$filePathExcel= $this->generator->generateExcelFile($factureMensuelles);
				$fileExcel = new File();
                $fileExcel->setType(File::TYPE_DOCUMENT);
				$fileExcel->setMime("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
                $fileExcel->setName("TOTAL_RENTES_{$dest_r->getName()}_Excel");
                $fileExcel->setWarrant($warrant_r);
                $fileExcel->setDriveId($this->drive->addFile($file->getName(), $filePathExcel, File::TYPE_INVOICE, $warrant_r->getId()));
                $this->manager->persist($fileExcel);
                $this->manager->flush();
            }
            if($somme_h>0){
                $filePath2= $this->generator->generateFile2($dest_h,$somme_h,$factureMensuelles, $parameters,$date_h,utf8_encode(strftime('%A %e %B %Y')));                $filePathExcel2= $this->generator->generateExcelFile2($factureMensuelles);
                $totalFactureMensuelle= new TotalFactureMensuelle();
                $totalFactureMensuelle->setAmount($somme_h);
                $totalFactureMensuelle->setDestinataire($dest_h);
                $totalFactureMensuelle->setType(TotalFactureMensuelle ::TYPE_HONORAIRES);

                $file2 = new File();
                $file2->setType(File::TYPE_DOCUMENT);
                $file2->setName("TOTAL_HONORAIRES_{$dest_h->getName()}");
                $file2->setWarrant($warrant_h);
                $file2->setDriveId($this->drive->addFile($file2->getName(), $filePath2, File::TYPE_INVOICE, $warrant_h->getId()));
                $this->manager->persist($file2);
                $totalFactureMensuelle->setFile($file2);
                $this->manager->persist($totalFactureMensuelle);
                $this->manager->flush();
				
				$fileExcel2 = new File();
                $fileExcel2->setType(File::TYPE_DOCUMENT);
				$fileExcel2->setMime("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
                $fileExcel2->setName("TOTAL_HONORAIRES_{$dest_h->getName()}_Excel");
                $fileExcel2->setWarrant($warrant_h);
                $fileExcel2->setDriveId($this->drive->addFile($fileExcel2->getName(), $filePathExcel2, File::TYPE_INVOICE, $warrant_h->getId()));
                $this->manager->persist($fileExcel2);
                $this->manager->flush();
            }
           


            if($somme_r>0){
                $message = (new Swift_Message("ADF ".$d->format('m/y')." OB2"))
                    ->setFrom($this->mail_from)
                    ->setBcc([$this->mail_from,"ayrtongonsalloheroku@gmail.com"])
                    ->setTo($dest_r->getEmail())
                    //->setTo( "ayrtongonsalloheroku@gmail.com")
                    ->setBody($this->twig->render('invoices/emails/total_r.twig', [ 'date' => "{$date_r}"]), 'text/html')
                    ->attach(Swift_Attachment::fromPath($filePath))
                    ->attach(Swift_Attachment::fromPath($filePathExcel));

                if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                    $io->note("mail somme rente envoyé avec la somme ".$somme_r." à ".$dest_r->getName()." à l'adresse ".$dest_r->getEmail());
                } else{
                    $io->note("erreur envoi message ");
                }
            }
            if($somme_h>0){
                $message2 = (new Swift_Message("ADF ".$d->format('m/y')."  OG2I"))
                    ->setFrom($this->mail_from)
                    ->setBcc([$this->mail_from,"ayrtongonsalloheroku@gmail.com"])
                    ->setTo($dest_h->getEmail())
                    //->setTo( "ayrtongonsalloheroku@gmail.com")
                    ->setBody($this->twig->render('invoices/emails/total_h.twig', [ 'date' => "{$date_h}"]), 'text/html')
                     ->attach(Swift_Attachment::fromPath($filePath2))
                    ->attach(Swift_Attachment::fromPath($filePathExcel2));
                   
                if (!$this->areMailsDisabled() && $this->mailer->send($message2)) {
                    $io->note("mail somme honoraires envoyé avec la somme ".$somme_h." à ".$dest_h->getName()." à l'adresse ".$dest_h->getEmail());
                } else{
                    $io->note("erreur envoi message ");
                }
            }

            

            
                  
         

            //@unlink($this->pdf_dir . $fileName);
        } catch (Exception $e) {
            $io->error($e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine());
        }
		
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
