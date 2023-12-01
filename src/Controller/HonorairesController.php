<?php

namespace App\Controller;

use App\Service\DriveManager;
use DateTime;
use App\Entity\RevaluationHistory;
//use App\Service\Bank;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
//use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
//use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Honoraire;
use App\Form\HonoraireFormType;
class HonorairesController extends AbstractController
{
    /**
     * @Route("/honoraires/honoraires", name="honoraires")
     *
     * @return Response
     */
    public function index(Request $request): Response
    {
        $defaultData = ['message' => 'Type your message here'];
        $honoraires = $this->getDoctrine()
            ->getRepository(Honoraire::class)
            ->findAll();

        if (empty($honoraires)) {// si ils n'existent pas on crée de nouveaux objets
            $form = $this->createFormBuilder($defaultData)
            ->add('vh1', TextType::class, array('label' => false,'data' => '5.0'))
            ->add('min1', TextType::class, array('label' => false,'data' => '15.0'))
            ->add('vh2', TextType::class, array('label' => false,'data' => '6.0'))
            ->add('min2', TextType::class, array('label' => false,'data' => '18.0'))
            ->add('vh3', TextType::class, array('label' => false,'data' => '5.0'))
            ->add('min3', TextType::class, array('label' => false,'data' => '15.0'))
            ->add('vh4', TextType::class, array('label' => false,'data' => '4.0'))
            ->add('vh5', TextType::class, array('label' => false,'data' => '1.0'))
            ->add('min5', TextType::class, array('label' => false,'data' => '50.0'))
            ->add('vh6', TextType::class, array('label' => false,'data' => '50.0'))
            ->add('vh7', TextType::class, array('label' => false,'data' => '200.0'))
            ->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                //creer un honoraire
                $honoraire1 = new Honoraire();
                $honoraire1->setNom('Bien occupé individuel');
                $honoraire1->setDateMod(new DateTime());
                $honoraire1->setMinimum(floatval($data['min1']));
                $honoraire1->setValeur(floatval($data['vh1']));
                //creer un honoraire
                $honoraire2 = new Honoraire();
                $honoraire2->setNom('Bien occupé en copropriété');
                $honoraire2->setDateMod(new DateTime());
                $honoraire2->setMinimum(floatval($data['min2']));
                $honoraire2->setValeur(floatval($data['vh2']));
                //creer un honoraire
                $honoraire3 = new Honoraire();
                $honoraire3->setNom('Bien libre de toute occupation');
                $honoraire3->setDateMod(new DateTime());
                $honoraire3->setMinimum(floatval($data['min3']));
                $honoraire3->setValeur(floatval($data['vh3']));
                //creer un honoraire
                $honoraire4 = new Honoraire();
                $honoraire4->setNom('Gestion + de 10 biens');
                $honoraire4->setDateMod(new DateTime());
                $honoraire4->setMinimum(floatval("0.0"));
                $honoraire4->setValeur(floatval($data['vh4']));
                //creer un honoraire
                $honoraire5 = new Honoraire();
                $honoraire5->setNom('Indexation annuelle de la rente viagère');
                $honoraire5->setDateMod(new DateTime());
                $honoraire5->setMinimum(floatval($data['min5']));
                $honoraire5->setValeur(floatval($data['vh5']));
                //creer un honoraire
                $honoraire6 = new Honoraire();
                $honoraire6->setNom('Bien occupé individuel sans rente');
                $honoraire6->setDateMod(new DateTime());
                $honoraire6->setMinimum(floatval("0.0"));
                $honoraire6->setValeur(floatval($data['vh6']));
                //creer un honoraire
                $honoraire7 = new Honoraire();
                $honoraire7->setNom('Bien occupé en copropriété sans rente');
                $honoraire7->setDateMod(new DateTime());
                $honoraire7->setMinimum(floatval("0.0"));
                $honoraire7->setValeur(floatval($data['vh7']));
                    //les sauvegarder tous
                $manager = $this->getDoctrine()->getManager();
                $manager->persist($honoraire1);
                $manager->persist($honoraire2);
                $manager->persist($honoraire3);
                $manager->persist($honoraire4);
                $manager->persist($honoraire5);
                $manager->persist($honoraire6);
                $manager->persist($honoraire7);
                $manager->flush();
                $this->addFlash('success', 'Valeurs des honoraires bien éditées !');
                return $this->redirectToRoute('honoraires_list');
            }
        }else{ // si ils existent on récupère les anciens objets
            $form = $this->createFormBuilder($defaultData)
            ->add('vh1', TextType::class, array('label' => false,'data' => $honoraires[0]->getValeur()))
            ->add('min1', TextType::class, array('label' => false,'data' => $honoraires[0]->getMinimum()))
            ->add('vh2', TextType::class, array('label' => false,'data' => $honoraires[1]->getValeur()))
            ->add('min2', TextType::class, array('label' => false,'data' => $honoraires[1]->getMinimum()))
            ->add('vh3', TextType::class, array('label' => false,'data' => $honoraires[2]->getValeur()))
            ->add('min3', TextType::class, array('label' => false,'data' => $honoraires[2]->getMinimum()))
            ->add('vh4', TextType::class, array('label' => false,'data' => $honoraires[3]->getValeur()))
            ->add('vh5', TextType::class, array('label' => false,'data' => $honoraires[4]->getValeur()))
            ->add('min5', TextType::class, array('label' => false,'data' => $honoraires[4]->getMinimum()))
            ->add('vh6', TextType::class, array('label' => false,'data' => $honoraires[5]->getValeur()))
            ->add('vh7', TextType::class, array('label' => false,'data' => $honoraires[6]->getValeur()))
            ->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                //creer un honoraire
                $honoraire1 =$honoraires[0];
                $honoraire1->setNom('Bien occupé individuel');
                $honoraire1->setDateMod(new DateTime());
                $honoraire1->setMinimum(floatval($data['min1']));
                $honoraire1->setValeur(floatval($data['vh1']));
                //creer un honoraire
                $honoraire2 =$honoraires[1];
                $honoraire2->setNom('Bien occupé en copropriété');
                $honoraire2->setDateMod(new DateTime());
                $honoraire2->setMinimum(floatval($data['min2']));
                $honoraire2->setValeur(floatval($data['vh2']));
                //creer un honoraire
                $honoraire3 =$honoraires[2];
                $honoraire3->setNom('Bien libre de toute occupation');
                $honoraire3->setDateMod(new DateTime());
                $honoraire3->setMinimum(floatval($data['min3']));
                $honoraire3->setValeur(floatval($data['vh3']));
                //creer un honoraire
                $honoraire4 =$honoraires[3];
                $honoraire4->setNom('Gestion + de 10 biens');
                $honoraire4->setDateMod(new DateTime());
                $honoraire4->setMinimum(floatval("0.0"));
                $honoraire4->setValeur(floatval($data['vh4']));
                //creer un honoraire
                $honoraire5 =$honoraires[4];
                $honoraire5->setNom('Indexation annuelle de la rente viagère');
                $honoraire5->setDateMod(new DateTime());
                $honoraire5->setMinimum(floatval($data['min5']));
                $honoraire5->setValeur(floatval($data['vh5']));
                //creer un honoraire
                $honoraire6 =$honoraires[5];
                $honoraire6->setNom('Bien occupé individuel sans rente');
                $honoraire6->setDateMod(new DateTime());
                $honoraire6->setMinimum(floatval("0.0"));
                $honoraire6->setValeur(floatval($data['vh6']));
                //creer un honoraire
                $honoraire7 =$honoraires[6];
                $honoraire7->setNom('Bien occupé en copropriété sans rente');
                $honoraire7->setDateMod(new DateTime());
                $honoraire7->setMinimum(floatval("0.0"));
                $honoraire7->setValeur(floatval($data['vh7']));
                    //les sauvegarder tous
                $manager = $this->getDoctrine()->getManager();
                $manager->persist($honoraire1);
                $manager->persist($honoraire2);
                $manager->persist($honoraire3);
                $manager->persist($honoraire4);
                $manager->persist($honoraire5);
                $manager->persist($honoraire6);
                $manager->persist($honoraire7);
                $manager->flush();
                $this->addFlash('success', 'Valeurs des honoraires bien éditées !');
                return $this->redirectToRoute('honoraires_list');
            }
        }

        


        return $this->render('honoraires/index.html.twig',  array('form' => $form->createView()));
           

           
        }

       
    

    /**
     * @Route("/honoraires/generate", name="bank_generate", methods={"POST"})
     *
     * @param Request $request
     * @return Response
     *
     * @throws \Exception
     */
    /*public function generateHonoraires(Request $request, DriveManager $drive)
    {
        return 'Hello IPC';
    }*/

    /**
     * @Route("/honoraires/list", name="honoraires_list")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function view(Request $request)
    {
        $honoraires = $this->getDoctrine()
            ->getRepository(Honoraire::class)
            ->findAll();

        if (empty($honoraires)) {
            $this->addFlash('danger', 'Aucun honoraire n\'est enregistré');
        }
        /*
        foreach ($honoraires as $honoraire) {

            $form = $this->createForm(HonoraireFormType::class, $honoraire);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $manager = $this->getDoctrine()->getManager();
                $honoraire->setDateMod(new DateTime());

                if ($old_index !== $honoraire->getRevaluationIndex()) {
                    $notifications = $manager
                        ->getRepository(Notification::class)
                        ->findProperty('revaluation', $honoraire);

                    foreach ($notifications as $notification) {
                        $manager->remove($notification);
                    }

                    $history = new RevaluationHistory();
                    $history->setValue($old_index);
                    $history->setProperty($honoraire);

                    $honoraire->setLastRevaluation(new DateTime());

                    $manager->persist($history);
                }

                $manager->flush();
                $this->addFlash('success', 'Bien édité');
            }

            $tva = $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva']);
        }*/
        

        

        return $this->render('honoraires/list.html.twig', [
            
            'honoraires'  => $honoraires,
            
        ]);
    }

    /**
     * @Route("/ipc/add_honoraire", name="add_honoraire")
     *
     * @return Response
     */
    public function add_honoraire(Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $honoraire = new Honoraire();
        $honoraire->setDateMod(new \DateTime());
        //$honoraire->setType("Urbains");
        $form = $this->createForm(HonoraireFormType::class, $honoraire);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
          

            // 4) save the RevaluationHistory!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($honoraire);
            $entityManager->flush();

            // ... do any other work - like sending them an email, etc
            // maybe set a "flash" success message for the revaluationHistory

            return $this->redirectToRoute('honoraires_list');
        }
 		
        return $this->render('honoraires/ajouter-honoraire.html.twig', [
            'form' => $form->createView()
        ]);
    }


    /**
     * @Route("/supprimer_honoraires/{id}", name="supprimer_honoraire")
     */
    public function removeRevaluationHistory( Honoraire $h)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($h);
        $em->flush();
        return $this->redirectToRoute('honoraires_list');

    }
    
    /**
     * @Route("/modifier_honoraires/{id}", name="modifier_honoraire")
     */
    public function modifierHonoraire(  Request $request,  Honoraire $honoraire)
    {
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(HonoraireFormType::class, $honoraire);
        $form->handleRequest($request);
        if ($form->isSubmitted() and $form->isValid()) {

            $data = $form->getData();
            $honoraire->setDateMod(new \DateTime());
            $em->persist($honoraire);
            $em->flush();
            return $this->redirectToRoute('honoraires_list');
        }
        $columns = $em->remove($honoraire);
       
            return $this->render('honoraires/modifier-honoraire.html.twig', [
                "form" => $form->createView()
            ]);
        
        
    }
}
