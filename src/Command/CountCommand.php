<?php

namespace App\Command;

use App\Entity\Parameter;
use App\Entity\Property;
use App\Entity\Warrant;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CountCommand extends Command
{
    protected static $defaultName = 'app:count';

    private $manager;

    protected function configure()
    {
        $this
            ->setDescription('Recount all');
    }

    public function __construct(ManagerRegistry $doctrine)
    {
        parent::__construct();

        $this->manager = $doctrine->getManager();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->note('Count ...');

        $counts = [];

        $counts[] = ['name' => 'count_warrants_b', 'value' => $this->manager->getRepository(Warrant::class)
            ->createQueryBuilder('w')
            ->select('count(w.id)')
            ->where('w.type = :type')
            ->andWhere('w.active = :true')
            ->setParameter('type', Warrant::TYPE_BUYERS)
            ->setParameter('true', true)
            ->getQuery()->getSingleScalarResult()];

        $counts[] = ['name' => 'count_warrants_s', 'value' => $this->manager->getRepository(Warrant::class)
            ->createQueryBuilder('w')
            ->select('count(w.id)')
            ->where('w.type = :type')
            ->andWhere('w.active = :true')
            ->setParameter('type', Warrant::TYPE_SELLERS)
            ->setParameter('true', true)
            ->getQuery()->getSingleScalarResult()];

        $count = 0;
        $properties = $this->manager->getRepository(Property::class)
            ->createQueryBuilder('p')
            ->where('p.active = :true')
            ->setParameter('true', true)
            ->getQuery()
            ->getResult();

        /** @var Property $property */
        foreach ($properties as $property) {
            if ($property->getWarrant()->isActive()) {
                $count++;
            }
        }

        $counts[] = ['name' => 'count_properties', 'value' => $count];

        $io->note('Saving ...');

        foreach ($counts as $count) {
            $io->writeln("{$count['name']} => {$count['value']}");
            $parameter = $this->manager->getRepository(Parameter::class)->findOneBy(['name' => $count['name']]);
            if (empty($parameter)) {
                $parameter = new Parameter();
                $parameter->setName($count['name']);
                $this->manager->persist($parameter);
            }
            $parameter->setValue($count['value']);
        }

        $this->manager->flush();
        $io->success('Completed.');

        return 0;
    }
}
