<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Parameter;
use App\Entity\Property;
use App\Entity\Warrant;
use App\Entity\Notification;
use App\Entity\Rappel;
use stdClass;
use DateTime;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    /**
     * @Route("/", name="dashboard")
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $now_date=new DateTime();
        $manager = $this->getDoctrine()->getManager();

        $invoices = $manager
            ->getRepository(Invoice::class)
            ->findLast();

        $properties = $manager
            ->getRepository(Property::class)
            ->findLast();

        $warrants = $manager
            ->getRepository(Warrant::class)
            ->findLast();

        $endings = $manager
            ->getRepository(Property::class)
            ->findNextEndings();


        $invoicesToPay = $manager
            ->getRepository(Invoice::class)
            ->findInvoicesToPay(15);

        $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("p")
        ->from('App\Entity\Property', 'p')
        ->where('p.date_fin_exercice_copro < :date')
        ->andWhere('p.active = 1')
        ->setParameter('date', $now_date)
            ->orderBy('p.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $copro_edings = $query->getResult();

        $qb2=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("p")
        ->from('App\Entity\Property', 'p')
        ->where('p.revaluation_date like :date')
        ->andWhere('p.active = 1')
        ->setParameter('date', '%-'.intval(date("m",strtotime('first day of +1 month'))).'%')
            ->orderBy('p.revaluation_date', 'ASC');
        $query2 = $qb2->getQuery();
        // Execute Query
        $next_revaluations = $query2->getResult();

        $qb3=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("p")
        ->from('App\Entity\Property', 'p')
        ->where('p.date_assurance_habitation < :date')
        ->andWhere('p.active = 1')
        ->setParameter('date', $now_date)
            ->orderBy('p.date_assurance_habitation', 'ASC');
        $query3 = $qb3->getQuery();
        // Execute Query
        $fin_assurances_habitation = $query3->getResult();

        $qb4=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("p")
        ->from('App\Entity\Property', 'p')
        ->where('p.date_chaudiere < :date')
        ->andWhere('p.active = 1')
        ->setParameter('date', $now_date)
            ->orderBy('p.date_chaudiere', 'ASC');
        $query4 = $qb4->getQuery();
        // Execute Query
        $fin_assurances_chaudiere = $query4->getResult();

        $qb5=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("p")
        ->from('App\Entity\Property', 'p')
        ->where('p.date_cheminee < :date')
        ->andWhere('p.active = 1')
        ->setParameter('date', $now_date)
            ->orderBy('p.date_cheminee', 'ASC');
        $query5 = $qb5->getQuery();
        // Execute Query
        $fin_assurances_cheminee = $query5->getResult();

        $qb2=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("p")
        ->from('App\Entity\Property', 'p')
        ->where('p.date_climatisation < :date')
        ->andWhere('p.active = 1')
        ->setParameter('date', $now_date)
            ->orderBy('p.date_climatisation', 'ASC');
        $query2 = $qb2->getQuery();
        // Execute Query
        $fin_assurances_clim_pompe = $query2->getResult();


        $em = $this->getDoctrine()->getManager();

        $now = new \DateTime();
        $oneMonthLater = (clone $now)->modify('+1 month');

        $qb6 = $em->createQueryBuilder()
            ->select('p')
            ->from('App\Entity\Property', 'p')
            ->where('p.end_date_management BETWEEN :now AND :limit')
            ->andWhere('p.active = 1')
            ->setParameter('now', $now)
            ->setParameter('limit', $oneMonthLater)
            ->orderBy('p.end_date_management', 'ASC');

        $query6 = $qb6->getQuery();

        // Execute Query
        $mandat_a_renouveller = $query6->getResult();

        
        $rappels_valides = $manager
            ->getRepository(Rappel::class)
            ->findValidToday();

        $counts = new stdClass();
        $counts->warrants_b = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_warrants_b'])->getValue();
        $counts->warrants_s = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_warrants_s'])->getValue();
        $counts->properties = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_properties'])->getValue();

        $defaultData = ['message' => 'Type your message here'];
        $notification_form = $this->createFormBuilder($defaultData)
        ->add('texte', TextareaType::class, array('required' => true,
            'attr' => array('cols' => '20', 'rows' => '2'),
        ))
        ->add('date_rappel', DateType::class, ['required' => true, 'format' => 'dd-MMM-yyyy', 'years' => range(date('Y'), date('Y')+10)])
        ->getForm();
            $notification_form->handleRequest($request);
            

        if ($notification_form->isSubmitted() && $request->request->has('notification_submit') && $notification_form->isValid()) {
            $data = $notification_form->getData();
            $notification = new Notification();
            $notification->setProperty(null);
            $notification->setType("notification-libre");
            $notification->setExpiry($data["date_rappel"]);
            $manager->persist($notification);
            $manager->flush();
            $this->addFlash('success', 'Notification ajoutée');
        }else if ($notification_form->isSubmitted() && $request->request->has('notification_submit') && ! $notification_form->isValid()) {
            $this->addFlash('danger', 'Une erreur a eu lieu pendant l\'ajout de la notification');
        }

        $defaultData2 = ['message' => 'Type your message here'];
        $rappel_form = $this->createFormBuilder($defaultData2)
        ->add('texte', TextType::class, array('required' => true,
            'attr' => array('placeholder' => 'Objet', ),
        ))
        ->add('type', ChoiceType::class, ['choices' => array_flip(Rappel::REMIND_TYPES), 'choice_translation_domain' => false])
        ->add('date_rappel_mensuel_trimestre', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(date('Y'), date('Y')+10),'data' => new \DateTime(),])
        ->add('date_rappel_annuel', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(date('Y'), date('Y')+10),'data' => new \DateTime(),])
        ->getForm();

            $rappel_form->handleRequest($request);

        if ($rappel_form->isSubmitted() && $request->request->has('rappel_submit') && $rappel_form->isValid()) {
            $data = $rappel_form->getData();
            $rappel = new Rappel();
            $rappel->setType($data["type"]);
            $rappel->setTexte($data["texte"]);
            // Récupérer l'objet DateTime (même si l'année est cachée)

            $now = new \DateTime();


            switch ($data['type']) {

                /* =======================
                * 1️⃣ MENSUEL
                * ======================= */
                case Rappel::REMIND_TYPE_MONTH:
                    // Dernier jour du mois courant
                    $nextdisplay = (clone $now)
                        ->modify('last day of this month')
                        ->setTime(0, 0, 0);
                    break;


                /* =======================
                * 2️⃣ DÉBUT DE TRIMESTRE
                * (janv / avril / juill / oct)
                * ======================= */
                case Rappel::REMIND_TYPE_START_TRIMESTER:

                    $month = (int) $now->format('n');
                    $year  = (int) $now->format('Y');

                    if ($month <= 3) {
                        $lastMonth = 3;
                    } elseif ($month <= 6) {
                        $lastMonth = 6;
                    } elseif ($month <= 9) {
                        $lastMonth = 9;
                    } else {
                        $lastMonth = 12;
                    }

                    $lastDay = cal_days_in_month(CAL_GREGORIAN, $lastMonth, $year);
                    $nextdisplay = new \DateTime(sprintf('%d-%02d-%02d', $year, $lastMonth, $lastDay));
                    break;


                /* =======================
                * 3️⃣ FIN DE TRIMESTRE
                * (mars / juin / sept / déc)
                * ======================= */
                case Rappel::REMIND_TYPE_END_TRIMESTER:

                    $month = (int) $now->format('n');
                    $year  = (int) $now->format('Y');

                    if ($month <= 3) {
                        $lastMonth = 3;
                    } elseif ($month <= 6) {
                        $lastMonth = 6;
                    } elseif ($month <= 9) {
                        $lastMonth = 9;
                    } else {
                        $lastMonth = 12;
                    }

                    $lastDay = cal_days_in_month(CAL_GREGORIAN, $lastMonth, $year);
                    $nextdisplay = new \DateTime(sprintf('%d-%02d-%02d', $year, $lastMonth, $lastDay));
                    break;


                /* =======================
                * 4️⃣ ANNUEL
                * ======================= */
                case Rappel::REMIND_TYPE_YEAR:
                    // Dernier jour de l'année courante
                    $nextdisplay = new \DateTime($now->format('Y') . '-12-31');
                    break;


                default:
                    $nextdisplay = clone $now;
            }


            
            if($data["type"]<=3){
                $dateRappel = $data['date_rappel_mensuel_trimestre'];
                // Convertir en string jour
                $jourMois = $dateRappel->format('d'); // ex: "01"
            }else{
                $dateRappel = $data['date_rappel_annuel'];
                // Convertir en string jour-mois
                $jourMois = $dateRappel->format('d-m'); // ex: "01-11"
            }

            $rappel->setNextdisplay($nextdisplay);
            $rappel->setExpiry($jourMois);
            $rappel->setDate($now);
            $rappel->setStatus(false);
            $manager->persist($rappel);
            $manager->flush();
            $this->addFlash('success', 'Rappel ajouté');
            return $this->redirectToRoute('dashboard');
        }else if ($rappel_form->isSubmitted() && $request->request->has('rappel_submit') && ! $rappel_form->isValid()) {
            $this->addFlash('danger', 'Une erreur a eu lieu pendant l\'ajout du rappel');
        }


        return $this->render('dashboard/index.html.twig', [
            'counts' => $counts,
            'invoices' => $invoices,
            'invoicesToPay' => $invoicesToPay,
            'properties' => $properties,
            'warrants' => $warrants,
            'endings' => $endings,
            'copro_edings' => $copro_edings,
            'rappels_valides' => $rappels_valides,
            'next_revaluations' => $next_revaluations,
            'mandat_a_renouveller' => $mandat_a_renouveller,
            'fin_assurances_habitation' => $fin_assurances_habitation,
            'fin_assurances_chaudiere' => $fin_assurances_chaudiere,
            'fin_assurances_cheminee' => $fin_assurances_cheminee,
            'fin_assurances_clim_pompe' => $fin_assurances_clim_pompe,
            'notification_form' => $notification_form->createView(),
            'rappel_form' => $rappel_form->createView(),

        ]);
    }

     /**
     * @Route("/rappel/check/{id}", name="rappel_check")
     *
     * @param Request $request
     * @return Response
     */
    public function check(Rappel $rappel)
    {
        $manager = $this->getDoctrine()->getManager();
        $now = new \DateTime();

        switch ($rappel->getType()) {

            case Rappel::REMIND_TYPE_MONTH:
                $nextdisplay = (clone $now)->modify('last day of next month');
                break;

            case Rappel::REMIND_TYPE_START_TRIMESTER:
            case Rappel::REMIND_TYPE_END_TRIMESTER:
                $base = clone $rappel->getNextdisplay();
                // se placer au 1er jour du trimestre courant
                $month = (int) $base->format('n');
                $quarter = (int) ceil($month / 3);

                $quarterStart = new DateTime(sprintf(
                    '%d-%02d-01',
                    $base->format('Y'),
                    ($quarter - 1) * 3 + 1
                ));
                // fin du trimestre suivant
                $nextdisplay = (clone $quarterStart)
                    ->modify('+6 months')
                    ->modify('-1 day');

                break;

            case Rappel::REMIND_TYPE_YEAR:
                $nextdisplay = (clone $rappel->getNextdisplay())
                    ->modify('+1 year')
                    ->modify('last day of December');
                break;

            default:
                $nextdisplay = clone $now;
        }

        $rappel->setNextdisplay($nextdisplay);
        $rappel->setStatus(false); // prêt pour le prochain affichage

        $manager->flush();

        $this->addFlash('success', 'Rappel traité');
        return $this->redirectToRoute('dashboard');
    }


     /**
     * @Route("/rappel/delete/{id}", name="rappel_delete")
     *
     * @param Request $request
     * @return Response
     */
    public function delete(Rappel $rappel)
    {
        $manager = $this->getDoctrine()->getManager();
        $manager->remove($rappel);
        $manager->flush();

        $this->addFlash('success', 'Rappel supprimé');
        return $this->redirectToRoute('dashboard');
    }


}
