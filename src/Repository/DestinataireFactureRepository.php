<?php

namespace App\Repository;

use App\Entity\DestinataireFacture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

use \DateTime;

/**
 * @method DestinataireFacture|null find($id, $lockMode = null, $lockVersion = null)
 * @method DestinataireFacture|null findOneBy(array $criteria, array $orderBy = null)
 * @method DestinataireFacture[]    findAll()
 * @method DestinataireFacture[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DestinataireFactureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DestinataireFacture::class);
    }

   
}
