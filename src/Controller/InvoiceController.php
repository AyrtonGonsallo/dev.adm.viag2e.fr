<?php
/** @noinspection DuplicatedCode */

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\PendingInvoice;
use App\Entity\Property;
use App\Entity\Warrant;
use App\Form\InvoiceOTPType;
use App\Service\DriveManager;
use DateTime;
use PhpOffice\PhpSpreadsheet\Exception;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use function MongoDB\BSON\fromJSON;

class InvoiceController extends AbstractController
{
    private const LABEL_ANNUITY = 0;
    private const LABEL_HONORARIES = 1;

    private const LABELS = [self::LABEL_ANNUITY, self::LABEL_HONORARIES];

    private const TYPE_CREDIT = 0;
    private const TYPE_DEBIT = 1;

    private const TYPES = [self::TYPE_CREDIT, self::TYPE_DEBIT];

    private const RES_PER_PAGE = 50;

    /**
     * @Route("/invoice/export", name="invoice_export")
     *
     * @return Response
     */
    public function export()
    {
        return $this->render('invoice/export.html.twig', []);
    }

    /**
     * @Route("/invoice/accounting", name="invoice_accounting", methods={"POST"})
     *
     * @param Request $request
     * @return Response
     *
     * @throws Exception
     */
    public function generateAccountingFile(Request $request)
    {
        $range = $request->get('range');
        $wType = intval($request->get('type'));

        if (empty($range) || empty($wType)) {
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

        set_time_limit(0);
        ini_set('max_execution_time', 0);

        $invoices = $this->getDoctrine()->getManager()
            ->getRepository(Invoice::class)
            ->listByDate($start, $end);

        $spreadsheet = new Spreadsheet();
        /* @var $sheet Worksheet */
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Export " . (($wType == Warrant::TYPE_BUYERS) ? 'acquéreurs' : 'vendeurs'));

        $sheet->setCellValue('A1', 'DATES');
        $sheet->setCellValue('B1', 'JOURNAL');
        $sheet->setCellValue('C1', 'COMPTE GENERAL');
        $sheet->setCellValue('D1', 'NUMERO DE QUITTANCE');
        $sheet->setCellValue('E1', 'NUMERO CLIENT');
        $sheet->setCellValue('F1', 'TITRE DU BIEN');
        $sheet->setCellValue('G1', 'SOCIETE');
        $sheet->setCellValue('H1', 'LIBELLE (Honoraires/Rente)');
        $sheet->setCellValue('I1', 'DEBIT');
        $sheet->setCellValue('J1', 'CREDIT');

        $totals = [
            'annuities'         => 0,
            'honoraries'        => 0,
            'tax'               => 0,
            'condominiumFees'   => 0,
            'garbage'           => 0,
            'manual'            => 0,
            'manual_honoraries' => 0,
            'manual_taxes'      => 0,
        ];
        $y = 2;
        /* @var $invoice Invoice */
        foreach ($invoices as $invoice) {
            if ($invoice->getProperty()->getType() !== $wType) {
                continue;
            }

            $data = $invoice->getData();

            if($invoice->getCategory() == Invoice::CATEGORY_ANNUITY) {
                $totals['annuities']  += $data['property']['annuity'];
                $totals['honoraries'] += $data['property']['honoraryRates'];
                $totals['tax']        += $data['property']['honoraryRatesTax'];
            }
            elseif($invoice->getCategory() == Invoice::CATEGORY_CONDOMINIUM_FEES) {
                $totals['condominiumFees']  += $data['property']['condominiumFees'];
            }
            elseif($invoice->getCategory() == Invoice::CATEGORY_GARBAGE) {
                $totals['garbage']  += $data['amount'];
            }
            elseif($invoice->getCategory() == Invoice::CATEGORY_MANUAL) {
                $totals['manual']  += $data['amount'];
                if($data['honoraryRates'] > -1) {
                    $totals['manual_honoraries'] += $data['honoraryRates'];
                    $totals['manual_taxes'] += $data['honoraryRatesTax'];
                }
            }

            foreach (self::LABELS as $label) {
                foreach (self::TYPES as $type) {
                    if($invoice->getCategory() !== Invoice::CATEGORY_ANNUITY && $invoice->getCategory() !== Invoice::CATEGORY_MANUAL && $label != self::LABEL_ANNUITY) {
                        continue;
                    }

                    $sheet->setCellValue('A' . $y, $invoice->getDate()->format('d/m/Y'));
                    $sheet->setCellValue('B' . $y, 'QUI');
                    $sheet->setCellValue('D' . $y, Invoice::formatNumber($invoice->getNumber(), Invoice::TYPE_RECEIPT));
                    $sheet->setCellValue('E' . $y, $invoice->getProperty()->getWarrant()->getId());
                    $sheet->setCellValue('F' . $y, $invoice->getProperty()->getTitle());

                    if($invoice->getCategory() === Invoice::CATEGORY_ANNUITY) {
                        $sheet->setCellValue('G' . $y, $invoice->getProperty()->getWarrant()->getLastname() . ' ' . $invoice->getProperty()->getWarrant()->getFirstname());

                        $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                        $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                        if ($label == self::LABEL_ANNUITY) {
                            $sheet->setCellValue('H' . $y, 'Rente ' . ucwords($data['date']['month']));

                            if ($type == self::TYPE_CREDIT) {
                                $sheet->setCellValue('J' . $y, $data['property']['annuity']);
                            } else {
                                $sheet->setCellValue('I' . $y, $data['property']['annuity']);
                            }
                        } else {
                            $sheet->setCellValue('H' . $y, 'Honoraires ' . ucwords($data['date']['month']));

                            if ($type == self::TYPE_CREDIT) {
                                $sheet->setCellValue('J' . $y, $data['property']['honoraryRates']);
                            } else {
                                $sheet->setCellValue('I' . $y, $data['property']['honoraryRates']);
                            }
                        }
                    }
                    elseif($invoice->getCategory() === Invoice::CATEGORY_CONDOMINIUM_FEES) {
                        $sheet->setCellValue('G' . $y, $invoice->getProperty()->getWarrant()->getLastname() . ' ' . $invoice->getProperty()->getWarrant()->getFirstname());

                        $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                        $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                        $sheet->setCellValue('H' . $y, 'Avance trimestrielle des charges locatives de la copropriété ' . ucwords($data['date']['trimester']['text']. ' ' .$data['date']['trimester']['year']));

                        if ($type == self::TYPE_CREDIT) {
                            $sheet->setCellValue('J' . $y, $data['property']['condominiumFees']);
                        } else {
                            $sheet->setCellValue('I' . $y, $data['property']['condominiumFees']);
                        }
                    }
                    elseif($invoice->getCategory() === Invoice::CATEGORY_GARBAGE) {
                        $sheet->setCellValue('G' . $y, ($data['target'] == 1) ? $invoice->getProperty()->getWarrant()->getLastname() . ' ' . $invoice->getProperty()->getWarrant()->getFirstname() : $invoice->getProperty()->getLastname1() . ' ' . $invoice->getProperty()->getFirstname1());

                        $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                        $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                        $sheet->setCellValue('H' . $y, 'Taxe d\'enlèvement des ordures ménagères ' . (!empty($data['period']) ? ucwords($data['period']) : null));

                        if ($type == self::TYPE_CREDIT) {
                            $sheet->setCellValue('J' . $y, $data['amount']);
                        } else {
                            $sheet->setCellValue('I' . $y, $data['amount']);
                        }
                    }
                    elseif($invoice->getCategory() === Invoice::CATEGORY_MANUAL) {
                        $sheet->setCellValue('G' . $y, ($data['target'] == 1) ? $invoice->getProperty()->getWarrant()->getLastname() . ' ' . $invoice->getProperty()->getWarrant()->getFirstname() : $invoice->getProperty()->getLastname1() . ' ' . $invoice->getProperty()->getFirstname1());

                        $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                        $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                        $sheet->setCellValue('H' . $y, 'Facture manuelle ' . (!empty($data['period']) ? ucwords($data['period']) : null));

                        if ($label == self::LABEL_ANNUITY) {
                            $sheet->setCellValue('H' . $y, $data['label'] . ' ' . ucwords($data['date']['month']));
                            if ($type == self::TYPE_CREDIT) {
                                $sheet->setCellValue('J' . $y, $data['amount']);
                            } else {
                                $sheet->setCellValue('I' . $y, $data['amount']);
                            }
                        } elseif($data['honoraryRates'] > -1) {
                            $sheet->setCellValue('H' . $y, 'Honoraires ' . ucwords($data['date']['month']));

                            if ($type == self::TYPE_CREDIT) {
                                $sheet->setCellValue('J' . $y, $data['honoraryRates']);
                            } else {
                                $sheet->setCellValue('I' . $y, $data['honoraryRates']);
                            }
                        }
                    }

                    $y++;
                }
            }
        }

        foreach (self::TYPES as $type) {
            foreach ($invoices as $invoice) {
                if ($invoice->getProperty()->getType() !== $wType) {
                    continue;
                }

                $data = $invoice->getData();

                $sheet->setCellValue('A' . $y, $invoice->getDate()->format('d/m/Y'));
                $sheet->setCellValue('B' . $y, 'IMP');
                $sheet->setCellValue('D' . $y, Invoice::formatNumber($invoice->getNumber(), Invoice::TYPE_RECEIPT));
                $sheet->setCellValue('E' . $y, $invoice->getProperty()->getWarrant()->getId());
                $sheet->setCellValue('F' . $y, $invoice->getProperty()->getTitle());

                if($invoice->getCategory() === Invoice::CATEGORY_ANNUITY) {
                    $sheet->setCellValue('G' . $y, $invoice->getProperty()->getWarrant()->getLastname() . ' ' . $invoice->getProperty()->getWarrant()->getFirstname());

                    $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                    $sheet->setCellValue('H' . $y, 'APPEL QUITTANCE ' . strtoupper($data['date']['month']));

                    if ($type == self::TYPE_CREDIT) {
                        $sheet->setCellValue('J' . $y, $data['property']['annuity']);
                    } else {
                        $sheet->setCellValue('I' . $y, $data['property']['annuity']);
                    }
                }
                elseif($invoice->getCategory() === Invoice::CATEGORY_CONDOMINIUM_FEES) {
                    $sheet->setCellValue('G' . $y, $invoice->getProperty()->getWarrant()->getLastname() . ' ' . $invoice->getProperty()->getWarrant()->getFirstname());

                    $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                    $sheet->setCellValue('H' . $y, 'Avance trimestrielle des charges locatives de la copropriété ' . ucwords($data['date']['trimester']['text']. ' ' .$data['date']['trimester']['year']));

                    if ($type == self::TYPE_CREDIT) {
                        $sheet->setCellValue('J' . $y, $data['property']['condominiumFees']);
                    } else {
                        $sheet->setCellValue('I' . $y, $data['property']['condominiumFees']);
                    }
                }
                elseif($invoice->getCategory() === Invoice::CATEGORY_GARBAGE) {
                    $sheet->setCellValue('G' . $y, ($data['target'] == 1) ? $invoice->getProperty()->getWarrant()->getLastname() . ' ' . $invoice->getProperty()->getWarrant()->getFirstname() : $invoice->getProperty()->getLastname1() . ' ' . $invoice->getProperty()->getFirstname1());

                    $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                    $sheet->setCellValue('H' . $y, 'Taxe d\'enlèvement des ordures ménagères ' . (!empty($data['period']) ? ucwords($data['period']) : null));

                    if ($type == self::TYPE_CREDIT) {
                        $sheet->setCellValue('J' . $y, $data['amount']);
                    } else {
                        $sheet->setCellValue('I' . $y, $data['amount']);
                    }
                }
                elseif($invoice->getCategory() === Invoice::CATEGORY_MANUAL) {
                    $sheet->setCellValue('G' . $y, ($data['target'] == 1) ? $invoice->getProperty()->getWarrant()->getLastname() . ' ' . $invoice->getProperty()->getWarrant()->getFirstname() : $invoice->getProperty()->getLastname1() . ' ' . $invoice->getProperty()->getFirstname1());

                    $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                    $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                    $sheet->setCellValue('H' . $y, 'Facture manuelle ' . (!empty($data['period']) ? ucwords($data['period']) : null));

                    $sheet->setCellValue('H' . $y, $data['label'] . ' ' . ucwords($data['date']['month']));
                    if ($type == self::TYPE_CREDIT) {
                        $sheet->setCellValue('J' . $y, $data['amount']);
                    } else {
                        $sheet->setCellValue('I' . $y, $data['amount']);
                    }
                }

                $y++;
            }
        }

        $y = $y + 4;
        $sheet->setCellValue('H' . $y, 'TOTAL HONORAIRES');
        $sheet->setCellValue('I' . $y, 'TOTAL RENTES');
        $sheet->setCellValue('J' . $y, 'TOTAL TEOM');
        $sheet->setCellValue('K' . $y, 'TOTAL CO-PRO');
        $sheet->setCellValue('L' . $y, 'TOTAL MANUEL');
        $y++;
        $sheet->setCellValue('H' . $y, $totals['honoraries'] - $totals['tax']);
        $sheet->setCellValue('I' . $y, $totals['annuities']);
        $sheet->setCellValue('J' . $y, $totals['garbage']);
        $sheet->setCellValue('K' . $y, $totals['condominiumFees']);
        $sheet->setCellValue('L' . $y, $totals['manual'] + $totals['manual_honoraries'] - $totals['manual_taxes']);
        $sheet->getStyle('H' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('K' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('L' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $y++;
        $sheet->setCellValue('G' . $y, 'TVA 1.'. ((empty($data['tva'])) ? '20' : $data['tva']));
        $sheet->setCellValue('H' . $y, $totals['tax']);
        $sheet->setCellValue('I' . $y, '0.0');
        $sheet->setCellValue('J' . $y, '0.0');
        $sheet->setCellValue('K' . $y, '0.0');
        $sheet->setCellValue('L' . $y, $totals['manual_taxes']);
        $sheet->getStyle('H' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('K' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('L' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $y++;
        $sheet->setCellValue('H' . $y, $totals['honoraries']);
        $sheet->setCellValue('I' . $y, $totals['annuities']);
        $sheet->setCellValue('J' . $y, $totals['garbage']);
        $sheet->setCellValue('K' . $y, $totals['condominiumFees']);
        $sheet->setCellValue('L' . $y, $totals['manual'] + $totals['manual_honoraries']);
        $sheet->getStyle('H' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('I' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('J' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('K' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('L' . $y)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        foreach (range('A', 'L') as $letter) {
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }
        $sheet->calculateColumnWidths();

        $writer = new Xlsx($spreadsheet);
        $tmp_file = tempnam(sys_get_temp_dir(), uniqid());
        $writer->save($tmp_file);

        return $this->file($tmp_file, 'Export '. (($wType == Warrant::TYPE_BUYERS) ? 'acquéreurs' : 'vendeurs') .' '. $start->format('d-m-Y'). '_'. $end->format('d-m-Y') .'.xlsx', ResponseHeaderBag::DISPOSITION_INLINE);
    }

	
    /**
     * @Route("invoice/create/{propertyId}", name="invoice_create", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));

        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }

        $pendingInvoice = new PendingInvoice();
        $pendingInvoice->setProperty($property);
        $form = $this->createForm(InvoiceOTPType::class, $pendingInvoice, [
            'property'    => $property,
            'prop_locked' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($pendingInvoice->getTarget() === PendingInvoice::TARGET_BUYER && (!$property->getWarrant()->getType() === Warrant::TYPE_SELLERS || empty($property->getBuyerFirstname()))) {
                $this->addFlash('danger', 'La cible de la facture est incorrecte, "acheteur" ne peut être sélectionné que si le mandat est vendeur et que ses informations sont renseignées sur le profil du bien.');
            }
            else {
                $pendingInvoice->setCategory(Invoice::CATEGORY_MANUAL);

                $manager = $this->getDoctrine()->getManager();
                $manager->persist($pendingInvoice);
                $manager->flush();

                $this->addFlash('success', 'Facture enregistrée');
                return $this->redirectToRoute('property_view', ['propertyId' => $property->getId()]);
            }
        }

        return $this->render('invoice/otp.html.twig', ['form' => $form->createView(),'prop' => $property]);
    }
	
	/**
     * @Route("invoice/create/annuel/{propertyId}", name="create_annuel")
     *
     * @param Request $request
     * @return Response
     */
    public function create_annuel(Request $request)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));

        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }

        $pendingInvoice = new PendingInvoice();
        $pendingInvoice->setProperty($property);
        $form = $this->createForm(InvoiceOTPType::class, $pendingInvoice, [
            'property'    => $property,
            'prop_locked' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($pendingInvoice->getTarget() === PendingInvoice::TARGET_BUYER && (!$property->getWarrant()->getType() === Warrant::TYPE_SELLERS || empty($property->getBuyerFirstname()))) {
                $this->addFlash('danger', 'La cible de la facture est incorrecte, "acheteur" ne peut être sélectionné que si le mandat est vendeur et que ses informations sont renseignées sur le profil du bien.');
            }
            else {
                $pendingInvoice->setCategory(Invoice::CATEGORY_MANUAL);

                $manager = $this->getDoctrine()->getManager();
                $manager->persist($pendingInvoice);
                $manager->flush();

                $this->addFlash('success', 'Facture enregistrée');
                return $this->redirectToRoute('property_view', ['propertyId' => $property->getId()]);
            }
        }

        return $this->render('invoice/annuel.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/invoice/create/garbage/{propertyId}", name="invoice_create_garbage")
     *
     * @param Request $request
     * @return Response
     */
    public function create_garbage(Request $request)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));

        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }

        $pendingInvoice = new PendingInvoice();
        $pendingInvoice->setProperty($property);
        $form = $this->createForm(InvoiceOTPType::class, $pendingInvoice, [
            'amount'      => $property->getGarbageTax(),
            'label'       => 'Taxe d\'enlèvement des ordures ménagères',
            'property'    => $property,
            'reason'      => 'la taxe d\'enlèvement des ordures ménagères',
            'locked'      => true,
            'prop_locked' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($pendingInvoice->getTarget() === PendingInvoice::TARGET_BUYER && (!$property->getWarrant()->getType() === Warrant::TYPE_SELLERS || empty($property->getBuyerFirstname()))) {
                $this->addFlash('danger', 'La cible de la facture est incorrecte, "acheteur" ne peut être sélectionné que si le mandat est vendeur et que ses informations sont renseignées sur le profil du bien.');
            }
            else {
                $pendingInvoice->setCategory(Invoice::CATEGORY_GARBAGE);
                $pendingInvoice->setLabel('Taxe d\'enlèvement des ordures ménagères');
                $pendingInvoice->setReason('la taxe d\'enlèvement des ordures ménagères');

                $manager = $this->getDoctrine()->getManager();
                $manager->persist($pendingInvoice);
                $manager->flush();

                $this->addFlash('success', 'Facture enregistrée');
                return $this->redirectToRoute('property_view', ['propertyId' => $property->getId()]);
            }
        }

        return $this->render('invoice/otp.html.twig', ['form' => $form->createView(),'prop' => $property]);
    }

    /**
     * @Route("/invoices", name="invoices")
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        $range = $request->get('range');

        $start = new DateTime('-2 months');
        $end = new DateTime();

        $dates = explode(' - ', $range);
        if (count($dates) == 2) {
            $start = DateTime::createFromFormat('d/m/Y', $dates[0]);
            $end = DateTime::createFromFormat('d/m/Y', $dates[1]);
        }

        if ($start === false || $end === false) {
            $start = new DateTime('first day of 2 months ago');
            $end = new DateTime();
        }

        return $this->render('invoice/index.html.twig', [
            'range_start' => $start->format('d/m/Y'),
            'range_end' => $end->format('d/m/Y'),
        ]);
    }

    /**
     * @Route("/invoices/data", name="invoices_data")
     *
     * @param Request $request
     * @return Response
     */
    public function data(Request $request)
    {
        $pagination = $request->get('pagination');
        $query = $request->get('query');

        if(empty($query)) {
            $query = [];
        }

        $page = !empty($pagination['page']) ? $pagination['page'] : 1;
        $perpage = !empty($pagination['perpage']) ? $pagination['perpage'] : self::RES_PER_PAGE;

        $start = new DateTime('-2 months');
        $end = new DateTime();

        if(!empty($query['range'])) {
            $dates = explode(' - ', $query['range']);
            if (count($dates) == 2) {
                $start = DateTime::createFromFormat('d/m/Y', $dates[0]);
                $end   = DateTime::createFromFormat('d/m/Y', $dates[1]);
            }
        }

        if ($start === false || $end === false) {
            $start = new DateTime('first day of 2 months ago');
            $end = new DateTime();
        }

        $query['start'] = $start;
        $query['end'] = $end;

        $rep = $this->getDoctrine()->getManager()->getRepository(Invoice::class);

        $invoices = $rep->findAllOrdered($page, $perpage, $query);

        $data = [];

        /** @var Invoice $invoice */
        foreach ($invoices['data'] as $invoice) {
            
			$file2 = $invoice->getFile2();
            $file1 = $invoice->getFile();

            if($file2 && $file1){
                $file1=$invoice->getFile()->getDriveId();
                $file2_drive_id=$invoice->getFile2()->getDriveId();
                $lien_telechargements='<a href="'.$this->generateUrl('file_download', ['fileId' => $file1]).'" target="_blank"><i class="la la-cloud-download" title="Télécharger"></i> Rente</a> <br> <a href="'.$this->generateUrl('file_download', ['fileId' => $file2_drive_id]).'" target="_blank"><i class="la la-cloud-download" title="Télécharger"></i> Honoraires</a>';
			}
            elseif($file2){
                $file2_drive_id=$invoice->getFile2()->getDriveId();
                $lien_telechargements='<a href="'.$this->generateUrl('file_download', ['fileId' => $file2_drive_id]).'" target="_blank"><i class="la la-cloud-download" title="Télécharger"></i> Honoraires</a>';
            }
            elseif($file1){
                $file1=$invoice->getFile()->getDriveId();
                $lien_telechargements='<a href="'.$this->generateUrl('file_download', ['fileId' => $file1]).'" target="_blank"><i class="la la-cloud-download" title="Télécharger"></i> Rente</a>';
            }
            else{
                $lien_telechargements='Pas de fichier';
            }
			if($this->getTableAmount($invoice)<=-1){
                $amount='-';
            }
            else{
                $amount = $this->getTableAmount($invoice);
            }
            //Honoraire
            if($this->getTableHonoraryRates($invoice)=='-'){
                $honoraire=$this->getTableHonoraryRatesHt($invoice);
            }
            else{
                $honoraire=$this->getTableHonoraryRates($invoice);
            }
            if($invoice->getCategoryString()=='Rente'){
                $date=$invoice->getData()['date']["month_n"]."/".$invoice->getData()['date']["year"];
            }else if($invoice->getCategoryString()=='Frais de co-pro'){
                $date=$invoice->getData()['date']["year"];
                if($invoice->getData()['date']["month_n"]>9){
                    $date.='T4';
                }else if($invoice->getData()['date']["month_n"]>6){
                    $date.='T3';
                }
                else if($invoice->getData()['date']["month_n"]>3){
                    $date.='T2';
                }
                else if($invoice->getData()['date']["month_n"]>0){
                    $date.='T1';
                }
            }else{
                $date=$invoice->getDate()->format('m/Y');
            }


            $filePath = -1;
            $filePath2 = -1;
            $data2 = $invoice->getData();
            if($invoice->getFile()){
                $filePath = $invoice->getFile()->getDriveId();
            }
            if($invoice->getFile2()){
                $filePath2 = $invoice->getFile2()->getDriveId();
            }
            $cond_h_n=($filePath2 != -1)?true:false; //honoraires nuls ?
            $cond_r_n=($filePath != -1)?true:false; //rente nulle ?
            if($invoice->getCategory() == Invoice::CATEGORY_MANUAL){
                if($data2["target"]==1){//mandant
                    $mailTarget=$invoice->getProperty()->getWarrant()->getMail1();
                }else if($data2["target"]==2){//proprietaire du bien
                    $mailTarget=$invoice->getProperty()->getMail1();
                    if($invoice->getProperty()->getMail2()){
                        $mailTarget3=$invoice->getProperty()->getMail2();
                    }
                }else if($data2["target"]==3){//acheteur
                    $mailTarget=$invoice->getProperty()->getBuyerMail1();
                }
                else if($data2["target"]==4){//debirentier
                    $mailTarget=$invoice->getProperty()->getEmailDebirentier();
                }
                
            }else{
                if($data2['recursion'] ==Invoice::RECURSION_QUARTERLY){
                    $mailTarget=$invoice->getProperty()->getWarrant()->getMail1();
                    if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                        $mailTarget=$invoice->getProperty()->getWarrant()->getMail1();
                    }else{
                        $mailTarget=$invoice->getProperty()->getMail1();
                        if(!empty($invoice->getProperty()->getMail2())) {
                           $mailTarget3=$invoice->getProperty()->getMail2();
                        }
                    }
                  
                    
                }
                if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    if($cond_h_n){
                        $mailTarget2=$invoice->getProperty()->getWarrant()->getMail1();
                       
                    }
                    if($cond_r_n){
                        $mailTarget1=$invoice->getProperty()->getBuyerMail1();
                        
                    }
                }else{
                    if($cond_h_n){
                        $mailTarget2=$invoice->getProperty()->getWarrant()->getMail1();
                       
                    }
                    if($cond_r_n){
                        if($invoice->getProperty()->getDebirentierDifferent()){
                            $mailTarget1=$invoice->getProperty()->getEmailDebirentier();
    
                        }else{
                            $mailTarget1=$invoice->getProperty()->getWarrant()->getMail1();
    
                        }
                       
                    }
                }
                if($data2['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                    if($cond_h_n){
                        if(!empty($invoice->getMailCc())) {
                            $mailTarget3=$invoice->getMailCc();
                        }
        
                        
                    }
                    
              }else if($data2['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() != Warrant::TYPE_SELLERS){
                if($cond_h_n && $cond_r_n){
                    if(!empty($invoice->getMailCc())) {
                        $mailTarget3=$invoice->getMailCc();
                    }
                }    
                
              }
            }
            $recap_mails="";
            if($invoice->getCategory() == Invoice::CATEGORY_MANUAL){
                if($cond_r_n){
                    $recap_mails="la rente sera envoyée à ".$mailTarget;
                }
                if($cond_h_n){
                    $recap_mails="les honoraires seront envoyées à ".$mailTarget;
                }
                if($invoice->getProperty()->getMail2() && $data2['target'] ==2) {
                    $recap_mails.=" et ".$mailTarget3;
                }
            }
            else if($data2['recursion'] ==Invoice::RECURSION_QUARTERLY){
                $recap_mails="la rente sera envoyée à ".$mailTarget;
                if(!empty($invoice->getProperty()->getMail2())) {
                    $recap_mails.=" et ".$mailTarget3;
                }
            }else{
                if($cond_r_n){
                    $recap_mails="la rente sera envoyée à ".$mailTarget1;
                }
                if($cond_h_n){
                    $recap_mails.="les honoraires seront envoyés à ".$mailTarget2;
                }
                
            }
            $status_choice=($invoice->getStatus() >= Invoice::STATUS_PAYED) ? '<span class="'.$invoice->getStatusClass().'">'.$invoice->getStatusString().'</span>' : '<a id="invoice_'.$invoice->getId().'" href="#" data-id="'.$invoice->getId().'" data-number="'.$invoice->getFormattedNumber().'" data-toggle="modal" data-target="#m_modal_invoice_status" class="invoice-status m--font-bold '.$invoice->getStatusClass().'">'.$invoice->getStatusString().'</a>';
            if($invoice->getType()===2){
                $status_choice='<span class="'.$invoice->getStatusClass().'">'.$invoice->getStatusString().'</span>';
            }
           
            if($invoice->getCategory()===Invoice::CATEGORY_AVOIR){
                $resend='<a href="#" class="invoice-mail" data-id="'.$invoice->getId().'" data-message="'.$recap_mails.'" data-number="'.$invoice->getFormattedNumber().'" data-toggle="modal" data-target="#m_modal_invoice_mail"><i class="la la-envelope" title="Renvoyer"></i> Renvoyer</a>';
                $avoir=" relatif à ".$invoice->getData()["old_number"];
            }else{
                $avoir="";
                $resend='<a href="#" class="invoice-mail" data-id="'.$invoice->getId().'" data-message="'.$recap_mails.'" data-number="'.$invoice->getFormattedNumber().'" data-toggle="modal" data-target="#m_modal_invoice_mail"><i class="la la-envelope" title="Renvoyer"></i> Renvoyer</a>';
            }
            
            $data[] = [
                'Selected' =>"<input type='checkbox' name='invoice_".$invoice->getId()."' value='invoice_".$invoice->getId()."'>",
                'Date' => $date,
                'Number' => $invoice->getFormattedNumber().$avoir,
                'Category' => $invoice->getCategoryString(),
                'Type' => $invoice->getTypeString(),
                
                'Customer' => $this->getTablePayer($invoice),
                'Title' => '<a href="'.$this->generateUrl('property_view', ['propertyId' => $invoice->getProperty()->getId()]).'">'.$invoice->getProperty()->getTitle().'</a>',
                'Amount' => $amount,
                'HonoraryRates' => $honoraire,
                'Status' => $status_choice,
                //'Resend' => '<a href="'.$this->generateUrl('invoice_resend', ['invoiceId' => $invoice->getId()]).'"><i class="la la-envelope" title="Renvoyer"></i> Renvoyer</a>',
                'Resend' => $resend,
                
                'Download' => $lien_telechargements,
            ];
        }

        return JsonResponse::fromJsonString(json_encode([
            'post' => json_encode($_POST),
            'meta' => [
                'page'    => $page,
                'pages'   => $invoices['pagesCount'],
                'perpage' => $perpage,
                'total'   => $invoices['total'],
                'sort'    => 'asc',
                'field'   => 'Date'
            ], 
            'data' => $data
        ]));
    }

    public function getTableAmount(Invoice $invoice)
    {
        if ($invoice->getCategory() === Invoice::CATEGORY_CONDOMINIUM_FEES) {
            return number_format($invoice->getData()['property']['condominiumFees'], 2, '.', ' ');
        }
        elseif ($invoice->getCategory() === Invoice::CATEGORY_GARBAGE || $invoice->getCategory() === Invoice::CATEGORY_MANUAL) {
            return number_format($invoice->getData()['amount'],2, '.', ' ');
        }
        elseif ($invoice->getCategory() === Invoice::CATEGORY_AVOIR) {
            if(array_key_exists('annuity',$invoice->getData()['property']))
                return number_format($invoice->getData()['property']['annuity'],2, '.', ' ');
            else if(array_key_exists('condominiumFees',$invoice->getData()['property']) )
                return number_format($invoice->getData()['property']['condominiumFees'], 2, '.', ' ');
            else
                return number_format($invoice->getData()['amount'],2, '.', ' ');
        }
        else {
            return number_format($invoice->getData()['property']['annuity'],2, '.', ' ');
        }
    }

    public function getTableHonoraryRates(Invoice $invoice)
    {
        if ($invoice->getCategory() === Invoice::CATEGORY_ANNUITY) {
            return number_format($invoice->getData()['property']['honoraryRates'], 2, '.', ' ') . '(' . number_format($invoice->getData()['property']['honoraryRates'] - $invoice->getData()['property']['honoraryRatesTax'],2, '.', ' ') . ' HT)';
        }
        elseif ($invoice->getCategory() === Invoice::CATEGORY_MANUAL && $invoice->getData()['honoraryRates'] > -1) {
            return number_format($invoice->getData()['honoraryRates'],2, '.', ' ') . '(' . number_format($invoice->getData()['honoraryRates'] - $invoice->getData()['honoraryRatesTax'], 2, '.', ' ') . ' HT)';
        }
        elseif ($invoice->getCategory() === Invoice::CATEGORY_AVOIR) {
            if(array_key_exists('honoraryRates',$invoice->getData()['property']))
            return number_format($invoice->getData()['property']['honoraryRates'], 2, '.', ' ') . '(' . number_format($invoice->getData()['property']['honoraryRates'] - $invoice->getData()['property']['honoraryRatesTax'],2, '.', ' ') . ' HT)';
           else
                return '-';
        }
        else {
            return '-';
        }
    }
	
public function getTableHonoraryRatesHt(Invoice $invoice)
    {
       if ($invoice->getCategory() === Invoice::CATEGORY_MANUAL && $invoice->getData()['honoraryRates'] == -1 && $invoice->getData()['montantht'] >-1 ){
            return number_format(($invoice->getData()['montantht'])+($invoice->getData()['montantht']*0.2),2, '.', ' ') . '(' . number_format($invoice->getData()['montantht'], 2, '.', ' ') . ' HT)';
        }
        else {
            return '-';
        }
    }
	
    public function getTablePayer(Invoice $invoice)
    {
        if ($invoice->getCategory() === Invoice::CATEGORY_ANNUITY) {
            return '<a href="' . $this->generateUrl('warrant_view', ['type' => $invoice->getProperty()->getWarrant()->getTypeString(), 'warrantId' => $invoice->getProperty()->getWarrant()->getId()]) . '">' . $invoice->getProperty()->getWarrant()->getFirstname() . ' ' . $invoice->getProperty()->getWarrant()->getLastname() . '</a>';
        } elseif ($invoice->getCategory() === Invoice::CATEGORY_CONDOMINIUM_FEES) {
            return '<a href="' . $this->generateUrl('property_view', ['propertyId' => $invoice->getProperty()->getId()]) . '">' . $invoice->getProperty()->getFirstname1() . ' ' . $invoice->getProperty()->getLastname1() . '</a>';
        }else  if ($invoice->getCategory() === Invoice::CATEGORY_AVOIR) {
            return '<a href="' . $this->generateUrl('warrant_view', ['type' => $invoice->getProperty()->getWarrant()->getTypeString(), 'warrantId' => $invoice->getProperty()->getWarrant()->getId()]) . '">' . $invoice->getProperty()->getWarrant()->getFirstname() . ' ' . $invoice->getProperty()->getWarrant()->getLastname() . '</a>';
        }
        else {
            if ($invoice->getData()['target'] === PendingInvoice::TARGET_WARRANT) {
                return '<a href="'.$this->generateUrl('warrant_view', ['type' => $invoice->getProperty()->getWarrant()->getTypeString(), 'warrantId' => $invoice->getProperty()->getWarrant()->getId()]).'">'.$invoice->getProperty()->getWarrant()->getFirstname().' '.$invoice->getProperty()->getWarrant()->getLastname().'</a>';
            }
            if ($invoice->getData()['target'] === 4) {
                return $invoice->getProperty()->getNomDebirentier().' '.$invoice->getProperty()->getPrenomDebirentier();
            }
            elseif ($invoice->getData()['target'] === PendingInvoice::TARGET_PROPERTY) {
                return '<a href="' . $this->generateUrl('property_view', ['propertyId' => $invoice->getProperty()->getId()]) . '">' . $invoice->getProperty()->getFirstname1() . ' ' . $invoice->getProperty()->getLastname1() . '</a>';
            }
            else {
                return '<a href="' . $this->generateUrl('property_view', ['propertyId' => $invoice->getProperty()->getId()]) . '?buyer">' . $invoice->getProperty()->getBuyerFirstname() . ' ' . $invoice->getProperty()->getBuyerLastname() . '</a>';
            }
        }
    }

    /**
     * @Route("/invoice/payed", name="invoice_payed")
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function payed(Request $request)
    {
        if (!is_numeric($request->get('id'))) {
            return $this->redirectToRoute('invoices');
        }

        $invoice = $this->getDoctrine()
            ->getRepository(Invoice::class)
            ->find($request->get('id'));

        if (!empty($invoice) && $invoice->getStatus() < Invoice::STATUS_PAYED) {
            $invoice->setStatus(Invoice::STATUS_PAYED);
            $this->getDoctrine()->getManager()->flush();
            $this->addFlash('success', 'Facture marquée comme payée');
        } else {
            $this->addFlash('danger', 'Facture introuvable');
        }

        if (empty($request->get('treat'))) {
            return $this->redirectToRoute('invoices');
        }

        return $this->redirectToRoute('invoices_treat');
    }

    /**
     * @Route("/invoice/check_and_resend_mails", name="invoice_check_and_resend_mails")
     * 
     * @param Request $request
     * @param DriveManager $driveManager
     * @param Swift_Mailer $mailer
     * @return Response
     */
    public function check_and_resend_mails(Request $request,DriveManager $driveManager, Swift_Mailer $mailer)
    {
        if (!is_numeric($request->get('id'))) {
            return $this->redirectToRoute('invoices');
        }

        $invoice = $this->getDoctrine()
            ->getRepository(Invoice::class)
            ->find($request->get('id'));

       
            if (!empty($invoice)) {
                if($invoice->getFile()){
                    //$filePath = $driveManager->getFile($invoice->getFile());
                    $filePath = "mis pour tester";
                }else{
                    $filePath = null;
                }
                if($invoice->getFile2()){
                    //$filePath2 = $driveManager->getFile($invoice->getFile2());
                    $filePath2 = "mis pour tester";
                }else{
                    $filePath2 = null;
                }
                $data = $invoice->getData();
                $cond_h_n=($filePath2)?true:false; //honoraires non nuls ?
                $cond_r_n=($filePath)?true:false; //rente non nulle ?
                
                if($invoice->getCategory() == Invoice::CATEGORY_MANUAL){
                    $message = (new Swift_Message($invoice->getMailSubject()))
                        ->setFrom($this->getParameter('mail_from'))
                        ->setBcc($this->getParameter('mail_from'))
                        ->setBody($this->renderView('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                    if($data["target"]==1){//mandant
                        $mailTarget=$invoice->getProperty()->getWarrant()->getMail1();
                    }else if($data["target"]==2){//proprietaire du bien
                        $mailTarget=$invoice->getProperty()->getMail1();
                        if($invoice->getProperty()->getMail2()){
                            $mailTarget3=$invoice->getProperty()->getMail2();
                            $message->setCc("roquetigrinho@gmail.com");
                        }
                    }else if($data["target"]==3){//acheteur
                        $mailTarget=$invoice->getProperty()->getBuyerMail1();
                    }
                    else if($data["target"]==4){//debirentier
                        $mailTarget=$invoice->getProperty()->getEmailDebirentier();
                    }
                    $message ->setTo("roquetigrinho@gmail.com");
                
                    if($cond_h_n){
                        //$message->attach(Swift_Attachment::fromPath($filePath2));
                    }
                    //envoyer la rente au buyer /acquereur/acheteur
                    if($cond_r_n){
                        //$message->attach(Swift_Attachment::fromPath($filePath));
                    }
                    
                }else{
                    if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                        if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                            $message = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->getParameter('mail_from'))
                                ->setBcc($this->getParameter('mail_from'))
                                ->setTo("roquetigrinho@gmail.com")
                                ->setBody($this->renderView('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                                //->attach(Swift_Attachment::fromPath($filePath));
                        }else{
                            $message = (new Swift_Message($invoice->getMailSubject()))
                                ->setFrom($this->getParameter('mail_from'))
                                ->setBcc($this->getParameter('mail_from'))
                                ->setTo("roquetigrinho@gmail.com")
                                ->setBody($this->renderView('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                                //->attach(Swift_Attachment::fromPath($filePath));
                                
                                if(!empty($invoice->getProperty()->getMail2())) {
                                    $message->setCc("roquetigrinho@gmail.com");
                                }
                        }
                    }
                    else{
                        if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                            //si mandat vendeur
                            //envoyer les honoraires aux mandant
                            if($cond_h_n){
                                $message1 = (new Swift_Message($invoice->getMailSubject()))
                                    ->setFrom($this->getParameter('mail_from'))
                                    ->setBcc($this->getParameter('mail_from'))
                                    ->setTo("roquetigrinho@gmail.com")
                                    ->setBody($this->renderView('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                                    //->attach(Swift_Attachment::fromPath($filePath2));
                            }
                            //envoyer la rente au buyer /acquereur/acheteur
                            if($cond_r_n){
                                $message2 = (new Swift_Message($invoice->getMailSubject()))
                                    ->setFrom($this->getParameter('mail_from'))
                                    ->setBcc($this->getParameter('mail_from'))
                                    ->setTo("roquetigrinho@gmail.com")
                                    ->setBody($this->renderView('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                                    //->attach(Swift_Attachment::fromPath($filePath));
                            }
                        }else{
                            //si mandat acquereur                                
                            if($cond_h_n){
                                $message = (new Swift_Message($invoice->getMailSubject()))
                                    ->setFrom($this->getParameter('mail_from'))
                                    ->setBcc($this->getParameter('mail_from'))
                                    ->setTo("roquetigrinho@gmail.com")
                                    ->setBody($this->renderView('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                                    //->attach(Swift_Attachment::fromPath($filePath))
                                    //->attach(Swift_Attachment::fromPath($filePath2));
                            }
							else if($cond_r_n){
                                $message = (new Swift_Message($invoice->getMailSubject()))
                                    ->setFrom($this->getParameter('mail_from'))
                                    ->setBcc($this->getParameter('mail_from'))
                                    ->setTo("roquetigrinho@gmail.com")
                                    ->setBody($this->renderView('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                                    //->attach(Swift_Attachment::fromPath($filePath))
                                    //->attach(Swift_Attachment::fromPath($filePath2));
                            }
                        }
                    }
                }
                
                if($invoice->getCategory() == Invoice::CATEGORY_MANUAL){
                    
    
                    if ($mailer->send($message)) {
                        $invoice->setStatus(Invoice::STATUS_SENT);
                    } else {
                        $invoice->setStatus(Invoice::STATUS_UNSENT);
                    }
                }
                else{
                    if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                        if($cond_h_n){
                            if(!empty($invoice->getMailCc())) {
                                $message1->setCc("roquetigrinho@gmail.com");
                            }
            
                            if ($mailer->send($message1)) {
                                $invoice->setStatus(Invoice::STATUS_SENT);
                            } else {
                                $invoice->setStatus(Invoice::STATUS_UNSENT);
                            }
                        }
                        if($cond_r_n){
                            if ($mailer->send($message2)) {
                                $invoice->setStatus(Invoice::STATUS_SENT);
                            } else {
                                $invoice->setStatus(Invoice::STATUS_UNSENT);
                            }
                        }
                    }else if($data['recursion'] !=Invoice::RECURSION_QUARTERLY && $invoice->getProperty()->getWarrant()->getType() != Warrant::TYPE_SELLERS){
                        if($cond_h_n || $cond_r_n){
                            if(!empty($invoice->getMailCc())) {
                                $message->setCc("roquetigrinho@gmail.com");
                            }
            
                            if ($mailer->send($message)) {
                                $invoice->setStatus(Invoice::STATUS_SENT);
                            } else {
                                $invoice->setStatus(Invoice::STATUS_UNSENT);
                            }
                        }    
                        
                    }else if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                        if(!empty($invoice->getMailCc())) {
                            $message->setCc("roquetigrinho@gmail.com");
                        }
        
                        if ($mailer->send($message)) {
                            $invoice->setStatus(Invoice::STATUS_SENT);
                        } else {
                            $invoice->setStatus(Invoice::STATUS_UNSENT);
                        }
                    }
                }
                
            
            } else {
                $this->addFlash('danger', 'Facture introuvable');
            }




    
            return new JsonResponse(['rente non nulle' => $cond_r_n,"honoraire non nuls"=> $cond_h_n,"results"=>true,"fp"=>$filePath,"fp2"=>$filePath2],);
    }

    /**
     * @Route("/invoice/resend/{invoiceId}", name="invoice_resend", requirements={"invoiceId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @param Swift_Mailer $mailer
     * @return RedirectResponse
     */
    public function resend(Request $request, DriveManager $driveManager, Swift_Mailer $mailer)
    {
        /** @var Invoice $invoice */
        $invoice = $this->getDoctrine()
            ->getRepository(Invoice::class)
            ->find($request->get('invoiceId'));

        if (!empty($invoice)) {
            if($invoice->getFile()){
                $filePath = $invoice->getFile()->getDriveId();
            }
            if($invoice->getFile2()){
                $filePath2 = $invoice->getFile2()->getDriveId();
            }
            $data = $invoice->getData();
            $mailTarget="";
            $cond_h_n=($filePath2 != -1)?true:false; //honoraires nuls ?
            $cond_r_n=($filePath != -1)?true:false; //rente nulle ?
            $message = (new Swift_Message($invoice->getMailSubject()))
                ->setFrom($this->getParameter('mail_from'))
                ->setBcc($this->getParameter('mail_from'));
            if($data['recursion'] ==Invoice::RECURSION_QUARTERLY){
                $mailTarget=$invoice->getProperty()->getWarrant()->getMail1();
                if($invoice->getFile()){
                    //$message ->attach(Swift_Attachment::fromPath($filePath));
                }
            }
            if($invoice->getProperty()->getWarrant()->getType() === Warrant::TYPE_SELLERS){
                if($cond_h_n){
                    $mailTarget=$invoice->getProperty()->getWarrant()->getMail1();
                    if($invoice->getFile()){
                        //$message ->attach(Swift_Attachment::fromPath($filePath2));
                    }
                }
                if($cond_r_n){
                    $mailTarget=$invoice->getProperty()->getBuyerMail1();
                    if($invoice->getFile()){
                        //$message ->attach(Swift_Attachment::fromPath($filePath));
                    }
                }
            }else{
                if($cond_h_n && $cond_r_n){
                    $mailTarget=$invoice->getProperty()->getWarrant()->getMail1();
                    if($invoice->getFile()){
                        //$message ->attach(Swift_Attachment::fromPath($filePath))
                        //->attach(Swift_Attachment::fromPath($filePath2));
                    }
                }
            }
            $message->setTo("roquetigrinho@gmail.com")
                ->setBody($this->renderView('invoices/emails/notice_expiry.twig', ['type' => strtolower($invoice->getTypeString()), 'date' => "{$data['date']['month']} {$data['date']['year']}"]), 'text/html');
                

            if (!empty($invoice->getMailCc())) {
                $message->setCc("roquetigrinho@gmail.com");
            }

            if ($mailer->send($message)) {
                if ($invoice->getStatus() < Invoice::STATUS_SENT) {
                    $invoice->setStatus(Invoice::STATUS_SENT);
                }
                $this->getDoctrine()->getManager()->flush();
                $this->addFlash('success', 'Facture renvoyée');
            } else {
                $this->addFlash('danger', 'Echec de l\'envoi');
            }
        } else {
            $this->addFlash('danger', 'Facture introuvable');
        }

        return $this->redirectToRoute('invoices');
    }






/**
     *@Route(name="get_selected_invoices",path="/get_selected_invoices/{type}")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function get_selected_invoices(Request $request)
    {
        $type=$request->get('type');
        $ids=$request->get('ids');
        $results=array();
        $manager = $this->getDoctrine()->getManager();

        if($type=="valider-tous"){
            foreach ($ids as $id) {
                if($id["id"]>0){
                    $invoice = $this->getDoctrine()
                    ->getRepository(Invoice::class)
                    ->find($id["id"]);
                    if($invoice->getStatus()<4){
                        array_push($results,$invoice);
                        $invoice->setStatus(Invoice::STATUS_PAYED);
                        $manager->persist($invoice);
                    }
                }
                
            }
            $rep="validés";
        }else if($type=="télécharger-tous"){
            foreach ($ids as $id) {
                if($id["id"]>0){
                    $invoice = $this->getDoctrine()
                    ->getRepository(Invoice::class)
                    ->find($id["id"]);
                    if($invoice->getFile()){
                        $file=$invoice->getFile();
                        array_push($results,array('/file/download/'.$file->getDriveId(),$file));

                    }
                    if($invoice->getFile2()){
                        $file2=$invoice->getFile2();
                        array_push($results,array('/file/download/'.$file2->getDriveId(),$file2));
                    }
                }
                
            }
            $rep="télécharger";

        }
        $manager->flush();
        return new JsonResponse(['selected_data' => $ids,"results"=> $results,"type"=> $type],);
    }






}
