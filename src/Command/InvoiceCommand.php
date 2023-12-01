<?php

namespace App\Command;

use App\Entity\File;
use App\Entity\Invoice;
use App\Entity\Parameter;
use App\Service\DriveManager;
use App\Service\InvoiceGenerator;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class InvoiceCommand extends Command
{
    protected static $defaultName = 'app:invoice';

    private $container;
    private $manager;
    private $drive;
    private $params;
    private $twig;
    private $generator;

    private $pdf_dir;
    private $pdf_logo;

    public function __construct(ContainerInterface $container, ManagerRegistry $doctrine, DriveManager $drive, ParameterBagInterface $params, InvoiceGenerator $generator)
    {
        parent::__construct();

        $this->container = $container;
        $this->manager = $doctrine->getManager();
        $this->drive = $drive;
        $this->params = $params;
        $this->generator = $generator;
        $this->twig = $this->container->get('twig');

        $this->pdf_dir = $this->params->get('pdf_tmp_dir');
        $this->pdf_logo = $this->params->get('pdf_logo_path');
    }

    protected function configure()
    {
        $this
            ->setDescription('Command to manually regenerate an invoice')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $id = $io->ask('Invoice ID');

        $invoice = $this->manager
            ->getRepository(Invoice::class)
            ->find($id);

        if (empty($invoice)) {
            $io->error('Invoice not found');
            return 1;
        }

        $rep = $this->manager->getRepository(Parameter::class);
        $parameters = [
            'tva'        => !empty($invoice->getData()['tva']) ? $invoice->getData()['tva'] : 20,
            'footer'     => $rep->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $rep->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $rep->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $rep->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $rep->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $rep->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $rep->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];

        $data = $invoice->getData();
        //$data['property']['honoraryRatesTax'] = $data['property']['honoraryRates'] / (100 + $parameters['tva']) * $parameters['tva'];
        //$invoice->setData($data);

        $file = new File();
        $file->setType(File::TYPE_INVOICE);
        $file->setName("{$invoice->getTypeString()} {$data['date']['month_n']}-{$data['date']['year']} #{$invoice->getProperty()->getId()} - R");
        $file->setWarrant($invoice->getProperty()->getWarrant());
        /** @noinspection PhpUnhandledExceptionInspection */
        $file->setDriveId($this->drive->addFile($file->getName(), $this->generator->generateFile($data, $parameters), File::TYPE_INVOICE, $invoice->getProperty()->getWarrant()->getId()));
        $this->manager->persist($file);

        $invoice->setFile($file);

        $this->manager->flush();

        $io->note("Invoice {$invoice->getId()} completed");

        return 0;
    }
}
