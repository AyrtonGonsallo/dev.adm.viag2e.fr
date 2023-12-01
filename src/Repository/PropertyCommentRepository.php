<?php

namespace App\Repository;
use App\Entity\Property;
use App\Entity\PropertyComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method PropertyComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method PropertyComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method PropertyComment[]    findAll()
 * @method PropertyComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PropertyCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyComment::class);
    }

    public function findGenerated(int $max)
    {
        return $this->createQueryBuilder('m')
            ->where('m.status = :generated')
            ->setParameter('generated', PropertyComment::STATUS_GENERATED)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }
    public function findByProperty(Property $property)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.property = :val')
            ->setParameter('val', $property)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    // /**
    //  * @return PropertyComment[] Returns an array of PropertyComment objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PropertyComment
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
