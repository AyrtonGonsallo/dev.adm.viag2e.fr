<?php

namespace App\Controller;

use App\Entity\Notification;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

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
}
