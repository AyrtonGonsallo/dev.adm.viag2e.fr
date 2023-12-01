<?php

namespace App\Repository;

use App\Entity\RevaluationHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method RevaluationHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method RevaluationHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method RevaluationHistory[]    findAll()
 * @method RevaluationHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RevaluationHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RevaluationHistory::class);
    }

    // /**
    //  * @return RevaluationHistory[] Returns an array of RevaluationHistory objects
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
    public function findOneBySomeField($value): ?RevaluationHistory
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
