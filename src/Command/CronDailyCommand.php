<?php

namespace App\Command;

use App\Entity\Cron;
use App\Entity\Notification;
use App\Entity\Parameter;
use App\Entity\Property;
use App\Entity\PropertyComment;
use DateTime;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CronDailyCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'cron-daily';

    private const INVOICE_DELAY = 15;

    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var ObjectManager
     */
    private $manager;
    /**
     * @var ParameterBagInterface
     */
    private $params;
    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    public function __construct(ContainerInterface $container, ManagerRegistry $doctrine, UrlGeneratorInterface $router, ParameterBagInterface $params)
    {
        parent::__construct();

        $this->container = $container;
        $this->manager = $doctrine->getManager();
        $this->params = $params;
        $this->router = $router;
    }

    protected function configure()
    {
        $this
            ->setDescription('Daily cron command');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->lock()) {
            $io->error('The command is already running in another process.');
            return 1;
        }

        $start = microtime(true);

        $notifications = $this->manager
            ->getRepository(Notification::class)
            ->findBy(['expiry' => new DateTime()]);

        foreach ($notifications as $notification) {
            $this->manager->remove($notification);
        }

        $this->manager->flush();

        for ($i = 1; $i <= 3; $i++) {
            $properties = $this->manager
                ->getRepository(Property::class)
                ->findNextRevaluations(self::INVOICE_DELAY + $i);

            /** @var Property $property */
            foreach ($properties as $property) {
                if (!$property->getWarrant()->isActive()) {
                    continue;
                }

                $notification = new Notification();
                $notification->setType("revaluation");
                $notification->setData(['delay' => $i, 'route' => $this->router->generate('property_view', ['propertyId' => $property->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]);
                $notification->setProperty($property);
                if ($i != 1) {
                    $notification->setExpiry(new DateTime('tomorrow noon'));
                }
                $this->manager->persist($notification);
            }

            $this->manager->flush();
        }
        $properties_ExpiredExerciceCopro = $this->manager
                ->getRepository(Property::class)
                ->findExpiredExerciceCopro();
        foreach ($properties_ExpiredExerciceCopro as $property) {
            $io->note('Date de fin d\'exercice de Copro expiré sur '.$property->getTitle().' le '.$property->date_fin_exercice_copro->format('d-m-Y h:i:s').' ...');
            $notification = new Notification();
            $notification->setProperty(null);
            $notification->setType("copro_expire_sur_propriete");
            $notification->setData(['date'=> $property->date_fin_exercice_copro->format('d-m-Y h:m:s'), 'route' => $this->router->generate('property_view', ['propertyId' => $property->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]);
            $notification->setExpiry(new DateTime('tomorrow noon'));
            $notification->setProperty($property);
            $this->manager->persist($notification);
        }
        $now_date=new DateTime();
        if(intval($now_date->format('d')==13) ){
            $io->note('Vérification de l\'ipc mensuel le '.$now_date->format('d-m-Y h:i:s').' ...');
            $startDate = \DateTime::createFromFormat('d-n-Y', "01-".$now_date->format('m')."-".$now_date->format('Y'));
            $startDate->setTime(0, 0 ,0);

            $endDate = \DateTime::createFromFormat('d-n-Y', "31-".($now_date->format('m'))."-".$now_date->format('Y'));
            $endDate->setTime(0, 0, 0);
            //ogi
            $qb1=$this->manager->createQueryBuilder()
            ->select("rh")
            ->from('App\Entity\RevaluationHistory', 'rh')
            ->where('rh.type LIKE :key and rh.date BETWEEN :start AND :end')
            ->setParameter('key', 'OGI')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
                ->orderBy('rh.id', 'DESC');
            $query1 = $qb1->getQuery();
            // Execute Query
            $rhs_ogi = $query1->getResult();
            //urbains
            $qb2=$this->manager->createQueryBuilder()
            ->select("rh")
            ->from('App\Entity\RevaluationHistory', 'rh')
            ->where('rh.type LIKE :key and rh.date BETWEEN :start AND :end')
            ->setParameter('key', 'Urbains')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
                ->orderBy('rh.id', 'DESC');
            $query2 = $qb2->getQuery();
            // Execute Query
            $rhs_urbains = $query2->getResult();
            //menages
            $qb3=$this->manager->createQueryBuilder()
            ->select("rh")
            ->from('App\Entity\RevaluationHistory', 'rh')
            ->where('rh.type LIKE :key and rh.date BETWEEN :start AND :end')
            ->setParameter('key', 'Ménages')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
                ->orderBy('rh.id', 'DESC');
            $query3 = $qb3->getQuery();
            // Execute Query
            $rhs_menages = $query3->getResult();
            if($rhs_menages){
                $io->note('ipc menage renseigné pour ce mois');
            }else{
                $io->note('ipc menage non renseigné pour ce mois');
                $notification = new Notification();
                $notification->setProperty(null);
                $notification->setType("ipc-non-renseigne");
                $notification->setData(['message' => 'ménages','date'=> $now_date->format('m-Y')]);
                $notification->setExpiry(new DateTime('tomorrow noon'));
                $this->manager->persist($notification);
            }
            if($rhs_ogi){
                $io->note('ipc og2i renseigné pour ce mois');
            }else{
                $io->note('ipc og2i non renseigné pour ce mois');
                $notification = new Notification();
                $notification->setProperty(null);
                $notification->setType("ipc-non-renseigne");
                $notification->setData(['message' => 'og2i','date'=> $now_date->format('m-Y')]);
                $notification->setExpiry(new DateTime('tomorrow noon'));
                $this->manager->persist($notification);
            }
            if($rhs_urbains){
                $io->note('ipc urbain renseigné pour ce mois');
            }else{
                $io->note('ipc urbain non renseigné pour ce mois');
                $notification = new Notification();
                $notification->setProperty(null);
                $notification->setType("ipc-non-renseigne");
                $notification->setData(['message' => 'urbains','date'=> $now_date->format('m-Y')]);
                $notification->setExpiry(new DateTime('tomorrow noon'));
                $this->manager->persist($notification);
            }
        }
        $io->note('Checking property comments at '.$now_date->format('d-m-Y h:i:s').' ...');
        $comments = $this->manager
        ->getRepository(PropertyComment::class)
        ->findAll();
        foreach ($comments as $comment) {
            if( ($comment->getDateButoir()->getTimestamp()- $now_date->getTimestamp()) < 0){
                //$comment->getDateButoir() < $now_date
                $p=$comment->getProperty();
                $io->note('Property comment: '.$comment->getMessage().' expiring the '.$comment->getDateButoir()->format('d-m-Y h:m:s').' added the '.$comment->getDate()->format('d-m-Y h:m:s').' is expired');
                $notification = new Notification();
                $notification->setProperty($p);
                $notification->setType("suivi-message-expiration");
                $notification->setData(['message' => $comment->getMessage(),'date'=> $comment->getDate()->format('d-m-Y h:m:s'), 'route' => $this->router->generate('property_view', ['propertyId' => $p->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]);
                $notification->setExpiry(new DateTime('tomorrow noon'));
                $this->manager->persist($notification);
            }else if( ($comment->getDateButoir()->getTimestamp()- $now_date->getTimestamp()) <86400 ){
                $p=$comment->getProperty();
                $io->note('Property comment: '.$comment->getMessage().' expiring the '.$comment->getDateButoir()->format('d-m-Y h:m:s').' added the '.$comment->getDate()->format('d-m-Y h:m:s').' will expire in less than 1 day');
                $notification = new Notification();
                $notification->setProperty($p);
                $notification->setType("suivi-message-expiration-warning");
                $notification->setData(['message' => $comment->getMessage(),'date'=> $comment->getDate()->format('d-m-Y'), 'route' => $this->router->generate('property_view', ['propertyId' => $p->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]);
                $notification->setExpiry(new DateTime('tomorrow noon'));
                $this->manager->persist($notification);
            }
        }

        $properties = $this->manager
            ->getRepository(Property::class)
            ->findTerminatingContracts();

        /** @var Property $property */
        foreach ($properties as $property) {
            if (!$property->getWarrant()->isActive()) {
                continue;
            }

            if($property->getEndDateManagement() < new DateTime()) {
                continue;
            }

            $notifications = $this->manager
                ->getRepository(Notification::class)
                ->findProperty('terminatingcontract', $property);

            foreach ($notifications as $notification) {
                $this->manager->remove($notification);
            }

            $notification = new Notification();
            $notification->setType("terminatingcontract");
            $notification->setData(['date' => $property->getEndDateManagement()->format('d-m-Y'), 'route' => $this->router->generate('property_view', ['propertyId' => $property->getId()], UrlGeneratorInterface::ABSOLUTE_URL)]);
            $notification->setProperty($property);
            $this->manager->persist($notification);
        }

        $this->manager->getRepository(Parameter::class)->findOneBy(['name' => 'last_cron_daily'])->setValue(date("Y-m-d H:i:s"));
        $this->manager->persist(new Cron(Cron::TYPE_DAILY, microtime(true) - $start));

        $this->manager->flush();

        $io->success('End.');
        return 0;
    }
}
