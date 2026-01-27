<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\Mail;
use App\Entity\Notification;
use App\Entity\Parameter;
use App\Entity\Property;
use App\Entity\Warrant;
use App\Form\FileFormType;
use App\Form\MailFormType;
use App\Form\WarrantFormType;
use App\Service\DriveManager;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class WarrantController extends AbstractController
{
    /**
     * @Route("/warrant/activate/{warrantId}", name="warrant_activate", requirements={"warrantId"="\d+"})
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws \Exception
     */
    public function activate(Request $request)
    {
        $manager = $this->getDoctrine()->getManager();

        $warrant = $manager
            ->getRepository(Warrant::class)
            ->find($request->get('warrantId'));

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        if ($warrant->isActive()) {
            $warrant->setActive(false);

            $param = $manager->getRepository(Parameter::class)->findOneBy(['name' => ($warrant->getType() == Warrant::TYPE_BUYERS) ? 'count_warrants_b' : 'count_warrants_s']);
            $param->setValue($param->getValue() - 1);

            $properties = $manager
                ->getRepository(Property::class)
                ->findBy(['warrant' => $warrant]);

            foreach ($properties as $property) {
                $notifications = $manager
                    ->getRepository(Notification::class)
                    ->findProperty('revaluation', $property);

                foreach ($notifications as $notification) {
                    $manager->remove($notification);
                }

                $notifications = $manager
                    ->getRepository(Notification::class)
                    ->findProperty('terminatingcontract', $property);

                foreach ($notifications as $notification) {
                    $manager->remove($notification);
                }
            }

            $this->addFlash('success', 'Mandat désactivé');
        } else {
            $warrant->setActive(true);

            $param = $manager->getRepository(Parameter::class)->findOneBy(['name' => ($warrant->getType() == Warrant::TYPE_BUYERS) ? 'count_warrants_b' : 'count_warrants_s']);
            $param->setValue($param->getValue() + 1);

            $properties = $manager
                ->getRepository(Property::class)
                ->findBy(['warrant' => $warrant]);

            foreach ($properties as $property) {
                if ($property->isActive()) {
                    $property->setLastInvoice(new DateTime());
                }
            }

            $this->addFlash('success', 'Mandat activé');
        }
        $manager->flush();

        return $this->redirectToRoute('warrant_view', ['warrantId' => $request->get('warrantId'), 'type' => $warrant->getTypeString()]);
    }

    /**
     * @Route("/warrants/{type}", name="warrants", requirements={"type"="(buyers)|(sellers)"})
     *
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function index(Request $request)
    {
        $warrants = $this->getDoctrine()
            ->getRepository(Warrant::class)
            ->findList(Warrant::getTypeId($request->get('type')));

        $properties = $this->getDoctrine()
            ->getRepository(Property::class)
            ->findBy(['type' => Warrant::getTypeId($request->get('type'))]);

        $warrant = new Warrant();
		 $warrant->setBankIcs('FR12ZZZ886B32');
        $form = $this->createForm(WarrantFormType::class, $warrant);
        $form->handleRequest($request);
        $form_mail = $this->createForm(MailFormType::class, new Mail(), ['action' => $this->generateUrl('mail_add2', ['type' => $request->get('type')])]);

        if ($form->isSubmitted() && $form->isValid()) {
            $warrant = $form->getData();
            $warrant->setType(Warrant::getTypeId($request->get('type')));
            $warrant->setCreationUser($this->getUser());
            $warrant->setEditionUser($this->getUser());

            $manager = $this->getDoctrine()->getManager();
            $manager->persist($warrant);

            $param = $manager->getRepository(Parameter::class)->findOneBy(['name' => ($warrant->getType() == Warrant::TYPE_BUYERS) ? 'count_warrants_b' : 'count_warrants_s']);
            $param->setValue($param->getValue() + 1);

            $manager->flush();

            $this->addFlash('success', 'Mandat créé');
            return $this->redirectToRoute('warrant_view', ['type' => $request->get('type'), 'warrantId' => $warrant->getId()]);
        }
        $fichiers = $this->getDoctrine()
        ->getRepository(File::class)
        ->findBy(array(), null, 100, 0);
        $messages = $this->getDoctrine()
            ->getRepository(Mail::class)
            ->findAll();
        if($request->get('type') == "buyers"){
            $id_type = 1;

        }else if($request->get('type') == "sellers"){
            $id_type = 2;
        }
        return $this->render('warrant/index.html.twig', [
            'properties' => $properties,
            'warrants'  => $warrants,
            'fichiers'  => $fichiers,
            'id_type'  => $id_type,
            'form_mail'  => $form_mail->createView(),
            'messages'  => $messages,
            'form'      => $form->createView()
        ]);
    }

    /**
     * @Route("/warrant/{type}/{warrantId}", name="warrant_view", requirements={"type"="(buyers)|(sellers)", "warrantId"="\d+"})
     *
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function view(Request $request)
    {
        $warrant = $this->getDoctrine()
            ->getRepository(Warrant::class)
            ->find($request->get('warrantId'));

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('warrants', ['type' => $request->get('type')], 302);
        }

        if ($warrant->getType() !== Warrant::getTypeId($request->get('type'))) {
            return $this->redirectToRoute('warrant_view', ['type' => Warrant::getTypeName($warrant->getType()), 'warrantId' => $warrant->getId()]);
        }

        $form = $this->createForm(WarrantFormType::class, $warrant);
        $form_file = $this->createForm(FileFormType::class, new File(), ['action' => $this->generateUrl('file_add', ['warrantId' => $warrant->getId()])]);
        $form_mail = $this->createForm(MailFormType::class, new Mail(), ['action' => $this->generateUrl('mail_add', ['warrantId' => $warrant->getId()])]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $warrant->setEditionUser($this->getUser());
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Mandat édité');
        }

        return $this->render('warrant/view.html.twig', [
            'active'     => $warrant->isActive(),
            'form'       => $form->createView(),
            'form_file'  => $form_file->createView(),
            'form_mail'  => $form_mail->createView(),
            'properties' => $warrant->getProperties(),
            'warrant'    => $warrant,
            'warrantId'  => $request->get('warrantId'),
            'page' => empty($request->get('page')) ? 'customer' : $request->get('page')
        ]);
    }
}
