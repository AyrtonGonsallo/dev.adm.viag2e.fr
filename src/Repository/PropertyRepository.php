<?php

namespace App\Repository;

use App\Entity\Property;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

use \DateTime;

/**
 * @method Property|null find($id, $lockMode = null, $lockVersion = null)
 * @method Property|null findOneBy(array $criteria, array $orderBy = null)
 * @method Property[]    findAll()
 * @method Property[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PropertyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Property::class);
    }

    public function findInvoicesToDo(int $max)
    {
        return $this->createQueryBuilder('p')
            ->where('p.last_invoice < :date')
            //->where('p.last_invoice < :date OR p.last_receipt < p.last_invoice')
            ->andWhere('p.start_date_management < :last_month')
            ->andWhere('p.billing_disabled = false')
            ->andWhere('p.active = true')
            ->setParameter('date', new DateTime('last day of last month'))
            ->setParameter('last_month', new DateTime('last day of next month'))
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }

    public function findQuarterlyInvoicesToDo(int $max)
    {
        return $this->createQueryBuilder('p')
            ->where('p.last_quarterly_invoice < :date')
            ->andWhere('p.condominium_fees > 0')
            //->where('p.last_quarterly_invoice < :date OR p.last_receipt < p.last_invoice')
            ->andWhere('p.start_date_management < :last_month')
            ->andWhere('p.active = true')
            ->setParameter('date', new DateTime('last day of last month'))
            ->setParameter('last_month', new DateTime('last day of next month'))
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }


/*

pour faire les tests sur les charges de copro

    public function findQuarterlyInvoicesToDo(int $max)
    {// 15 83 24
        return $this->createQueryBuilder('p')
            ->where('p.id =:id')
            ->setParameter('id', 16)
            ->setMaxResults(0)
            ->getQuery()
            ->getResult();
    }

    pour faire les tests sur les rentes et honoraires

    public function findInvoicesToDo(int $max)
    {
        return $this->createQueryBuilder('p')
            ->where('p.id =:id')
            ->setParameter('id', 83)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }*/
    public function findLast()
    {
        return $this->createQueryBuilder('p')
            ->where('p.active = :active')
            ->setParameter('active', true)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    public function findNextEndings()
    {
        return $this->createQueryBuilder('p')
            ->where('p.active = :active')
            ->setParameter('active', true)
            ->andWhere('p.end_date_management <> :null')
            ->setParameter('null', null)
            ->orderBy('p.end_date_management', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    public function findNextRevaluations($days)
    {
        $date = new DateTime("+$days days");
        return $this->createQueryBuilder('p')
            ->where('p.revaluation_date = :date')
            ->andWhere('p.last_revaluation < :rdate')
            ->andWhere('p.active = true')
            ->setParameter('date', $date->format('d-m'))
            ->setParameter('rdate', new DateTime('-2 month'))
            ->getQuery()
            ->getResult();
    }

    public function findExpiredExerciceCopro()
    {
        $date = new DateTime();
        return $this->createQueryBuilder('p')
            ->where('p.date_fin_exercice_copro < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function findTerminatingContracts()
    {
        return $this->createQueryBuilder('p')
            ->where('p.end_date_management <= :date')
            ->andWhere('p.active = true')
            ->setParameter('date', new DateTime("+10 days"))
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return BuyerGood[] Returns an array of BuyerGood objects
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
    public function findOneBySomeField($value): ?BuyerGood
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
