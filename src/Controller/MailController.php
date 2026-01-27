<?php

namespace App\Controller;

use App\Entity\Mail;
use App\Entity\Mailing;
use App\Entity\Warrant;
use App\Entity\PieceJointe;
use App\Entity\Property;
use App\Form\MailFormType;
use App\Form\MailingFormType;
use DateTime;
use App\Twig\StringLoader;
use App\Service\DriveManager;
use Swift_Mailer;
use Swift_Message;
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


class MailController extends AbstractController
{

    
    
    /**
     * @Route("/mail/add/{warrantId}", name="mail_add", requirements={"warrantId"="\d+"})
     *
     * @param Request $request
     * @param Swift_Mailer $mailer
     * @return RedirectResponse
     */
    public function add(Request $request, Swift_Mailer $mailer)
    {
        $warrant = $this->getDoctrine()
            ->getRepository(Warrant::class)
            ->find($request->get('warrantId'));

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $mail = new Mail();
        $form = $this->createForm(MailFormType::class, $mail, ['action' => $this->generateUrl('mail_add', ['warrantId' => $warrant->getId()])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mail->setStatus(Mail::STATUS_GENERATED);
            $mail->setUser($this->getUser());
            $mail->setEtat(0);
            $mail->setWarrant($warrant);

            if ($this->sendMail($mail, $mailer)) {
                $mail->setStatus(Mail::STATUS_SENT);
                $this->addFlash('success', 'Message envoyé');
            } else {
                $mail->setStatus(Mail::STATUS_UNSENT);
                $this->addFlash('danger', 'Echec de l\'envoi');
            }

            $manager = $this->getDoctrine()->getManager();
            $manager->persist($mail);
            $manager->flush();
        }

        return $this->redirectToRoute('warrant_view', ['type' => Warrant::getTypeName($warrant->getType()), 'warrantId' => $warrant->getId()]);
    }

 /**
     * @Route("/mail/add2/{type}", name="mail_add2",requirements={"type"="(buyers)|(sellers)"})
     *
     * @param Request $request
     * @param Swift_Mailer $mailer
     * @return RedirectResponse
     */
    public function add2(Request $request, Swift_Mailer $mailer)
    {
        
        $type=$request->get('type');
        if($type=="buyers"){
            $id_type = 1;

        }else if($type=="sellers"){
            $id_type = 2;
        }
        $warrants = $this->getDoctrine()
            ->getRepository(Warrant::class)
            ->findList($id_type);
            
        foreach ($warrants as $warrant) {
            
            $mail = new Mail();
            $form = $this->createForm(MailFormType::class, $mail, ['action' => $this->generateUrl('mail_add2', ['type' => $type])]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                
                $mail->setStatus(Mail::STATUS_GENERATED);
                $mail->setUser($this->getUser());
                $mail->setEtat(0);
                $mail->setWarrant($warrant);

                if ($this->sendMail($mail, $mailer)) {
                    $mail->setStatus(Mail::STATUS_SENT);
                    $this->addFlash('success', 'Message envoyé');
                } else {
                    $mail->setStatus(Mail::STATUS_UNSENT);
                    $this->addFlash('danger', 'Echec de l\'envoi');
                }

                $manager = $this->getDoctrine()->getManager();
                $manager->persist($mail);
                $manager->flush();
            }
        }
        if($type=="buyers"){
            return $this->redirectToRoute('warrants', ['type' => 'buyers']);

        }else if($type=="sellers"){
            return $this->redirectToRoute('warrants', ['type' => 'sellers']);

        }
    }
        
        /**
     * @Route("/mail/add3/{p_id}", name="mail_add3")
     *
     * @param Request $request
     * @param Swift_Mailer $mailer
     * @return RedirectResponse
     */
    public function add3(Request $request, Swift_Mailer $mailer)
    {
        
        $p_id=$request->get('p_id');
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($p_id);
        $warrant = $property->getWarrant();
            
       
            $mail = new Mail();
            $form = $this->createForm(MailFormType::class, $mail, ['action' => $this->generateUrl('mail_add3', ['p_id' => $p_id])]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                
                $mail->setStatus(Mail::STATUS_GENERATED);
                $mail->setUser($this->getUser());
                $mail->setEtat(0);
                $mail->property_id=$p_id;
                $mail->setWarrant($warrant);

                if ($this->sendMail($mail, $mailer)) {
                    $mail->setStatus(Mail::STATUS_SENT);
                    $this->addFlash('success', 'Message envoyé');
                } else {
                    $mail->setStatus(Mail::STATUS_UNSENT);
                    $this->addFlash('danger', 'Echec de l\'envoi');
                }

                $manager = $this->getDoctrine()->getManager();
                $manager->persist($mail);
                $manager->flush();
            }
            return $this->redirectToRoute('property_view', ['propertyId' => $p_id]);

        }
        

    
/**
     * @Route("/mail/add_property_mail/{propertyId}", name="property_mail_add", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param Swift_Mailer $mailer
     * @return RedirectResponse
     */
    
    public function add_property_mail(Request $request, Swift_Mailer $mailer)
    {
        $property = $this->getDoctrine()
        ->getRepository(Property::class)
        ->find($request->get('propertyId'));

        $warrant = $property->getWarrant();

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $mail = new Mail();
        $form = $this->createForm(MailFormType::class, $mail, ['action' => $this->generateUrl('mail_add', ['warrantId' => $warrant->getId()])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mail->setStatus(Mail::STATUS_GENERATED);
            $mail->setUser($this->getUser());
            $mail->setWarrant($warrant);
            $mail->setEtat(0);
            if ($this->sendMail($mail, $mailer)) {
                $mail->setStatus(Mail::STATUS_SENT);
                $this->addFlash('success', 'Message envoyé');
            } else {
                $mail->setStatus(Mail::STATUS_UNSENT);
                $this->addFlash('danger', 'Echec de l\'envoi');
            }

            $manager = $this->getDoctrine()->getManager();
            $manager->persist($mail);
            $manager->flush();
        }

        return $this->redirectToRoute('property_view', ['propertyId' => $property->getId()]);
    }


    /**
     * @Route("/mail/content", name="mail_content")
     *
     * @param Request $request
     * @return Response
     */
    public function content(Request $request)
    {
        if (!is_numeric($request->get('id'))) {
            throw $this->createNotFoundException('Mail not found');
        }

        $mail = $this->getDoctrine()
            ->getRepository(Mail::class)
            ->find($request->get('id'));

        if (empty($mail)) {
            throw $this->createNotFoundException('Mail not found');
        }

        return new Response($mail->getContent());
    }

    /**
     * @Route("/mailing/create", name="mailing_create")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function create(Request $request, DriveManager $driveManager)
    {
        $mailing=new Mailing();
        $form = $this->createForm(MailingFormType::class, $mailing);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $file = new PieceJointe();
            /** @var UploadedFile $f */
            $f = $mailing->getPieceJointeDriveId();
            $file->setTmpName(md5(uniqid()).'.'.$f->guessExtension());
            $file->setMime($f->getMimeType());
            $f->move($this->getParameter('tmp_files_dir'), $f->getClientOriginalName());

            $filePath = $this->getParameter('tmp_files_dir').'/'.$f->getClientOriginalName();
            $file->setDriveId($driveManager->addFile($f->getClientOriginalName(), $filePath, PieceJointe::TYPE_DOCUMENT, 0));
            $file->setType(PieceJointe::TYPE_DOCUMENT);
            $file->setDate(new DateTime());
            $file->setName($f->getClientOriginalName());
            $mailing->setPieceJointe($file);
            $manager = $this->getDoctrine()->getManager();
            $manager->persist($file);
            $manager->flush();

            //unlink($filePath);
            /* @var Mailing $mailing */
            $mailing = $form->getData();
            $mailing->setPieceJointeDriveId($file->getDriveId());
            $manager = $this->getDoctrine()->getManager();

            $manager->persist($mailing);
            $manager->flush();

            return $this->redirectToRoute('mailing_list');
        }

        $warrants = $this->getDoctrine()->getRepository(Warrant::class)->findAll();

        return $this->render('mail/create.html.twig', ['form' => $form->createView(), 'warrants' => $warrants]);
    }

    /**
     * @Route("/mailing/create/preview", name="mailing_create_preview")
     *
     * @param Request $request
     * @return Response
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function createPreview(Request $request)
    {
        if(empty($request->get('content')) || empty($request->get('warrantId'))) {
            throw new BadRequestHttpException();
        }

        $warrant = $this->getDoctrine()
            ->getRepository(Warrant::class)
            ->find($request->get('warrantId'));

        if (empty($warrant)) {
            throw new NotFoundHttpException();
        }

        $twig = new Environment(new StringLoader());
        return new Response($twig->render($request->get('content'), Mail::getReplacers($warrant)));
    }

    /**
     * @Route("/mailing/list", name="mailing_list")
     *
     * @return Response
     */
    public function viewMailings()
    {
        $mailings = $this->getDoctrine()
            ->getRepository(Mailing::class)
            ->listAll();

        return $this->render('mail/list.html.twig', ['mailings' => $mailings]);
    }

    /**
     * @Route("/mailing/view", name="mailing_view")
     *
     * @param Request $request
     * @return Response
     *
     */
    public function view(Request $request)
    {
        if(empty($request->get('mailingId'))) {
            throw new BadRequestHttpException();
        }

        $mailing = $this->getDoctrine()
            ->getRepository(Mailing::class)
            ->find($request->get('mailingId'));

        if (empty($mailing)) {
            throw new NotFoundHttpException();
        }

        return new Response($mailing->getContent());
    }

    /**
     * @Route("/mail/resend/{mailId}", name="mail_resend", requirements={"mailId"="\d+"})
     *
     * @param Request $request
     * @param Swift_Mailer $mailer
     * @return RedirectResponse
     */
    public function resend(Request $request, Swift_Mailer $mailer)
    {
        $mail = $this->getDoctrine()
            ->getRepository(Mail::class)
            ->find($request->get('mailId'));

        if (!empty($mail)) {
            if ($this->sendMail($mail, $mailer)) {
                $mail->setStatus(Mail::STATUS_SENT);
                $this->addFlash('success', 'Message envoyé');
            } else {
                $mail->setStatus(Mail::STATUS_UNSENT);
                $this->addFlash('danger', 'Echec de l\'envoi');
            }

            $this->getDoctrine()->getManager()->flush();
        } else {
            $this->addFlash('danger', 'E-mail introuvable');
        }

        return $this->redirectToRoute('warrant_view', ['type' => Warrant::getTypeName($mail->getWarrant()->getType()), 'warrantId' => $mail->getWarrant()->getId()]);
    }

    public function sendMail(Mail $mail, Swift_Mailer $mailer)
    {
        $message = (new Swift_Message($mail->getObject()))
            ->setFrom($this->getParameter('mail_from'))
            ->setBcc($this->getParameter('mail_from'))
            ->setTo($mail->getWarrant()->getMail1())
            ->setBody($mail->getContent(), 'text/html');

        if (!empty($mail->getWarrant()->getMail2())) {
            $message->setCc($mail->getWarrant()->getMail2());
        }
        $property = $this->getDoctrine()
        ->getRepository(Property::class)
        ->find($mail->property_id);
        $adresse_vendeur=$property->getMail1()?$property->getMail1():$property->getMail2();
        $adresse_Debirentier=$property->getEmailDebirentier();
        if (!empty($adresse_vendeur)) {
            $message->setCc($adresse_vendeur);
        }
        if (!empty( $adresse_Debirentier)) {
            $message->setCc( $adresse_Debirentier);
        }

        return $mailer->send($message);
    }
	
	/**
     * @Route("/mail/send_test_mail", name="send_test_mail")
     *
     * @param Request $request
     * @param Swift_Mailer $mailer
     * @return RedirectResponse
     */
    public function sendTestMail( Swift_Mailer $mailer)
    {
        $message = (new Swift_Message("Test de l'ancienne configuration smtp"))
            ->setFrom($this->getParameter('mail_from'))
            ->setBcc($this->getParameter('mail_from'))
            ->setTo("ayrtongonsallo444@gmail.com")
            ->setBody("Ceci est un mail de test. S'il est parvenu à vous c'est que l'ancienne configuration Smtp est bien faite.", 'text/html');
		$message->setCc("roquetigrinho@gmail.com");
        $mailer->send($message);
        return $this->redirectToRoute('dashboard');
    }
	
}
