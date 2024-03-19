<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findAllOrdered()
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findDateOrderedDesc()
    {
        return $this->createQueryBuilder('n')
            ->Where('n.status = 1')
            ->orderBy('n.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findDateOrderedAsc()
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findExpired()
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.expiry < :date')
            ->setParameter('date', new \DateTime('tomorrow midnight'))
            ->getQuery()
            ->getResult();
    }

    public function findProperty($type, $property)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->setParameter('type', $type)
            ->andWhere('n.property = :property')
            ->setParameter('property', $property)
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return Notification[] Returns an array of Notification objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Notification
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
