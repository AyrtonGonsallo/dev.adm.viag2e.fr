<?php /** @noinspection DuplicatedCode */

namespace App\Command;

use App\Entity\File;
use App\Entity\Invoice;
use App\Entity\Parameter;
use App\Entity\PendingInvoice;
use App\Entity\Property;
use App\Entity\Recap;
use App\Service\DriveManager;
use App\Service\RecapGenerator;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use DateTime;

class RecapCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'app:recap';

    private $manager;
    private $drive;
    private $mailer;
    private $params;
    private $recapGenerator;
    private $twig;

    private $pdf_dir;
    private $mail_from;

    private $dryRun;
    private $noMail;

    private $recapGenerationType = Recap::GENERATION_TYPE_FULL;

    public function __construct(ContainerInterface $container, ManagerRegistry $doctrine, DriveManager $drive, Swift_Mailer $mailer, RecapGenerator $recapGenerator, ParameterBagInterface $params)
    {
        parent::__construct();

        $this->manager = $doctrine->getManager();

        $this->drive = $drive;
        $this->mailer = $mailer;
        $this->recapGenerator = $recapGenerator;
        $this->twig = $container->get('twig');

        $this->params = $params;
        $this->pdf_dir = $this->params->get('pdf_tmp_dir').'/recap';
        $this->mail_from = $this->params->get('mail_from');
    }

    protected function configure()
    {
        $this
            ->setDescription('Command used to send an annual recap')
            ->addOption('property', 'p', InputOption::VALUE_OPTIONAL, 'Only generate for one property')
            ->addOption('year', 'y', InputOption::VALUE_OPTIONAL, 'Only generate for one property')
            ->addOption('only-warrants', null, InputOption::VALUE_OPTIONAL, 'Only generate for some warrants')
            ->addOption('exclude-warrants', null, InputOption::VALUE_OPTIONAL, 'Exclude some warrants')
            ->addOption('no-mail', 'm', InputOption::VALUE_NONE, 'Disable mails')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Disable mails and save for testing purposes')
            ->addOption('distinct', '3', InputOption::VALUE_NONE, 'Generate one recap for each type (buyer, seller, honoraries)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        $distinctGeneration = $input->getOption('distinct');
        if ($distinctGeneration) {
            $this->recapGenerationType = Recap::GENERATION_TYPE_DISTINCT;
        }

        setlocale(LC_TIME, 'fr_FR.utf8', 'French', 'French_France'); // dates in french

        $io->comment('Clearing folder');
        $files = ($this->isDryRun()) ? glob($this->pdf_dir . '/dry/*') : glob($this->pdf_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        $id = (($input->getOption('property') !== false) && is_numeric($input->getOption('property'))) ? $input->getOption('property') : $io->ask('Property ID (0 for all)');
        $year = (($input->getOption('year') !== false) && is_numeric($input->getOption('year'))) ? $input->getOption('year') : $io->ask('Year (0 = '. (date('Y') - 1) .')');

        if(empty($year)) {
            $year = date('Y') - 1;
        }

        $year = intval($year);

        $excluded_warrants = [];
        if(!empty($input->getOption('exclude-warrants'))) {
            $warrant_id = explode(',', $input->getOption('exclude-warrants'));
            foreach ($warrant_id as $wid) {
                if(is_numeric($wid)) {
                    $excluded_warrants[] = $wid;
                }
            }
        }

        $only_warrants = [];
        if(!empty($input->getOption('only-warrants'))) {
            $warrant_id = explode(',', $input->getOption('only-warrants'));
            foreach ($warrant_id as $wid) {
                if(is_numeric($wid)) {
                    $only_warrants[] = $wid;
                }
            }
        }

        $properties = [];

        if(empty($id) || !is_numeric($id)) {
            $properties = $this->manager
                ->getRepository(Property::class)
                ->findAll();
        }
        else {
            $property = $this->manager->getRepository(Property::class)->find($id);
            if(empty($property)) {
                $io->error('Property not found.');
                return 1;
            }

            $properties[] = $property;
        }

        $parameters = [
            'footer'     => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];

        $d = new DateTime('First day of this month');

        $date = [
            'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
            'current_month' => date('m'),
            'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
            'month'         => strftime('%B', $d->getTimestamp()),
            'month_n'       => $d->format('m'),
            'year'          => $d->format('Y'),
        ];

        /** @var Property $property */
        foreach ($properties as $property) {
            $io->note('Processing property ' . $property->getId());

            if(!empty($only_warrants) && !in_array($property->getWarrant()->getId(), $only_warrants)) {
                $io->writeln('    Warrant not in list, skippking');
                continue;
            }

            if(!empty($excluded_warrants) && in_array($property->getWarrant()->getId(), $excluded_warrants)) {
                $io->writeln('    Warrant excluded list, skippking');
                continue;
            }

            /*if($property->isBillingDisabled()) {
                $io->writeln('    Billing disabled, skippking');
                continue;
            }*/

            $data = [
                'date'          => $date,

                'year'          => $year,

                'buyer' => [
                    'firstname'  => $property->getBuyerFirstname(),
                    'lastname'   => $property->getBuyerLastname(),
                    'address'    => $property->getBuyerAddress(),
                    'postalcode' => $property->getBuyerPostalCode(),
                    'city'       => $property->getBuyerCity(),
                    'mail'       => $property->getBuyerMail1(),
                    'mail_cc'    => $property->getBuyerMail2(),
                ],
                'property'   => [
                    'id'         => $property->getId(),
                    'firstname'  => $property->getFirstname1(),
                    'lastname'   => $property->getLastname1(),
                    'firstname2' => $property->getFirstname2(),
                    'lastname2'  => $property->getLastname2(),
                    'address'    => $property->getAddress(),
                    'postalcode' => $property->getPostalCode(),
                    'city'       => $property->getCity(),
                    'mail'       => $property->getMail1(),
                    'mail_cc'    => $property->getMail2(),

                    'title'      => $property->getTitle(),
                ],
                'warrant'    => [
                    'id'         => $property->getWarrant()->getId(),
                    'type'       => $property->getWarrant()->getType(),
                    'firstname'  => $property->getWarrant()->getFirstname(),
                    'lastname'   => $property->getWarrant()->getLastname(),
                    'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                    'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                    'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                    'mail'       => $property->getWarrant()->getMail1(),
                    'mail_cc'    => $property->getWarrant()->getMail2(),
                ]
            ];

            $invoices = $this->manager
                ->getRepository(Invoice::class)
                ->findForRecap($property, $year);

            if(empty($invoices)) {
                $io->writeln('    No invoices, skippking');
                continue;
            }

            $totals = [
                'difference'    => 0,
                'warrant'       => 0,
                'buyer'         => 0,
                'property'      => 0,
                'honoraries'    => 0,
            ];

            /** @var Invoice $invoice */
            foreach ($invoices as $invoice) {
                $inv_data = $invoice->getData();
                // OTP
                /*if(!empty($inv_data['target'])) {
                    if($inv_data['target'] == PendingInvoice::TARGET_WARRANT) {
                        $totals['warrant'] += round($inv_data['amount'], 2);
                        $totals['honoraries'] += round($inv_data['honoraryRates'], 2); // ?
                    }
                    elseif($inv_data['target'] == PendingInvoice::TARGET_BUYER) {
                        $totals['buyer'] += round($inv_data['amount'], 2);
                        $totals['honoraries'] += round($inv_data['honoraryRates'], 2); // ?
                    }
                    else {
                        $totals['property'] += round($inv_data['amount'], 2);
                        $totals['honoraries'] += round($inv_data['honoraryRates'], 2);
                    }
                }*/
                // Quaterly
                /*if(!empty($inv_data['property']['condominiumFees'])) {
                    $totals['property'] += round($inv_data['property']['condominiumFees'], 2);
                }*/
                // Annuities
                if(!empty($inv_data['property']['annuity'])) {
                    $totals['warrant'] += round($inv_data['property']['annuity'], 2);
                }
                if(!empty($inv_data['property']['honoraryRates'])) {
                    $totals['honoraries'] += round($inv_data['property']['honoraryRates'], 2); // ?
                }

                $io->writeln('    Invoice ' . $invoice->getId() . ' processed');
            }

            if(empty($totals['warrant']) && empty($totals['buyer']) && empty($totals['honoraries'])) {
                $io->writeln('    No amounts, skippking');
                continue;
            }

            $totals['cumulation']   = number_format($totals['warrant'] + $totals['honoraries'], 2, '.', ' ');
            $totals['difference']   = number_format($totals['warrant'] - $totals['honoraries'], 2, '.', ' ');
            $totals['warrant']      = number_format($totals['warrant'], 2, '.', ' ');
            $totals['buyer']        = number_format($totals['buyer'], 2, '.', ' ');
            //$totals['property']   = number_format($totals['property'], 2, '.', ' ');
            $totals['honoraries']   = number_format($totals['honoraries'], 2, '.', ' ');
            $data['totals']         = $totals;

            if ($this->recapGenerationType === Recap::GENERATION_TYPE_FULL) {
                $data['type'] = -1;

                $this->generateRecap($io, $data, $parameters, $property);
                $this->manager->flush();
            }
            else {
                for ($i = 1; $i <= 3; $i++) {
                    //if($i != 2) continue;
                    $data['type'] = $i;

                    $this->generateRecap($io, $data, $parameters, $property);
                    $this->manager->flush();
                }
            }

            sleep(1);
        }

        $io->success('Done in ' . number_format((microtime(true) - $start) / 1000, 2, '.', ' ') . 's !');
        return 0;
    }

    public function generateRecap(SymfonyStyle &$io, array $data, array $parameters, Property $property) {
        try {
            $filePath = $this->recapGenerator->generateFile($data, $parameters);

            if ($this->isDryRun()) {
                return;
            }

            $recap = new Recap();
            $recap->setProperty($property);
            $recap->setYear($data['year']);
            $recap->setType($data['type']);
            $recap->setData($data);

            $file = new File();
            $file->setType(File::TYPE_RECAP);
            $file->setName("[RECAP] {$recap->getFileTypeString()} {$data['year']} #{$property->getId()}");
            $file->setRecap($recap);
            $file->setWarrant($property->getWarrant());
            $file->setDriveId($this->drive->addFile($file->getName(), $filePath, File::TYPE_RECAP, $property->getWarrant()->getId()));
            $this->manager->persist($file);

            $recap->setFile($file);
            $this->manager->persist($recap);

            $io->writeln('        Sending ' . $data['type'] . '-'.$data['warrant']['type'].' to ' . $recap->getMailRecipient() . ' - ' . $recap->getMailCc());

            if (!empty($recap->getMailRecipient())) {
                $message = (new Swift_Message($recap->getMailSubject()))
                    ->setFrom($this->mail_from)
                    ->setBcc($this->mail_from)
                    ->setTo("roquetigrinho@gmail.com")
                    ->setBody($this->twig->render('invoices/emails/recap.twig', ['type' => $recap->getTypeString(), 'year' => $data['year']]), 'text/html')
                    ->attach(Swift_Attachment::fromPath($filePath));

                if(!empty($recap->getMailCc())) {
                    $message->setCc($recap->getMailCc());
                }

                if (!$this->areMailsDisabled() && $this->mailer->send($message)) {
                    $recap->setStatus(Recap::STATUS_SENT);
                } else {
                    $recap->setStatus(Recap::STATUS_UNSENT);
                }
            } else {
                $recap->setStatus(Recap::STATUS_UNSENT);
            }

        } catch (\Exception $e) {
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
