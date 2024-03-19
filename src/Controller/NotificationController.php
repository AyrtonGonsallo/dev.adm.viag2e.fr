<?php

namespace App\Controller;

use App\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends AbstractController
{
    /**
     * @Route("/notifications", name="notifications")
     */
    public function index()
    {
        $notifications = $this->getDoctrine()->getManager()
            ->getRepository(Notification::class)
            ->findDateOrderedDesc();
            $notifications2 = $this->getDoctrine()->getManager()
            ->getRepository(Notification::class)
            ->findDateOrderedDesc();

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'notifications2' => $notifications2,
        ]);
    }

    /**
     * @Route("/test_notifications", name="test_notifications")
     */
    public function get_test()
    {
        $notifications = $this->getDoctrine()->getManager()
            ->getRepository(Notification::class)
            ->findDateOrderedDesc();
            
        return new JsonResponse(['res' => $notifications]);

    }

    /**
     * @Route("/notifications/valider_notification/{id}", name="valider_notification")
     *
     * @param Request $request
     * 
     * @return Response
     */
    public function valider_notification(Request $request)
    {
       
        $n = $this->getDoctrine()
            ->getRepository(Notification::class)
            ->findOneBy(['id' => $request->get('id')]);

        if (empty($n)) {
            $this->addFlash('danger', 'notification introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }
        $n->setStatus(0);
        $manager = $this->getDoctrine()->getManager();
            $manager->persist($n);
            $manager->flush();
            return $this->redirectToRoute('notifications', [], 302);
       
    }
}
