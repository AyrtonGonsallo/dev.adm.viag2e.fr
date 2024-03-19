<?php

namespace App\Repository;

use App\Entity\FactureMensuelle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

use \DateTime;

/**
 * @method FactureMensuelle|null find($id, $lockMode = null, $lockVersion = null)
 * @method FactureMensuelle|null findOneBy(array $criteria, array $orderBy = null)
 * @method FactureMensuelle[]    findAll()
 * @method FactureMensuelle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FactureMensuelleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactureMensuelle::class);
    }

   
}
