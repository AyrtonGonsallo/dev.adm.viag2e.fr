<?php

namespace App\Controller;

use App\Service\DriveManager;
use DateTime;
use App\Entity\BankExport;
use App\Service\Bank;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class BankController extends AbstractController
{
    /**
     * @Route("/bank/exports", name="bank_exports")
     *
     * @return Response
     */
    public function index()
    {
        $exports = $this->getDoctrine()->getManager()
            ->getRepository(BankExport::class)
            ->findAllOrdered();

        return $this->render('bank/index.html.twig', [
            'exports' => $exports,
        ]);
    }

    /**
     * @Route("/bank/generate", name="bank_generate", methods={"POST"})
     *
     * @param Request $request
     * @return Response
     *
     * @throws \Exception
     */
    public function generateBankFile(Request $request, DriveManager $drive)
    {
        $range = $request->get('range');

        if (empty($range)) {
            throw new BadRequestHttpException('Missing parameter');
        }

        $dates = explode(' - ', $range);
        if (count($dates) != 2) {
            throw new NotFoundHttpException('Invalid range');
        }

        $start = DateTime::createFromFormat('d/m/Y', $dates[0]);
        $end = DateTime::createFromFormat('d/m/Y', $dates[1]);

        if ($start === false || $end === false) {
            throw new NotFoundHttpException('Invalid range');
        }

        $bank = new Bank($this->container);
        $export = new BankExport();

        $xml = $bank->generate($start, $end, $export->getMessageId());

        $export->setDate(new DateTime());
        $export->setPeriod($range);
        $export->setType(BankExport::TYPE_MANUAL);
        $manager = $this->getDoctrine()->getManager();
        $manager->persist($export);


        if($this->getParameter('kernel.environment') != 'prod' || true) { // Tmp GDrive fix
            $export->setDriveId('notstored');
            $manager->flush();

            $response = new Response($xml);
            $disposition = HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                'export_'. date('d-m-Y') .'.xml'
            );
            $response->headers->set('Content-Disposition', $disposition);
            return $response;
        }

        $export->setDriveId($drive->addExport($export->getName(), $xml));
        $manager->flush();

        return $this->redirectToRoute('file_export_download', ['fileId' => $export->getDriveId()]);
    }


    /**
     * @Route("/bank/generate_fa", name="bank_generate_fa", methods={"POST"})
     *
     * @param Request $request
     * @return Response
     *
     * @throws \Exception
     */
    public function generateFABankFile(Request $request, DriveManager $drive)
    {
        $range = $request->get('range');

        if (empty($range)) {
            throw new BadRequestHttpException('Missing parameter');
        }

        $dates = explode(' - ', $range);
        if (count($dates) != 2) {
            throw new NotFoundHttpException('Invalid range');
        }

        $start = DateTime::createFromFormat('d/m/Y', $dates[0]);
        $end = DateTime::createFromFormat('d/m/Y', $dates[1]);

        if ($start === false || $end === false) {
            throw new NotFoundHttpException('Invalid range');
        }

        $bank = new Bank($this->container);
        $export = new BankExport();

        $xml = $bank->generate_fa($start, $end, $export->getMessageId());

        $export->setDate(new DateTime());
        $export->setPeriod($range);
        $export->setType(BankExport::TYPE_MANUAL);
        $manager = $this->getDoctrine()->getManager();
        $manager->persist($export);


        if($this->getParameter('kernel.environment') != 'prod' || true) { // Tmp GDrive fix
            $export->setDriveId('notstored');
            $manager->flush();

            $response = new Response($xml);
            $disposition = HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                'export_'. date('d-m-Y') .'.xml'
            );
            $response->headers->set('Content-Disposition', $disposition);
            return $response;
        }

        $export->setDriveId($drive->addExport($export->getName(), $xml));
        $manager->flush();

        return $this->redirectToRoute('file_export_download', ['fileId' => $export->getDriveId()]);
    }
}
