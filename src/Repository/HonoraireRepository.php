<?php

namespace App\Repository;

use App\Entity\Honoraire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Honoraire|null find($id, $lockMode = null, $lockVersion = null)
 * @method Honoraire|null findOneBy(array $criteria, array $orderBy = null)
 * @method Honoraire[]    findAll()
 * @method Honoraire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HonoraireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Honoraire::class);
    }

    // /**
    //  * @return Honoraire[] Returns an array of Honoraire objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setHonoraire('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Honoraire
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setHonoraire('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
