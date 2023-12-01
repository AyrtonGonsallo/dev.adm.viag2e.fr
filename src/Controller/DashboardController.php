<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Parameter;
use App\Entity\Property;
use App\Entity\Warrant;
use stdClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    /**
     * @Route("/", name="dashboard")
     *
     * @return Response
     */
    public function index()
    {
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

        $counts = new stdClass();
        $counts->warrants_b = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_warrants_b'])->getValue();
        $counts->warrants_s = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_warrants_s'])->getValue();
        $counts->properties = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_properties'])->getValue();

        return $this->render('dashboard/index.html.twig', [
            'counts' => $counts,
            'invoices' => $invoices,
            'properties' => $properties,
            'warrants' => $warrants,
            'endings' => $endings
        ]);
    }
}
