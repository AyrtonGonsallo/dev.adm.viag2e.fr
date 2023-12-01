<?php

namespace App\Controller;

use App\Service\DriveManager;
use DateTime;
use App\Entity\RevaluationHistory;
use App\Entity\Property;
//use App\Service\Bank;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\RevaluationHistoryFormType;
use App\Form\RevaluationHistoryFormType_ogi;
use App\Form\RevaluationHistoryFormType_urbain;
class IpcController extends AbstractController
{
    /**
     * @Route("/ipc/ipc", name="ipc")
     *
     * @return Response
     */
    public function index()
    {
 		$exports='test';
        return $this->render('ipc/index.html.twig', [
            'exports' => $exports,
        ]);
    }


    /**
     * @Route("/ipc/liste-menages", name="liste_menages")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function view_indices_menages(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $connection = $em->getConnection();
        $statement = $connection->prepare("SELECT rh.id,rh.value as 'valeur', rh.comment as 'commentaire',rh.date as 'date_rev'FROM revaluation_history rh WHERE rh.type='Ménages' Order by rh.id desc;");

        $statement->execute();
        $indices = $statement->fetchAll();
       

        if (empty($indices)) {
            $this->addFlash('danger', 'Aucun indice n\'est enregistré');
        }
        /*
        foreach ($indices as $indice) {

            $form = $this->createForm(HonoraireFormType::class, $indice);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $manager = $this->getDoctrine()->getManager();
                $indice->setDateMod(new DateTime());

                if ($old_index !== $indice->getRevaluationIndex()) {
                    $notifications = $manager
                        ->getRepository(Notification::class)
                        ->findProperty('revaluation', $indice);

                    foreach ($notifications as $notification) {
                        $manager->remove($notification);
                    }

                    $history = new RevaluationHistory();
                    $history->setValue($old_index);
                    $history->setProperty($indice);

                    $indice->setLastRevaluation(new DateTime());

                    $manager->persist($history);
                }

                $manager->flush();
                $this->addFlash('success', 'Bien édité');
            }

            $tva = $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva']);
        }*/
        

        

        return $this->render('ipc/liste-menages.html.twig', [
            
            'indices'  => $indices,
            
        ]);
    }
/**
     * @Route("/ipc/liste-OGI", name="liste_ogi")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function view_indices_ogi(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $connection = $em->getConnection();
        $statement = $connection->prepare("SELECT rh.id,rh.value as 'valeur', rh.comment as 'commentaire',rh.date as 'date_rev' FROM revaluation_history rh WHERE rh.type='OGI' Order by rh.id desc;");

        $statement->execute();
        $indices = $statement->fetchAll();
       

        if (empty($indices)) {
            $this->addFlash('danger', 'Aucun indice n\'est enregistré');
        }
        /*
        foreach ($indices as $indice) {

            $form = $this->createForm(HonoraireFormType::class, $indice);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $manager = $this->getDoctrine()->getManager();
                $indice->setDateMod(new DateTime());

                if ($old_index !== $indice->getRevaluationIndex()) {
                    $notifications = $manager
                        ->getRepository(Notification::class)
                        ->findProperty('revaluation', $indice);

                    foreach ($notifications as $notification) {
                        $manager->remove($notification);
                    }

                    $history = new RevaluationHistory();
                    $history->setValue($old_index);
                    $history->setProperty($indice);

                    $indice->setLastRevaluation(new DateTime());

                    $manager->persist($history);
                }

                $manager->flush();
                $this->addFlash('success', 'Bien édité');
            }

            $tva = $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva']);
        }*/
        

        

        return $this->render('ipc/liste-ogi.html.twig', [
            
            'indices'  => $indices,
            
        ]);
    }

/**
     * @Route("/ipc/liste-urbains", name="liste_urbains")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function view_indices_urbains(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $connection = $em->getConnection();
        $statement = $connection->prepare("SELECT rh.id,rh.value as 'valeur', rh.comment as 'commentaire',rh.date as 'date_rev' FROM revaluation_history rh WHERE rh.type='Urbains' Order by rh.id desc;");

        $statement->execute();
        $indices = $statement->fetchAll();
       

        if (empty($indices)) {
            $this->addFlash('danger', 'Aucun indice n\'est enregistré');
        }
        /*
        foreach ($indices as $indice) {

            $form = $this->createForm(HonoraireFormType::class, $indice);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $manager = $this->getDoctrine()->getManager();
                $indice->setDateMod(new DateTime());

                if ($old_index !== $indice->getRevaluationIndex()) {
                    $notifications = $manager
                        ->getRepository(Notification::class)
                        ->findProperty('revaluation', $indice);

                    foreach ($notifications as $notification) {
                        $manager->remove($notification);
                    }

                    $history = new RevaluationHistory();
                    $history->setValue($old_index);
                    $history->setProperty($indice);

                    $indice->setLastRevaluation(new DateTime());

                    $manager->persist($history);
                }

                $manager->flush();
                $this->addFlash('success', 'Bien édité');
            }

            $tva = $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva']);
        }*/
        

        

        return $this->render('ipc/liste-urbains.html.twig', [
            
            'indices'  => $indices,
            
        ]);
    }

    /**
     * @Route("/ipc/add_indice_urbain", name="add_indice_urbain")
     *
     * @return Response
     */
    public function add_indice_urbain(Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $revaluationHistoryIPC_Urbain = new RevaluationHistory();
        //$revaluationHistoryIPC_Urbain->setDate(new \DateTime());
        $revaluationHistoryIPC_Urbain->setType("Urbains");
        $form = $this->createForm(RevaluationHistoryFormType_urbain::class, $revaluationHistoryIPC_Urbain);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
           

            // 4) save the RevaluationHistory!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($revaluationHistoryIPC_Urbain);
            $entityManager->flush();

            // ... do any other work - like sending them an email, etc
            // maybe set a "flash" success message for the revaluationHistory

            return $this->redirectToRoute('liste_urbains');
        }
 		
        return $this->render('ipc/ajouter-indice-urbains.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/ipc/add_indice_ogi", name="add_indice_ogi")
     *
     * @return Response
     */
    public function add_indice_ogi(Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $revaluationHistoryIPC_OGI = new RevaluationHistory();
        //$revaluationHistoryIPC_OGI->setDate(new \DateTime());
        $revaluationHistoryIPC_OGI->setType("OGI");
        $form = $this->createForm(RevaluationHistoryFormType_ogi::class, $revaluationHistoryIPC_OGI);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            

            // 4) save the RevaluationHistory!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($revaluationHistoryIPC_OGI);
            $entityManager->flush();

            // ... do any other work - like sending them an email, etc
            // maybe set a "flash" success message for the revaluationHistory

            return $this->redirectToRoute('liste_ogi');
        }
 		
        return $this->render('ipc/ajouter-indice-ogi.html.twig', [
            'form' => $form->createView()
        ]);
    }
/**
     * @Route("/ipc/add_indice_menage", name="add_indice_menage")
     *
     * @return Response
     */
    public function add_indice_menage(Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $revaluationHistoryIPCMenage = new RevaluationHistory();
        //$revaluationHistoryIPCMenage->setDate(new \DateTime());
        $revaluationHistoryIPCMenage->setType("Ménages");
        $form = $this->createForm(RevaluationHistoryFormType::class, $revaluationHistoryIPCMenage);

        // 2) handle the submit (will only happen on POST)
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
           

            // 4) save the RevaluationHistory!
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($revaluationHistoryIPCMenage);
            $entityManager->flush();

            // ... do any other work - like sending them an email, etc
            // maybe set a "flash" success message for the revaluationHistory

            return $this->redirectToRoute('liste_menages');
        }
 		
        return $this->render('ipc/ajouter-indice-menages.html.twig', [
            'form' => $form->createView()
        ]);
    }
    /**
     * @Route("/supprimer_indice_menages/{id}", name="supprimer_indice_menage")
     */
    public function removeRevaluationHistory( RevaluationHistory $revaluationHistory)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($revaluationHistory);
        $em->flush();
        if($revaluationHistory->getType()=="Ménages"){
            return $this->redirectToRoute('liste_menages');
        }else if($revaluationHistory->getType()=="Urbains"){
            return $this->redirectToRoute('liste_urbains');
        }else if($revaluationHistory->getType()=="OGI"){
            return $this->redirectToRoute('liste_ogi');
        }else{
            return $this->redirectToRoute('liste_menages');
        }
       // return $this->redirectToRoute('liste_menages');

    }
    
    /**
     * @Route("/modifier_indice_menages/{id}", name="modifier_indice_menage")
     */
    public function editRevaluationHistory(  Request $request,  RevaluationHistory $revaluationHistory)
    {
        $em = $this->getDoctrine()->getManager();
        if($revaluationHistory->getType()=="Ménages"){
            $form = $this->createForm(RevaluationHistoryFormType::class, $revaluationHistory);
        }else if($revaluationHistory->getType()=="Urbains"){
            $form = $this->createForm(RevaluationHistoryFormType_urbain::class, $revaluationHistory);
        }else if($revaluationHistory->getType()=="OGI"){
            $form = $this->createForm(RevaluationHistoryFormType_ogi::class, $revaluationHistory);
        }else{
            $form = $this->createForm(RevaluationHistoryFormType::class, $revaluationHistory);
        }
        $form->handleRequest($request);
        
        if ($form->isSubmitted() and $form->isValid()) {

            $data = $form->getData();
            
           
            //$revaluationHistory->setDate(new \DateTime());
            $em->persist($revaluationHistory);
            $em->flush();
            if($revaluationHistory->getType()=="Ménages"){
                return $this->redirectToRoute('liste_menages');
            }else if($revaluationHistory->getType()=="Urbains"){
                return $this->redirectToRoute('liste_urbains');
            }else if($revaluationHistory->getType()=="OGI"){
                return $this->redirectToRoute('liste_ogi');
            }else{
                return $this->redirectToRoute('liste_menages');
            }
           // return $this->redirectToRoute('liste_menages');
        }
        $columns = $em->remove($revaluationHistory);
        if($revaluationHistory->getType()=="Ménages"){
            return $this->render('ipc/modifier-indice-menages.html.twig', [
                "form" => $form->createView()
            ]);
        }else if($revaluationHistory->getType()=="Urbains"){
            return $this->render('ipc/modifier-indice-urbains.html.twig', [
                "form" => $form->createView()
            ]);
        }else if($revaluationHistory->getType()=="OGI"){
            return $this->render('ipc/modifier-indice-ogi.html.twig', [
                "form" => $form->createView()
            ]);
        }else{
            return $this->render('ipc/modifier-indice-menages.html.twig', [
                "form" => $form->createView()
            ]);
        }
        
    }
}
