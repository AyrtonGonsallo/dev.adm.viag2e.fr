<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Parameter;
use App\Entity\Property;
use App\Entity\Warrant;
use App\Entity\Notification;
use stdClass;
use DateTime;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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

        $counts = new stdClass();
        $counts->warrants_b = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_warrants_b'])->getValue();
        $counts->warrants_s = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_warrants_s'])->getValue();
        $counts->properties = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_properties'])->getValue();

        $defaultData = ['message' => 'Type your message here'];
        $notification_form = $this->createFormBuilder($defaultData)
        ->add('texte', TextareaType::class, array('required' => true,
            'attr' => array('cols' => '100', 'rows' => '5'),
        ))
        ->add('date_rappel', DateType::class, ['required' => true, 'format' => 'dd-MMM-yyyy', 'years' => range(date('Y'), date('Y')+10)])
        ->getForm();
            $notification_form->handleRequest($request);

        if ($notification_form->isSubmitted() && $notification_form->isValid()) {
            $data = $notification_form->getData();
            $notification = new Notification();
            $notification->setProperty(null);
            $notification->setType("notification-libre");
            $notification->setData(['date'=> $data["date_rappel"]->format('d-m-Y'), 'message' => $data["texte"]]);
            $notification->setExpiry($data["date_rappel"]);
            $manager->persist($notification);
            $manager->flush();
            $this->addFlash('success', 'Notification ajoutée');
        }else if ($notification_form->isSubmitted() && ! $notification_form->isValid()) {
            $this->addFlash('danger', 'Une erreur a eu lieu pendant l\'ajout de la notification');
        }


        return $this->render('dashboard/index.html.twig', [
            'counts' => $counts,
            'invoices' => $invoices,
            'properties' => $properties,
            'warrants' => $warrants,
            'endings' => $endings,
            'copro_edings' => $copro_edings,
            'next_revaluations' => $next_revaluations,
            'fin_assurances_habitation' => $fin_assurances_habitation,
            'fin_assurances_chaudiere' => $fin_assurances_chaudiere,
            'fin_assurances_cheminee' => $fin_assurances_cheminee,
            'fin_assurances_clim_pompe' => $fin_assurances_clim_pompe,
            'notification_form' => $notification_form->createView(),

        ]);
    }
}
