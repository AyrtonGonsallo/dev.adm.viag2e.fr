<?php

namespace App\Repository;

use App\Entity\Mail;
use App\Entity\Mailing;
use App\Entity\Warrant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Warrant|null find($id, $lockMode = null, $lockVersion = null)
 * @method Warrant|null findOneBy(array $criteria, array $orderBy = null)
 * @method Warrant[]    findAll()
 * @method Warrant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WarrantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Warrant::class);
    }

    public function findByType(int $type)
    {
        $query = $this->createQueryBuilder('w');

        if ($type > Mailing::TYPE_EVERYONE) {
            $query
                ->where('w.type = :type')
                ->setParameter('type', ($type == Mailing::TYPE_BUYERS) ? Warrant::TYPE_BUYERS : Warrant::TYPE_SELLERS);
        }

        return $query->orderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLast()
    {
        return $this->createQueryBuilder('w')
            ->where('w.active = :active')
            ->setParameter('active', true)
            ->orderBy('w.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    public function findList($type)
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.type = :type')
            ->setParameter('type', $type)
            ->orderBy('w.active', 'DESC')
            ->orderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

    // /**
    //  * @return Warrant[] Returns an array of Warrant objects
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
    public function findOneBySomeField($value): ?Warrant
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
