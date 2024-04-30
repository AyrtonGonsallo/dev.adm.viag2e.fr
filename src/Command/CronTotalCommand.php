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

        $d = new DateTime('First day of next month');

        $this->date = [
            'current_day'   => strftime('%A %e %B %Y'),
            'current_month' => date('m'),
            'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
            'month'         => strftime('%B', $d->getTimestamp()),
            'month_n'       => $d->format('m'),
            'year'          => $d->format('Y'),
        ];

        $last_number = $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_number']);
        $io->comment('jour: '.date('d').' date totale:'.date('Y-m-d H:i:s'));
        

      
        
        if (date('d') >= 21) {
            
            
            $io->comment('Processing total invoices');
			// Obtenez la date du 20 du mois actuel
        $dateLimite = date('Y-m-20');

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
                    $somme_r+=$fact->getAmount();
                    $date_r=utf8_encode(strftime("%B %Y",strtotime($fact->getDate()->format('Y-m-d H:i:s'). ' +1 month')));
                    $warrant_r = $fact->getProperty()->getWarrant();
                }else if($fact->getType()==FactureMensuelle::TYPE_HONORAIRES ){
                    $somme_h+=$fact->getAmount();
                    $date_h=utf8_encode(strftime("%B %Y",strtotime($fact->getDate()->format('Y-m-d H:i:s'). ' +1 month')));
                    $warrant_h = $fact->getProperty()->getWarrant();
                }
               
            }
            $somme_h = round($somme_h, 2);
            $somme_r = round($somme_r, 2);
            if($somme_r>0){
                $filePath= $this->generator->generateFile($dest_r,$somme_r,$factureMensuelles, $parameters,$date_r,strftime('%A %e %B %Y'));
                $totalFactureMensuelle= new TotalFactureMensuelle();
                $totalFactureMensuelle->setAmount($somme_r);
                $totalFactureMensuelle->setDestinataire($dest_r);
                $totalFactureMensuelle->setType(TotalFactureMensuelle ::TYPE_RENTE);

                $file = new File();
                $file->setType(File::TYPE_DOCUMENT);
                $file->setName("TOTAL_RENTES_{$dest_r->getName()}.pdf");
                $file->setWarrant($warrant_r);
                $file->setDriveId($this->drive->addFile($file->getName(), $filePath, File::TYPE_INVOICE, $warrant_r->getId()));
                $this->manager->persist($file);
                $totalFactureMensuelle->setFile($file);
                $this->manager->persist($totalFactureMensuelle);
                $this->manager->flush();
            }
            if($somme_h>0){
                $filePath2= $this->generator->generateFile2($dest_h,$somme_h,$factureMensuelles, $parameters,$date_h,strftime('%A %e %B %Y'));
                $totalFactureMensuelle= new TotalFactureMensuelle();
                $totalFactureMensuelle->setAmount($somme_h);
                $totalFactureMensuelle->setDestinataire($dest_h);
                $totalFactureMensuelle->setType(TotalFactureMensuelle ::TYPE_HONORAIRES);

                $file2 = new File();
                $file2->setType(File::TYPE_DOCUMENT);
                $file2->setName("TOTAL_HONORAIRES_{$dest_h->getName()}.pdf");
                $file2->setWarrant($warrant_h);
                $file2->setDriveId($this->drive->addFile($file2->getName(), $filePath2, File::TYPE_INVOICE, $warrant_h->getId()));
                $this->manager->persist($file2);
                $totalFactureMensuelle->setFile($file2);
                $this->manager->persist($totalFactureMensuelle);
                $this->manager->flush();
            }
            $d = new DateTime('First day of next month');


            if($somme_r>0){
                $message = (new Swift_Message("ADF ".$d->format('m/y')." OB2"))
                    ->setFrom($this->mail_from)
                    ->setBcc($this->mail_from)
                    //->setTo($dest_r->getEmail())
                    ->setTo( "roquetigrinho@gmail.com")
                    ->setBody($this->twig->render('invoices/emails/total_r.twig', [ 'date' => "{$date_r}"]), 'text/html')
                    ->attach(Swift_Attachment::fromPath($filePath));

                if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                    $io->note("mail somme rente envoyé avec la somme ".$somme_r." à ".$dest_r->getName()." à l'adresse ".$dest_r->getEmail());
                } else{
                    $io->note("erreur envoi message ");
                }
            }
            if($somme_h>0){
                $message2 = (new Swift_Message("ADF ".$d->format('m/y')."  OG2I"))
                    ->setFrom($this->mail_from)
                    ->setBcc($this->mail_from)
                    //->setTo($dest_h->getEmail())
                    ->setTo( "roquetigrinho@gmail.com")
                    ->setBody($this->twig->render('invoices/emails/total_h.twig', [ 'date' => "{$date_h}"]), 'text/html')
                    ->attach(Swift_Attachment::fromPath($filePath2));
                   
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
