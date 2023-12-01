<?php

namespace App\Controller;

use App\Entity\Parameter;
use App\Form\ParameterFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParameterController extends AbstractController
{
    /**
     * @Route("/parameters", name="parameters")
     *
     * @return Response
     */
    public function index()
    {
        $repository = $this->getDoctrine()->getRepository(Parameter::class);
        $forms['tva'] = ['label' => 'Taux de TVA', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'tva']), ['action' => $this->generateUrl('parameter', ['parameter' => 'tva'])])->createView()];
        $forms['invoice_footer'] = ['label' => 'Pied de page', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'invoice_footer']), ['action' => $this->generateUrl('parameter', ['parameter' => 'invoice_footer'])])->createView()];
        $forms['invoice_address'] = ['label' => 'Adresse', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'invoice_address']), ['action' => $this->generateUrl('parameter', ['parameter' => 'invoice_address'])])->createView()];
        $forms['invoice_postalcode'] = ['label' => 'Code postal', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'invoice_postalcode']), ['action' => $this->generateUrl('parameter', ['parameter' => 'invoice_postalcode'])])->createView()];
        $forms['invoice_city'] = ['label' => 'Ville', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'invoice_city']), ['action' => $this->generateUrl('parameter', ['parameter' => 'invoice_city'])])->createView()];
        $forms['invoice_phone'] = ['label' => 'Téléphone', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'invoice_phone']), ['action' => $this->generateUrl('parameter', ['parameter' => 'invoice_phone'])])->createView()];
        $forms['invoice_mail'] = ['label' => 'Adresse e-mail', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'invoice_mail']), ['action' => $this->generateUrl('parameter', ['parameter' => 'invoice_mail'])])->createView()];
        $forms['invoice_site'] = ['label' => 'Site', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'invoice_site']), ['action' => $this->generateUrl('parameter', ['parameter' => 'invoice_site'])])->createView()];
        $forms['iban'] = ['label' => 'IBAN', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'iban']), ['action' => $this->generateUrl('parameter', ['parameter' => 'iban'])])->createView()];
        $forms['bic'] = ['label' => 'BIC', 'form' => $this->createForm(ParameterFormType::class, $repository->findOneBy(['name' => 'bic']), ['action' => $this->generateUrl('parameter', ['parameter' => 'bic'])])->createView()];

        return $this->render('parameter/index.html.twig', [
            'forms' => $forms,
        ]);
    }

    /**
     * @Route("/parameter/edit/{parameter}", name="parameter")
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function edit(Request $request)
    {
        $param = $request->get('parameter');
        if (!in_array($param, ['tva', 'invoice_footer', 'invoice_address', 'invoice_postalcode', 'invoice_city', 'invoice_phone', 'invoice_mail', 'invoice_site', 'iban', 'bic'])) {
            $this->addFlash('danger', 'Paramètre non éditable');
            return $this->redirectToRoute('parameters', [], 302);
        }

        $parameter = $this->getDoctrine()
            ->getRepository(Parameter::class)
            ->findOneBy(['name' => $param]);

        if (empty($parameter)) {
            $this->addFlash('danger', 'Paramètre introuvable');
            return $this->redirectToRoute('parameters', [], 302);
        }

        $form = $this->createForm(ParameterFormType::class, $parameter);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Paramètre édité');
        }

        return $this->redirectToRoute('parameters', [], 302);
    }
}
