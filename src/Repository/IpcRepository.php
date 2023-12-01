<?php

namespace App\Repository;

use App\Entity\RevaluationHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method RevaluationHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method RevaluationHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method RevaluationHistory[]    findAll()
 * @method RevaluationHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */

 class IpcRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RevaluationHistory::class);
    }
   
}