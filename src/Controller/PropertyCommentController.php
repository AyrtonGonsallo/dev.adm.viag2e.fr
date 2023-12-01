<?php

namespace App\Controller;

use App\Entity\PropertyComment;
use App\Entity\Property;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class PropertyCommentController extends AbstractController
{
    /**
     * @Route("/property_comment/delete/{commentId}", name="property_comment_delete")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function delete_property_comment(Request $request)
    {
        $comment = $this->getDoctrine()
        ->getRepository(PropertyComment::class)
        ->find($request->get('commentId'));

        $route = $this->generateUrl('property_view', [ 'propertyId' => $comment->getProperty()->getId(),'onglet' => 'm_tabs_mails']);

        if (!empty($comment )) {
            $manager = $this->getDoctrine()->getManager();
            $cid=$comment->getId();
            $manager->remove($comment);
            $manager->flush();

            $this->addFlash('success', 'Commentaire n°'.$cid.' supprimé');
            return $this->redirect($route);
        }

        $this->addFlash('danger', 'Une erreur a eu lieu pendant la suppression');
        return $this->redirect($route);
    }

    /**
     * @Route("/property_comment/activate/{commentId}", name="property_comment_activate")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function activate_property_comment(Request $request)
    {
        $comment = $this->getDoctrine()
        ->getRepository(PropertyComment::class)
        ->find($request->get('commentId'));

        $route = $this->generateUrl('property_view', [ 'propertyId' => $comment->getProperty()->getId(),'onglet' => 'm_tabs_mails']);
        

        if (!empty($comment )) {
            $comment->setStatus(1);
            $manager = $this->getDoctrine()->getManager();
            $manager->persist($comment);
            $manager->flush();

            $this->addFlash('success', 'Commentaire n°'.$comment->getId().' activé');
            return $this->redirect($route);
        }

        $this->addFlash('danger', 'Une erreur a eu lieu pendant la suppression');
        return $this->redirect($route);
    }

    /**
     * @Route("/property_comment/desactivate/{commentId}", name="property_comment_desactivate")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function desactivate_property_comment(Request $request)
    {
        $comment = $this->getDoctrine()
        ->getRepository(PropertyComment::class)
        ->find($request->get('commentId'));

        $route = $this->generateUrl('property_view', [ 'propertyId' => $comment->getProperty()->getId(),'onglet' => 'm_tabs_mails']);

        if (!empty($comment )) {
            $comment->setStatus(0);
            $manager = $this->getDoctrine()->getManager();
            $manager->persist($comment);
            $manager->flush();

            $this->addFlash('success', 'Commentaire n°'.$comment->getId().' désactivé');
            return $this->redirect($route);
        }

        $this->addFlash('danger', 'Une erreur a eu lieu pendant la suppression');
        return $this->redirect($route);
    }
}
