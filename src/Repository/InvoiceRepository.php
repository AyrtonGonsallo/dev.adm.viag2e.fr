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
    function get_category($val){
        if($val=="Tous"){
            return 6;
        }
        else if($val=="Rente"){
            return 0;
        }else if($val=="Frais de co-pro"){
            return 1;
        }else if($val=="Ordures ménagères"){
            return 2;
        }else if($val=="Manuelle"){
            return 3;
        }
        else if($val=="Avoir"){
            return 4;
        }
        else if($val=="Regule Manuelle"){
            return 5;
        }
    }



    public function findToSeparate(int $max){
        return $this->createQueryBuilder('i')
        ->where('i.file IS NOT NULL')
        ->andWhere('i.file2 IS NOT NULL')
        ->orderBy('i.id', 'DESC')
        ->setMaxResults($max)
        ->getQuery()
        ->getResult();
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

        if(!empty($data['month_concerned']) && $data['month_concerned']!="all" ) {
            $query
                ->andWhere('i.data like :month')
                ->setParameter('month', '%"month_n":"'.$data['month_concerned'].'%');
        }

        if(!empty($data['Category']) && $this->get_category($data['Category'])!=6) {
            $query->andWhere('i.category = :category')->setParameter('category', $this->get_category($data['Category']));
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
            ->andWhere('i.number >= :start')
        ->andWhere('i.number <= :end')
            ->setParameter('status', Invoice::STATUS_PAYED)
            ->setParameter('start', 5105)
            ->setParameter('end', 5207)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }
/*
    public function findReceiptsToDo(int $max)
    {
        return $this->createQueryBuilder('i')
            ->where('i.status = :status')
            ->setParameter('status', Invoice::STATUS_PAYED)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }
*/
    public function  findInvoicesToRegenerate(int $max)
    {
        return $this->createQueryBuilder('i')
        ->where('i.date >= :start')
        ->andWhere('i.date < :end')
        ->andWhere('(i.date_regeneration IS NULL OR i.date_regeneration > :today)')
        ->andWhere('i.type = :t')
        ->setParameter('t', 1)
        ->setParameter('start', "2024-01-08")
        ->setParameter('today',new DateTime('tomorrow') )
            ->setParameter('end', "2024-01-10")
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }
    public function  findQuittancesToRegenerate(int $max)
    {
        return $this->createQueryBuilder('i')
        ->where('i.number >= :start')
        ->andWhere('i.number <= :end')
        ->andWhere('(i.date_regeneration IS NULL OR i.date_regeneration > :today)')
        ->andWhere('i.type = :t')
        ->setParameter('t', 2)
        ->setParameter('start', 4882)
        ->setParameter('today',new DateTime('tomorrow') )
        ->setParameter('end', 5001)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }
    /*public function  findAvoirsTogenerate(int $max)
    {
        return $this->createQueryBuilder('i')
        ->where('i.number >= :start')
        ->andWhere('i.number <= :end')
        ->andWhere('(i.date_generation_avoir IS NULL OR i.date_generation_avoir > :today)')
        ->andWhere('i.type = :t')
        ->setParameter('start', 4882)
        ->setParameter('today', new DateTime('tomorrow'))
        ->setParameter('t', 2)
            ->setParameter('end', 5001)
            ->setMaxResults($max)
            ->getQuery()
            ->getResult();
    }*/
    public function findAvoirsTogenerate(int $max)
{
   // $numbers = [5591, 5592, 5588, 5589, 5590, 5581, 5582, 5584, 5585, 5586, 5587, 5681, 5790, 5791, 5682];
   
   $numbers = [5904];

    return $this->createQueryBuilder('i')
        ->where('i.number IN (:numbers)')
        ->andWhere('(i.date_generation_avoir IS NULL OR i.date_generation_avoir > :today)')
        ->andWhere('i.type = :t')
        ->setParameter('numbers', $numbers)
        ->setParameter('today', new \DateTime('tomorrow'))
        ->setParameter('t', 1)
        ->setMaxResults($max)
        ->getQuery()
        ->getResult();
}
    public function  findSimilarInvoice(String $numero)
    {
        return $this->createQueryBuilder('i')
        ->where('i.data like :data')
        ->andWhere('i.number >= :num')
        ->andWhere('i.category = :cat')
        ->setParameter('num', 5002)
        ->setParameter('cat', 4)
        ->setParameter('data', '%'.$numero.'%')
        ->orderBy('i.id', 'DESC')
        ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }
    public function  getLastPropertyInvoice(int $pid)
    {
        return $this->createQueryBuilder('i')
        ->where('i.property = :pid')
        ->andWhere('i.category = :cat')
        ->setParameter('cat', 0)
        ->setParameter('pid', $pid)
        ->orderBy('i.id', 'DESC')
        ->setMaxResults(1)
            ->getQuery()
            ->getResult();
    }
    public function  findSimilarInvoice2(String $numero)
    {
        return $this->createQueryBuilder('i')
        ->where('i.data like :data')
        ->andWhere('i.number >= :num')
        ->andWhere('i.category = :cat')
        ->setParameter('num', 5105)
        ->setParameter('cat', 0)
        ->setParameter('data', '%'.$numero.'%')
        ->orderBy('i.id', 'DESC')
        ->setMaxResults(1)
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

    public function listByDate2(DateTime $start, DateTime $end)
    {
        return $this->createQueryBuilder('i')
            ->where('i.date >= :start')
            ->andWhere('i.date <= :end')
            ->andWhere('i.type = :type or i.type = :type2')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('type', Invoice::TYPE_RECEIPT)
            ->setParameter('type2', Invoice::TYPE_AVOIR)
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
