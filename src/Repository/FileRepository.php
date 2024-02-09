<?php

namespace App\Repository;

use App\Entity\File;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method File|null find($id, $lockMode = null, $lockVersion = null)
 * @method File|null findOneBy(array $criteria, array $orderBy = null)
 * @method File[]    findAll()
 * @method File[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    public function findAllOrdered(int $page, int $max, array $data)
    {
        $query = $this->createQueryBuilder('f')
            ->orderBy('f.date', 'DESC');
        $query->andWhere('not f.type = 2');
        if(!empty($data['start']) && !empty($data['end'])) {
            $query
                ->andWhere('f.date >= :start')
                ->andWhere('f.date <= :end')
                ->setParameter('start', $data['start'])
                ->setParameter('end', $data['end']);
        }

        

       

        if(!empty($data['Type'])) {
            $query->andWhere('f.type = :type')->setParameter('type', $data['Type']);
        }

        if(!empty($data['generalSearch'])) {
            $query
                
                ->andWhere('f.name LIKE :search ')
                ->setParameter('search', '%'.$data['generalSearch'].'%');
        }

        

        $query = $query->getQuery();

        $paginator = new Paginator($query);

        $total = count($paginator);
        $pagesCount = ceil($total / $max);

        $paginator
            ->getQuery()
            ->setFirstResult($max * ($page - 1))
            ->setMaxResults($max);

        return ['total' => $total, 'pagesCount' => $pagesCount, 'data' => $paginator];
    }
    // /**
    //  * @return File[] Returns an array of File objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?File
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
