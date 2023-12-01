<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserFormType;
use App\Form\UserRegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use \Symfony\Component\HttpFoundation\RedirectResponse;
use \Symfony\Component\HttpFoundation\Response;

class UserController extends AbstractController
{
    /**
     * @Route("/user/activate/{userId}", name="user_activate", requirements={"userId"="\d+"})
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function activate(Request $request)
    {
        $user = $this->getDoctrine()
            ->getRepository(User::class)
            ->find($request->get('userId'));

        if (empty($user)) {
            $this->addFlash('danger', 'Utilisateur introuvable');
            return $this->redirectToRoute('users', [], 302);
        }

        if ($user->isActive()) {
            $user->setActive(false);
            $this->addFlash('success', 'Utilisateur désactivé');
        } else {
            $user->setActive(true);
            $this->addFlash('success', 'Utilisateur activé');
        }
        $this->getDoctrine()->getManager()->flush();

        return $this->redirectToRoute('user_view', ['userId' => $request->get('userId')]);
    }

    /**
     * @Route("/users", name="users")
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return RedirectResponse|Response
     */
    public function index(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $users = $this->getDoctrine()
            ->getRepository(User::class)
            ->findAllOrdered();

        $user = new User();
        $form = $this->createForm(UserRegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $form->getData();
            $user->setPassword($passwordEncoder->encodePassword($user, $form->get('plainPassword')->getData()));

            $manager = $this->getDoctrine()->getManager();
            $manager->persist($user);
            $manager->flush();

            $this->addFlash('success', 'Utilisateur créé');
            return $this->redirectToRoute('user_view', ['userId' => $user->getId()]);
        }

        return $this->render('user/index.html.twig', [
            'users'    => $users,
            'form'     => $form->createView()
        ]);
    }

    /**
     * @Route("/user/{userId}", name="user_view", requirements={"userId"="\d+"})
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @return RedirectResponse|Response
     */
    public function view(Request $request, UserPasswordEncoderInterface $passwordEncoder)
    {
        $user = $this->getDoctrine()
            ->getRepository(User::class)
            ->find($request->get('userId'));

        if (empty($user)) {
            $this->addFlash('danger', 'Utilisateur introuvable');
            return $this->redirectToRoute('users', [], 302);
        }

        $form = $this->createForm(UserFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!empty($form->get('plainPassword')->getData())) {
                $user->setPassword($passwordEncoder->encodePassword($user, $form->get('plainPassword')->getData()));
            }

            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Utilisateur édité');
        }

        return $this->render('user/view.html.twig', [
            'active'    => $user->isActive(),
            'userId'    => $user->getId(),
            'form'      => $form->createView()
        ]);
    }
}
