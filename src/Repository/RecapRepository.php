<?php

namespace App\Repository;

use App\Entity\Recap;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Recap|null find($id, $lockMode = null, $lockVersion = null)
 * @method Recap|null findOneBy(array $criteria, array $orderBy = null)
 * @method Recap[]    findAll()
 * @method Recap[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recap::class);
    }

    // /**
    //  * @return Recap[] Returns an array of Recap objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Recap
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
