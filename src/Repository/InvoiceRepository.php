<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Property;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invoice[]    findAll()
 * @method Invoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findAllOrdered(int $page, int $max, array $data)
    {
        $query = $this->createQueryBuilder('i')
            ->orderBy('i.date', 'DESC');

        if(!empty($data['start']) && !empty($data['end'])) {
            $query
                ->andWhere('i.date >= :start')
                ->andWhere('i.date <= :end')
                ->setParameter('start', $data['start'])
                ->setParameter('end', $data['end']);
        }

        if(!empty($data['Category'])) {
            $query->andWhere('i.category = :category')->setParameter('category', $data['Category']);
        }

        if(!empty($data['Status'])) {
            $query->andWhere('i.status = :status')->setParameter('status', $data['Status']);
        }

        if(!empty($data['Type'])) {
            $query->andWhere('i.type = :type')->setParameter('type', $data['Type']);
        }

        if(!empty($data['generalSearch'])) {
            $query
                ->leftJoin('i.property', 'p')
                ->leftJoin('p.warrant', 'w')
                ->andWhere('i.number LIKE :search OR i.id LIKE :search OR IDENTITY(i.property) LIKE :search OR i.date LIKE :search 
                OR p.title LIKE :search OR p.lastname1 LIKE :search OR p.lastname2 LIKE :search OR p.buyer_lastname LIKE :search 
                OR w.lastname LIKE :search')
                ->setParameter('search', '%'.$data['generalSearch'].'%');
        }

        if(!empty($data['pending_only'])) {
            $query
                ->andWhere('i.type = :type')
                ->andWhere('i.status < :status')
                ->setParameter('type', Invoice::TYPE_NOTICE_EXPIRY)
                ->setParameter('status', Invoice::STATUS_PAYED);
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

    public function findTreatOrdered()
    {
        return $this->createQueryBuilder('i')
            ->where('i.type = :type')
            ->andWhere('i.status < :status')
            ->setParameter('type', Invoice::TYPE_NOTICE_EXPIRY)
            ->setParameter('status', Invoice::STATUS_PAYED)
            ->orderBy('i.date', 'DESC')
            ->getQuery()
            ->getResult()
            ;
    }

    public function findLast()
    {
        return $this->createQueryBuilder('i')
            ->where('i.type = :type')
            ->setParameter('type', Invoice::TYPE_NOTICE_EXPIRY)
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    public function findReceiptsToDo(int $max)
    {
        return $this->createQueryBuilder('i')
            ->where('i.status = :status')
            ->setParameter('status', Invoice::STATUS_PAYED)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }

    public function listByDate(DateTime $start, DateTime $end)
    {
        return $this->createQueryBuilder('i')
            ->where('i.date >= :start')
            ->andWhere('i.date <= :end')
            ->andWhere('i.type = :type')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('type', Invoice::TYPE_RECEIPT)
            ->getQuery()
            ->getResult();
    }

    public function listByDateNE(DateTime $start, DateTime $end)
    {
        return $this->createQueryBuilder('i')
            ->where('i.date >= :start')
            ->andWhere('i.date <= :end')
            ->andWhere('i.type = :type')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('type', Invoice::TYPE_NOTICE_EXPIRY)
            ->getQuery()
            ->getResult();
    }

    public function findForRecap(Property $property, int $year) {
        return $this->createQueryBuilder('i')
            ->andWhere('i.property = :property')
            ->andWhere('i.date >= :start')
            ->andWhere('i.date <= :end')
            ->andWhere('i.type = :type')
            ->setParameter('property', $property)
            ->setParameter('start', \DateTime::createFromFormat('Y-m-d',($year - 1).'-12-19'))
            ->setParameter('end', \DateTime::createFromFormat('Y-m-d',$year.'-12-01'))
            ->setParameter('type', Invoice::TYPE_NOTICE_EXPIRY)
            ->getQuery()
            ->getResult();
    }
}
