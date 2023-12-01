<?php

namespace App\Repository;

use App\Entity\PendingInvoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method PendingInvoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method PendingInvoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method PendingInvoice[]    findAll()
 * @method PendingInvoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PendingInvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PendingInvoice::class);
    }

    // /**
    //  * @return PendingInvoice[] Returns an array of PendingInvoice objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PendingInvoice
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
