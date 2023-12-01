<?php

namespace App\Repository;

use App\Entity\BankExport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method BankExport|null find($id, $lockMode = null, $lockVersion = null)
 * @method BankExport|null findOneBy(array $criteria, array $orderBy = null)
 * @method BankExport[]    findAll()
 * @method BankExport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BankExportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankExport::class);
    }

    public function findAllOrdered()
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult()
            ;
    }

    // /**
    //  * @return BankExport[] Returns an array of BankExport objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?BankExport
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
