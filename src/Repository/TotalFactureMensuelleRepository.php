<?php

namespace App\Repository;

use App\Entity\TotalFactureMensuelle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

use \DateTime;

/**
 * @method TotalFactureMensuelle|null find($id, $lockMode = null, $lockVersion = null)
 * @method TotalFactureMensuelle|null findOneBy(array $criteria, array $orderBy = null)
 * @method TotalFactureMensuelle[]    findAll()
 * @method TotalFactureMensuelle[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TotalFactureMensuelleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TotalFactureMensuelle::class);
    }

   
}
