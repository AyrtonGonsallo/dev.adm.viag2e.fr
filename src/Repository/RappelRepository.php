<?php

namespace App\Repository;

use App\Entity\Rappel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Rappel|null find($id, $lockMode = null, $lockVersion = null)
 * @method Rappel|null findOneBy(array $criteria, array $orderBy = null)
 * @method Rappel[]    findAll()
 * @method Rappel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RappelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rappel::class);
    }

    public function findAllOrdered()
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

  /*
    public function findValidToday(){
        si ce mois on a ajouté
         (id, type, texte, date, expiry, nextdisplay, status)
          (11, '1', 'Transmission éléments comptables', '2026-01-27', '10', '2026-01-31', 0), // lors de l'ajout on met dernier jhour de ce mois, et il apparrait si date du jour < next display comme ca apres avoir coche le rappel on mettra 2026-02-28 dernier jour du mois suivant comme next display
          (13, '2', 'Prélever les honoraires trimestriels', '2026-01-27', '05', '2026-03-31', 0), // lors de l'ajout on met dernier jhour de ce trimestre, et il apparrait si on est en janv, avril, juill, oct et  next display est dans ce trimestre comme ca apres avoir coche le rappel on mettra 2026-06-31 dernier jour du trimestre suivant comme next display
          (14, '3', 'Poster les FA avances copro', '2026-01-27', '25', '2026-03-31', 0), // lors de l'ajout on met dernier jhour de ce trimestre, et il apparrait si on est en mars juin, sept, déc et  next display est dans ce trimestre comme ca apres avoir coche le rappel on mettra 2026-06-31 dernier jour du trimestre suivant comme next display
           (16, '4', 'Facturer les NP (nue propriété - pas de rente)', '2026-01-27', '15-01', '2026-12-31', 0);  // lors de l'ajout on met dernier jhour de cette annee, et il apparrait si   next display est dans cette anee comme ca apres avoir coche le rappel on mettra 2027-06-27 dernier jour de lannee suivante comme next display
           

        return $result;
    }
        */

    public function findValidToday()
{
    $today = new \DateTime();
    $month = (int)$today->format('n');
    $year  = (int)$today->format('Y');
    // trimestre courant (1 à 4)
    $quarter = (int) ceil($month / 3);
    $now = new \DateTime();

    // début et fin du trimestre courant
    $quarterStart = new \DateTime(sprintf('%d-%02d-01',
        $now->format('Y'),
        ($quarter - 1) * 3 + 1
    ));

    $quarterEnd = (clone $quarterStart)
        ->modify('+3 months')
        ->modify('-1 day');

    $rappels = $this->createQueryBuilder('r')
        ->where('r.status = 0')
        ->andWhere('r.nextdisplay >= :today')
        ->setParameter('today', $today->format('Y-m-d'))
        ->orderBy('r.id', 'DESC')
        ->getQuery()
        ->getResult();

    $result = [];

    foreach ($rappels as $rappel) {
        $type = $rappel->getType();
        $next = $rappel->getNextdisplay();

        switch ($type) {

            case Rappel::REMIND_TYPE_MONTH:
                // visible tout le mois
                if (
                (int)$next->format('n') === $month &&
                (int)$next->format('Y') === $year
                ) {
                    $result[] = $rappel;
                }
                break;

            case Rappel::REMIND_TYPE_START_TRIMESTER:
                if (in_array($month, [1, 4, 7, 10]) && ($next >= $quarterStart && $next <= $quarterEnd) ) {
                    $result[] = $rappel;
                }
                break;

            case Rappel::REMIND_TYPE_END_TRIMESTER:
                if (in_array($month, [3, 6, 9, 12]) && ($next >= $quarterStart && $next <= $quarterEnd)) {
                    $result[] = $rappel;
                }
                break;

            case Rappel::REMIND_TYPE_YEAR:
                if ((int)$next->format('Y') === $year) {
                    $result[] = $rappel;
                }
                break;
        }
    }

    return $result;
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

    

}
