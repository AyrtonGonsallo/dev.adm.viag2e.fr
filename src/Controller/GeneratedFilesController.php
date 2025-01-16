<?php

namespace App\Controller;

use App\Entity\BankExport;
use App\Entity\File;
use App\Entity\Warrant;
use App\Entity\Invoice;
use App\Entity\Property;
use App\Form\FileFormType;
use App\Entity\Parameter;
use App\Service\DriveManager;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Html2Pdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Container\ContainerInterface;

setlocale(LC_TIME, 'fr_FR.utf8', 'French', 'French_France'); // dates in french
class GeneratedFilesController extends AbstractController
{
    private $path;
    private $twig;
    private $pdf_logo;
    public function __construct(ContainerInterface $container, ParameterBagInterface $params)
    {
        $this->path     = $params->get('pdf_tmp_dir');
        $this->pdf_logo = $params->get('pdf_logo_path');
        $this->twig     = $container->get('twig');
    }
    /**
     * @Route("/warrant/file/add/{warrantId}", name="file_add", requirements={"warrantId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function add(Request $request, DriveManager $driveManager)
    {
        $warrant = $this->getDoctrine()
            ->getRepository(Warrant::class)
            ->find($request->get('warrantId'));

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $file = new File();
        $form = $this->createForm(FileFormType::class, $file, ['action' => $this->generateUrl('file_add', ['warrantId' => $warrant->getId()])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $f */
            $f = $file->getDriveId();
            $file->setTmpName(md5(uniqid()).'.'.$f->guessExtension());
            $file->setMime($f->getMimeType());
            $f->move($this->getParameter('tmp_files_dir'), $file->getTmpName());

            $filePath = $this->getParameter('tmp_files_dir').'/'.$file->getTmpName();
            $file->setDriveId($driveManager->addFile($file->getName(), $filePath, File::TYPE_DOCUMENT, $warrant->getId()));
            $file->setType(File::TYPE_DOCUMENT);
            $file->setWarrant($warrant);
            $manager = $this->getDoctrine()->getManager();
            $manager->persist($file);
            $manager->flush();

            unlink($filePath);
        }

        return $this->redirectToRoute('warrant_view', ['type' => Warrant::getTypeName($warrant->getType()), 'warrantId' => $warrant->getId()]);
    }

    /**
     * @Route("/file/delete/{fileId}", name="file_delete")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function delete(Request $request, DriveManager $driveManager)
    {
        $file = $this->getDoctrine()
            ->getRepository(File::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        $route = $this->generateUrl('warrant_view', ['type' => $file->getWarrant()->getTypeString(), 'warrantId' => $file->getWarrant()->getId()]);

        if (empty($file) || $file->getType() != File::TYPE_DOCUMENT) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        if ($driveManager->trashFile($file) === true) {
            $manager = $this->getDoctrine()->getManager();
            $manager->remove($file);
            $manager->flush();

            $this->addFlash('success', 'Fichier supprimé');
            return $this->redirect($route);
        }

        $this->addFlash('danger', 'Une erreur a eu lieu pendant la suppression');
        return $this->redirect($route);
    }



    /**
     * @Route("/property/file/add/{propertyId}", name="property_file_add", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function add_property_file(Request $request, DriveManager $driveManager)
    {
        $property = $this->getDoctrine()
        ->getRepository(Property::class)
        ->find($request->get('propertyId'));

        $warrant = $property->getWarrant();

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $file = new File();
        $form = $this->createForm(FileFormType::class, $file, ['action' => $this->generateUrl('file_add', ['warrantId' => $warrant->getId()])]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $f */
            $f = $file->getDriveId();
            $file->setTmpName(md5(uniqid()).'.'.$f->guessExtension());
            $file->setMime($f->getMimeType());
            $file->setDate(new DateTime());
            $f->move($this->getParameter('tmp_files_dir'), $file->getTmpName());

            $filePath = $this->getParameter('tmp_files_dir').'/'.$file->getTmpName();
            $file->setDriveId($driveManager->addFile($file->getName(), $filePath, File::TYPE_DOCUMENT, $warrant->getId()));
            $file->setType(File::TYPE_DOCUMENT);
            $file->setWarrant($warrant);

            $manager = $this->getDoctrine()->getManager();
            $manager->persist($file);
            $manager->flush();

            unlink($filePath);
        }

        return $this->redirectToRoute('property_view', [ 'propertyId' => $property->getId()]);
    }

    /**
     * @Route("/generated_files/mandat_sepa/{propertyId}", name="generate_mandat_sepa", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_mandat_sepa(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
        $parameters = [
            'tva'        => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva'])->getValue(),
            'footer'     => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];
        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }
        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('mode_de_paiement', ChoiceType::class, [
            'choices'  => [
                'Paiement récurrent / répétitif' => "Paiement récurrent / répétitif",
                'Paiement ponctuel' => "Paiement ponctuel",
            ],
        ])
        ->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $fileName = "MandatSEPA-".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                try {
                    $pdf->pdf->SetDisplayMode('fullpage');
                    $data = [
                        'date'       => $now_date,
                        'form'       => $form->getData(),
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                    ];
                    $pdf->writeHTML($this->twig->render('generated_files/mandat-sepa-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    $pdf->output('/var/www/vhosts/adm.viag2e.fr/adm.viag2e.fr/var/tmp/pdf/'. $fileName, 'F');
                    
                    $file = new File();
                    $file->setType(File::TYPE_DOCUMENT);
                    $file->setName($fileName);
                    $file->setWarrant($property->getWarrant());
                    $file->setProperty($property);
                    $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                    $manager = $this->getDoctrine()->getManager();
                    $manager->persist($file);
                    $manager->flush();

                    return $this->render('generated_files/mandat-sepa.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => 'fichier mandat sepa créé avec succès',
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/mandat-sepa.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
        ]);
    }


/**
     * @Route("/generated_files/mandat_courrier_mandat_prelevement_sepa/{propertyId}", name="generate_courrier_mandat_prelevement_sepa", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_mandat_prelevement_sepa(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
        $parameters = [
            'tva'        => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva'])->getValue(),
            'footer'     => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];
        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }
        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('formule', ChoiceType::class, [
            'choices'  => [
                'Cher Monsieur' => "Cher Monsieur",
                'Chère Madame' => "Chère Madame",
            ],
            
        ])
        ->add('civilite', ChoiceType::class, [
            'choices'  => [
                'Monsieur' => "Monsieur",
                'Madame' => "Madame",
            ],
            
        ])
        ->getForm();
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $fileName = "Courrier envoi de mandat de prélèvement SEPA -".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                try {
                    $pdf->pdf->SetDisplayMode('fullpage');
                    $data = [
                        'date'       => $now_date,
                        'form'       => $form->getData(),
                        'current_day'       => strftime("%d %B %Y", strtotime( $now_date->format('d-m-Y') )),
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                    ];
                    $pdf->writeHTML($this->twig->render('generated_files/courrier-mandat-sepa-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    $pdf->output('/var/www/vhosts/adm.viag2e.fr/adm.viag2e.fr/var/tmp/pdf/'. $fileName, 'F');
                    
                    $file = new File();
                    $file->setType(File::TYPE_DOCUMENT);
                    $file->setName($fileName);
                    $file->setWarrant($property->getWarrant());
                    $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                    $manager = $this->getDoctrine()->getManager();
                    $manager->persist($file);
                    $manager->flush();

                    return $this->render('generated_files/courrier-mandat-sepa.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => 'fichier mandat sepa créé avec succès',
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-mandat-sepa.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
        ]);
    }

    /**
     * @Route("/generated_files/courrier_regularisation_charges_copro/{propertyId}", name="generate_courrier_regularisation_charges_copro", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_regularisation_charges_copro(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
        $parameters = [
            'tva'        => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva'])->getValue(),
            'footer'     => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];
        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }
        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('formule', ChoiceType::class, [
            'choices'  => [
                'Cher Monsieur' => "Cher Monsieur",
                'Chère Madame' => "Chère Madame",
            ],
            
        ])
        ->add('civilite', ChoiceType::class, [
            'choices'  => [
                'Monsieur' => "Monsieur",
                'Madame' => "Madame",
            ],
            
        ])
        ->add('date_reg_fin', DateType::class, [ 
            'format' => 'dd-MMM-yyyy',
            'years' => range(2010, date('Y') + 2),
        ])
        ->add('date_reg_debut', DateType::class, [
             'format' => 'dd-MMM-yyyy', 
             'years' => range(2010, date('Y') + 2),
        ])
        ->add('regul', TextType::class, [
            'required' => false,
        ])
        ->add('partie_accessoire', CheckboxType::class, [
            'required' => false
            ])
        ->add('date_partie_accessoire', DateType::class, [ 
            'required' => false,
            'format' => 'dd-MMM-yyyy',
            'years' => range(2010, date('Y') + 2),
        ])
        ->add('montant_partie_accessoire', TextType::class, [
            'required' => false,
        ])
        ->add('debit', TextType::class, [
            'required' => false,
        ])
        ->add('pieces_jointes', FileType::class, [
            'required' => false,
            'multiple' => true,
            'attr'     => [
                'accept' => 'application/pdf',
                'multiple' => 'multiple'
            ]
        ])
        ->getForm();
        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                $regul = $data["regul"];
                $debit = $data["debit"];
                $date_reg_fin = $data["date_reg_fin"];
                $date_reg_debut = $data["date_reg_debut"];
                $pieces_jointes = $data["pieces_jointes"];
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $fileName = "Courrier de régularisation des charges de copropriété -".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";

                $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
                ->select("inv")
                ->from('App\Entity\Invoice', 'inv')
                ->where('inv.property = :property AND inv.category = 1 AND inv.type=1 AND inv.status=5 AND inv.file2 is null AND inv.date BETWEEN :start AND :end')
                ->setParameter('property', $property)
                ->setParameter('start', $date_reg_debut)
                ->setParameter('end', $date_reg_fin)
                    ->orderBy('inv.date', 'ASC');
                $query = $qb->getQuery();
                // Execute Query
                $invs = $query->getResult();
                $factures = array();
                $credit=0;
                foreach ($invs as  $inv) {
                    if($inv->getDate()->format('m')>9){
                        $trimestre='Trimestre 4';
                    }else if($inv->getDate()->format('m')>6){
                        $trimestre='Trimestre 3';
                    }
                    else if($inv->getDate()->format('m')>3){
                        $trimestre='Trimestre 2';
                    }
                    else if($inv->getDate()->format('m')>0){
                        $trimestre='Trimestre 1';
                    }

                    if ($inv->getCategory() === Invoice::CATEGORY_CONDOMINIUM_FEES) {
                        $montant = number_format($inv->getData()['property']['condominiumFees'], 2, '.', ' ');
                    }
                    elseif ($inv->getCategory() === Invoice::CATEGORY_GARBAGE || $inv->getCategory() === Invoice::CATEGORY_MANUAL) {
                        $montant = number_format($inv->getData()['amount'],2, '.', ' ');
                    }
                    else {
                        $montant = number_format($inv->getData()['property']['annuity'],2, '.', ' ');
                    }
                    $facture    = [
                        'year'         => $inv->getDate()->format('Y'),
                        'date'         => $inv->getDate()->format('d/m/Y'),
                        'montant'       => $montant,
                        'trimestre'  => $trimestre,
                        'numero'   => $inv->getNumber(),
                    ];
                    array_push($factures, $facture);
                    $credit+= $montant;
                }
                $pjs = array();
                foreach ($pieces_jointes as  $pieces_jointe) {
                    $pj    = [
                        'title'    => $pieces_jointe->getClientOriginalName(),
                    ];
                    array_push($pjs, $pj);
                }
                $now_date=new DateTime();
                $data = [
                    'date'       => $now_date,
                    'current_day'       => strftime("%d %B %Y", strtotime( $now_date->format('d-m-Y') )),
                    'annee'       => $now_date->format('Y'),
                    'date_a_f'       => $now_date->format('d/m/Y'),
                    'date_d_f'       => $date_reg_debut->format('d/m/Y'),
                    'date_f_f'       => $date_reg_fin->format('d/m/Y'),
                    'amount'           => abs($regul),
                    'period2'           => $date_reg_debut->format('d/m/Y').' au '.$date_reg_fin->format('d/m/Y'),
                    'period'           => ' '.strftime("%B %Y", strtotime( $date_reg_debut->format('d-m-Y') )).' à '.strftime("%B %Y", strtotime( $date_reg_fin->format('d-m-Y') )),
                    'property'   => $property,
                    'warrant'    => [
                        'id'         => $property->getWarrant()->getId(),
                        'type'       => $property->getWarrant()->getType(),
                        'firstname'  => $property->getWarrant()->getFirstname(),
                        'lastname'   => $property->getWarrant()->getLastname(),
                        'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                        'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                        'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                    ],
                    "debirentier" => null,
                    "debirentier_different" => null,
                    "target" => null,
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    "factures" => $factures,
                    "debit" => $debit,
                    "credit" => $credit,
                    "form" => $form->getData(),
                    "pieces_jointes" => $pjs,
                ];
                if($property->getDebirentierDifferent()){
                    $debirentier    = [
                        'nom_debirentier'         => $property->getNomDebirentier(),
                        'prenom_debirentier'       => $property->getPrenomDebirentier(),
                        'addresse_debirentier'  => $property->getAddresseDebirentier(),
                        'code_postal_debirentier'   => $property->getCodePostalDebirentier(),
                        'ville_debirentier'    => $property->getVilleDebirentier(),
                    ];
                    $data["debirentier"]=$debirentier;
                    $data["debirentier_different"]=$property->getDebirentierDifferent();
                }
                
                
                if($regul>0){
                    //si résultat positif= régul adréssée au débirentier
                    //Le débirentier = le propriétaire = la société Opale Business 2
                    //$pendingInvoice->setTarget(PendingInvoice::TARGET_WARRANT);//1
                    $data["target"]="debirentier";
                }else{
                    //si résultat négatif= régul adressée au crédirentier
                    //Le crédirentier
                    //$pendingInvoice->setTarget(PendingInvoice::TARGET_PROPERTY);//2
                    $data["target"]="crédirentier";
                }

                try {
                    $pdf->pdf->SetDisplayMode('fullpage');
                    if($regul>0){
                        //si résultat positif= régul adréssée au débirentier
                        //Le débirentier = le propriétaire = la société Opale Business 2
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_WARRANT);//1
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-regularisation-copro-07-debit-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }else{
                        //si résultat négatif= régul adressée au crédirentier
                        //Le crédirentier
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_PROPERTY);//2
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-regularisation-copro-06-credit-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }
                    $pdf->output('/var/www/vhosts/adm.viag2e.fr/adm.viag2e.fr/var/tmp/pdf/'. $fileName, 'F');
                    
                    $file = new File();
                    $file->setType(File::TYPE_DOCUMENT);
                    $file->setName($fileName);
                    $file->setDate($now_date);
                    $file->setWarrant($property->getWarrant());
                    $file->setProperty($property);
                    $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                    $manager = $this->getDoctrine()->getManager();
                    $manager->persist($file);
                    $manager->flush();

                    

                    return $this->render('generated_files/courrier-regularisation-copro.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => 'fichier courrier de régularisation créé avec succès',
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-regularisation-copro.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
        ]);
    }


    /**
     * @Route("/generated_files/courrier_indexation_og2i_annee1/{propertyId}", name="generate_courrier_indexation_og2i_annee1", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_indexation_og2i_annee1(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
        $parameters = [
            'tva'        => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva'])->getValue(),
            'footer'     => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];
        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }
        function get_label($i){
            if($i==1){
                return 'Urbains';
            }else if($i==2){
                return 'Ménages';
            }else{
                return 'Ménages';
            }

        }
        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
            ],
        ])
        ->add('formule', ChoiceType::class, [
            'choices'  => [
                'Cher Monsieur' => "Cher Monsieur",
                'Chère Madame' => "Chère Madame",
            ],
            
        ])
        ->add('civilite', ChoiceType::class, [
            'choices'  => [
                'Monsieur' => "Monsieur",
                'Madame' => "Madame",
            ],
        ])
        ->add('date_virement', DateType::class, [ 
            'format' => 'dd-MMM-yyyy',
            'years' => range(date('Y'), date('Y') + 20),
        ])
        ->add('date_revision', DateType::class, [ 
            'format' => 'dd-MMM-yyyy',
            'years' => range(date('Y'), date('Y') + 30),
        ])
        ->getForm();
        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                $destinataire = $data["destinataire"];
                $date_virement = $data["date_virement"];
                $date_revision = $data["date_revision"];
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $date_fdnm = new DateTime('First day of next month');
                $fileName = "Courrier d’indexation OG2I Année 1 -".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                $now_date=new DateTime();
                if(!$property->valeur_indice_ref_og2_i_object){
                    return $this->render('generated_files/courrier-indexation-og2i-annee1.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => null,
                        'error' => "Pour générer ce courrier d'indexation og2i, merci de verifier que le bien ".$property->getId()." : ".$property->getTitle()." est de type og2i et que le champ 'Valeur Indice de référence og2i' a été renseigné."]);
                }
                $month_og2i=$property->valeur_indice_ref_og2_i_object->getDate()->format('m');
                $endDate_og2i = \DateTime::createFromFormat('d-n-Y', "31-".$month_og2i."-".date('Y'));
                $endDate_og2i->setTime(0, 0, 0);
                //recuperer 
                $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
                ->select("rh")
                ->from('App\Entity\RevaluationHistory', 'rh')
                ->where('rh.type LIKE :key and rh.date <= :end')
                ->andWhere('rh.date like  :endmonth')
                ->setParameter('key', 'OGI')
                ->setParameter('end', $endDate_og2i)
                ->setParameter('endmonth',  "%-%".$month_og2i."-%")
                    ->orderBy('rh.date', 'DESC');
                $query = $qb->getQuery();
                // Execute Query
                if($query->getResult()){
                    $indice_og2i = $query->getResult()[0];
                }else{
                    $indice_og2i = (object) array('value' => 0,'id'=>0);
                }
                $mi = $property->getInitialAmount();
                $rdb=round(($property->valeur_indice_ref_og2_i_object->getValue()*$mi)/$property->initial_index_object->getValue(),2);

                $res=($indice_og2i->getValue() *$rdb)/$property->valeur_indice_ref_og2_i_object->getValue();

                $plaff=$property->plafonnement_index_og2_i;
                $plaff_v=(1+($plaff/100))*$rdb;
				if(!$plaff || $plaff<=0){
                    $rente=round($res,2);
                    $is_plaff=false;
                }
                else if($res<$plaff_v){
                    $rente=round($res,2);
                    $is_plaff=false;
                }else{
                    $rente=round($plaff_v,2);
                    $is_plaff=true;
                }
                $honoraires = round($rente*$property->honorary_rates_object->getValeur()/100,2);

                $data = [
                    'date'       => $now_date,
                    'current_day'       => strftime("%d %B %Y", strtotime( $now_date->format('d-m-Y') )),
                    'annee'       => $now_date->format('Y'),
                    'date_a_f'       => $now_date->format('d/m/Y'),
                    'property'   => $property,
                    'warrant'    => [
                        'id'         => $property->getWarrant()->getId(),
                        'type'       => $property->getWarrant()->getType(),
                        'firstname'  => $property->getWarrant()->getFirstname(),
                        'lastname'   => $property->getWarrant()->getLastname(),
                        'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                        'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                        'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                    ],
                    "debirentier" => null,
                    "debirentier_different" => null,
                    "target" => null,
                    "not_assurance_habit" => ($property->date_assurance_habitation && $property->date_assurance_habitation < $now_date )?true:false,
                    "texte_assurance_habit" => "Je vous remercie de bien vouloir nous faire parvenir votre attestation d’assurance habitation couvrant l’année ".$now_date->format('Y'),
                    "date_virement" => strftime("%B %Y", strtotime( $date_virement->format('d-m-Y') )),
                    "date_revision" => strftime("%B %Y", strtotime( $date_revision->format('d-m-Y') )),
                    "fd_next_month_d_m_y" => strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') )),
                    "fd_next_month_m_y" => strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') )),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    "form" => $form->getData(),
                    "date_indice_base" =>  strftime("%B %Y", strtotime( $property->initial_index_object->getDate()->format('d-m-Y') )),
                    "montant_indice_base" => $property->initial_index_object->getValue(),
                    "date_indice_base_og2i" =>  strftime("%B %Y", strtotime( $property->valeur_indice_ref_og2_i_object->getDate()->format('d-m-Y') )),
                    "montant_indice_base_og2i" => $property->valeur_indice_ref_og2_i_object->getValue(),
                    "date_indice_actuel_og2i" =>  strftime("%B %Y", strtotime( $indice_og2i->getDate()->format('d-m-Y') )),
                    "montant_indice_actuel_og2i" => $indice_og2i->getValue(),
                    "ia" => $property->getInitialAmount(),
                    "is_plaff" => $is_plaff,
                    "plaff_val" => $property->plafonnement_index_og2_i,
                    "rente_base" => $rdb,
                    "rente" => $rente,
                    "honoraires" => $honoraires,
                ];
                if($property->getDebirentierDifferent()){
                    $debirentier    = [
                        'nom_debirentier'         => $property->getNomDebirentier(),
                        'prenom_debirentier'       => $property->getPrenomDebirentier(),
                        'addresse_debirentier'  => $property->getAddresseDebirentier(),
                        'code_postal_debirentier'   => $property->getCodePostalDebirentier(),
                        'ville_debirentier'    => $property->getVilleDebirentier(),
                    ];
                    $data["debirentier"]=$debirentier;
                    $data["debirentier_different"]=$property->getDebirentierDifferent();
                }
                
                try {
                    $pdf->pdf->SetDisplayMode('fullpage');
                    if($destinataire=="Débirentier"){
                        $data["target"]="Débirentier";
                        //si résultat positif= régul adréssée au débirentier
                        //Le débirentier = le propriétaire = la société Opale Business 2
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_WARRANT);//1
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-og2i-annee1-debit-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }else if($destinataire=="Crédirentier"){
                        $data["target"]="Crédirentier";
                        //si résultat négatif= régul adressée au crédirentier
                        //Le crédirentier
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_PROPERTY);//2
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-og2i-annee1-credit-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }
                    $pdf->output('/var/www/vhosts/adm.viag2e.fr/adm.viag2e.fr/var/tmp/pdf/'. $fileName, 'F');
                    
                    $file = new File();
                    $file->setType(File::TYPE_DOCUMENT);
                    $file->setName($fileName);
                    $file->setWarrant($property->getWarrant());
                    $file->setProperty($property);
                    $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                    $manager = $this->getDoctrine()->getManager();
                    $manager->persist($file);
                    $manager->flush();

                    

                    return $this->render('generated_files/courrier-indexation-og2i-annee1.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'error' => null,
                        'message' => 'fichier Courrier d’indexation créé avec succès',
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-indexation-og2i-annee1.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'error' => null,
        ]);
    }


    /**
     * @Route("/generated_files/courrier_indexation/{propertyId}", name="generate_courrier_indexation", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_indexation(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
        $parameters = [
            'tva'        => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva'])->getValue(),
            'footer'     => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];
        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }
        function get_label($i){
            if($i==1){
                return 'Urbains';
            }else if($i==2){
                return 'Ménages';
            }else{
                return 'Ménages';
            }

        }
        $defaultData = ['message' => 'Type your message here'];
        $jour_revaluation=explode("-", $property->getRevaluationDate())[0];
        $mois_revaluation=explode("-", $property->getRevaluationDate())[1];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
            ],
        ])
        ->add('formule', ChoiceType::class, [
            'choices'  => [
                'Cher Monsieur' => "Cher Monsieur",
                'Chère Madame' => "Chère Madame",
                'Chère Madame, Cher Monsieur '=> "Chère Madame, Cher Monsieur",
            ],
            
        ])
        ->add('civilite', ChoiceType::class, [
            'choices'  => [
                'Monsieur' => "Monsieur",
                'Madame' => "Madame",
                'Madame, Monsieur' => "Madame, Monsieur",
            ],
        ])
        ->add('date_virement', DateType::class, [ 
            'format' => 'dd-MMM-yyyy',
            'years' => range(date('Y'), date('Y') + 20),
            'data' => new \DateTime(date('Y').'-'.$mois_revaluation.'-01'),
        ])
        ->add('date_revision', DateType::class, [ 
            'format' => 'dd-MMM-yyyy',
            'years' => range(date('Y'), date('Y') + 40),
            'data' => new \DateTime((date('Y')+1).'-'.$mois_revaluation.'-01'),
        ])
        ->getForm();
        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                $destinataire = $data["destinataire"];
                $date_virement = $data["date_virement"];
                $date_revision = $data["date_revision"];
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $date_fdnm = new DateTime('First day of this month');
                $fileName = "Courrier d’indexation -".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                $now_date=new DateTime();
                if(!$property->initial_index_object){
                    return $this->render('generated_files/courrier-indexation.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => null,
                        'error' => "Pour générer ce courrier d'indexation, merci de renseigner le champ 'Valeur de l'indice initial' sur le bien ".$property->getId()." : ".$property->getTitle(),
                    ]);
                }
                $month_m_u=$property->initial_index_object->getDate()->format('m');
                $endDate_m_u = \DateTime::createFromFormat('d-n-Y', "31-".$month_m_u."-".date('Y'));
                $endDate_m_u->setTime(0, 0, 0);
                // recuperer Valeur Indice de référence* (indexation)
                
                $qb4=$this->getDoctrine()->getManager()->createQueryBuilder()
                ->select("rh")
                ->from('App\Entity\RevaluationHistory', 'rh')
                ->where('rh.type LIKE :key')
                ->andWhere('rh.date <= :end')
                ->andWhere('rh.date like  :endmonth')
                ->setParameter('key', get_label($property->getIntitulesIndicesInitial()))
                ->setParameter('endmonth',  "%-%".$month_m_u."-%")
                ->setParameter('end', $endDate_m_u)
                    ->orderBy('rh.date', 'DESC');
                $query4 = $qb4->getQuery();
                // Execute Query
                if($query4->getResult()){
                    $indice_m_u = $query4->getResult()[0]; 
                    $property->valeur_indice_reference_object=$query4->getResult()[0];
                }else{
                    $indice_m_u = (object) array('value' => 0,'id'=>0);
                }

                $data = [
                    'date'       => $now_date,
                    'current_day'       => utf8_encode(strftime("%d %B %Y", strtotime( $now_date->format('d-m-Y') ))),
                    'annee'       => $now_date->format('Y'),
                    'date_a_f'       => $now_date->format('d/m/Y'),
                    'property'   => $property,
                    'warrant'    => [
                        'id'         => $property->getWarrant()->getId(),
                        'type'       => $property->getWarrant()->getType(),
                        'firstname'  => $property->getWarrant()->getFirstname(),
                        'lastname'   => $property->getWarrant()->getLastname(),
                        'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                        'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                        'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                    ],
                    "debirentier" => null,
                    "debirentier_different" => null,
                    "target" => null,
                    "not_assurance_habit" => ($property->date_assurance_habitation && $property->date_assurance_habitation < $now_date )?true:false,
                    "texte_assurance_habit" => "votre attestation d’assurance habitation couvrant l’année ".$now_date->format('Y'),
                    "not_assurance_chemine" => ($property->date_cheminee && $property->date_cheminee < $now_date )?true:false,
                    "texte_assurance_chemine" => "votre attestation d’entretien cheminée couvrant l’année ".$now_date->format('Y'),
                    "not_assurance_chaudiere" => ($property->date_chaudiere && $property->date_chaudiere < $now_date )?true:false,
                    "texte_assurance_chaudiere" => "votre attestation d’entretien chaudière couvrant l’année ".$now_date->format('Y'),
                    "not_assurance_climatisation" => ($property->date_climatisation && $property->date_climatisation < $now_date )?true:false,
                    "texte_assurance_climatisation" => "votre attestation d’entretien climatisation couvrant l’année ".$now_date->format('Y'),
                    "adresse_bien"=>($property->getShowDuh())?$property->getAddress():$property->getGoodAddress(),
                    "date_virement" => utf8_encode(strftime("%B %Y", strtotime( $date_virement->format('d-m-Y') ))),
                    "date_revision" => utf8_encode(strftime("%B %Y", strtotime( $date_revision->format('d-m-Y') ))),
                    "fd_next_month_d_m_y" => strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') )),
                    "fd_next_month_m_y" => strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') )),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    "form" => $form->getData(),
                    "date_indice_base" =>  utf8_encode(strftime("%B %Y", strtotime( $property->initial_index_object->getDate()->format('d-m-Y') ))),
                    "montant_indice_base" => $property->initial_index_object->getValue(),
                    "date_indice_actuel" =>  utf8_encode(strftime("%B %Y", strtotime( $indice_m_u->getDate()->format('d-m-Y') ))),
                    "montant_indice_actuel" => $indice_m_u->getValue(),
                    "ia" => $property->getInitialAmount(),
                    "rente" => round($property->getInitialAmount()*($indice_m_u->getValue()/$property->initial_index_object->getValue()),2),
                    "honoraires" => round(($property->getInitialAmount()*($indice_m_u->getValue()/$property->initial_index_object->getValue()))*$property->honorary_rates_object->getValeur()/100,2),
                ];
                if($property->getDebirentierDifferent()){
                    $debirentier    = [
                        'nom_debirentier'         => $property->getNomDebirentier(),
                        'prenom_debirentier'       => $property->getPrenomDebirentier(),
                        'addresse_debirentier'  => $property->getAddresseDebirentier(),
                        'code_postal_debirentier'   => $property->getCodePostalDebirentier(),
                        'ville_debirentier'    => $property->getVilleDebirentier(),
                    ];
                    $data["debirentier"]=$debirentier;
                    $data["debirentier_different"]=$property->getDebirentierDifferent();
                }
                
                try {
                    $pdf->pdf->SetDisplayMode('fullpage');
                    if($destinataire=="Débirentier"){
                        $data["target"]="Débirentier";
                        //si résultat positif= régul adréssée au débirentier
                        //Le débirentier = le propriétaire = la société Opale Business 2
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_WARRANT);//1
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-debit-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }else if($destinataire=="Crédirentier"){
                        $data["target"]="Crédirentier";
                        //si résultat négatif= régul adressée au crédirentier
                        //Le crédirentier
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_PROPERTY);//2
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-credit-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }
                    $pdf->output('/var/www/vhosts/adm.viag2e.fr/adm.viag2e.fr/var/tmp/pdf/'. $fileName, 'F');
                    
                    $file = new File();
                    $file->setType(File::TYPE_DOCUMENT);
                    $file->setName($fileName);
                    $file->setWarrant($property->getWarrant());
                    $file->setProperty($property);
                    $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                    $manager = $this->getDoctrine()->getManager();
                    $manager->persist($file);
                    $manager->flush();

                    

                    return $this->render('generated_files/courrier-indexation.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => 'fichier Courrier d’indexation créé avec succès',
                        'error' => null,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-indexation.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'error' =>null,
        ]);
    }

    /**
     * @Route("/generated_files/courrier_abandon_duh/{propertyId}", name="generate_courrier_abandon_duh", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_abandon_duh(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
        $parameters = [
            'tva'        => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva'])->getValue(),
            'footer'     => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];
        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }
        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
            ],
        ])
        ->add('formule', ChoiceType::class, [
            'choices'  => [
                'Cher Monsieur' => "Cher Monsieur",
                'Chère Madame' => "Chère Madame",
            ],
            
        ])
        ->add('civilite', ChoiceType::class, [
            'choices'  => [
                'Monsieur' => "Monsieur",
                'Madame' => "Madame",
            ],
            
        ])
        ->add('montant_mois_actuel', TextType::class, [
        ])
        ->add('montant_honoraires', TextType::class, [
        ])
        ->getForm();
        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                $destinataire = $data["destinataire"];
                $mma = $data["montant_mois_actuel"];
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $date_fdnm = new DateTime('First day of next month');
                $fileName = "Courrier d’abandon du DUH -".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                $now_date=new DateTime();
                $data = [
                    'date'       => $now_date,
                    'current_day'       => strftime("%d %B %Y", strtotime( $now_date->format('d-m-Y') )),
                    'annee'       => $now_date->format('Y'),
                    'date_a_f'       => $now_date->format('d/m/Y'),
                    'property'   => $property,
                    'warrant'    => [
                        'id'         => $property->getWarrant()->getId(),
                        'type'       => $property->getWarrant()->getType(),
                        'firstname'  => $property->getWarrant()->getFirstname(),
                        'lastname'   => $property->getWarrant()->getLastname(),
                        'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                        'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                        'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                    ],
                    "debirentier" => null,
                    "debirentier_different" => null,
                    "target" => null,
                    "fd_next_month_d_m_y" => strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') )),
                    "fd_next_month_m_y" => strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') )),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    "form" => $form->getData(),
                    "montant_mois_suivant" => $mma* (1+$property->getAbandonmentIndex()),
                ];
                if($property->getDebirentierDifferent()){
                    $debirentier    = [
                        'nom_debirentier'         => $property->getNomDebirentier(),
                        'prenom_debirentier'       => $property->getPrenomDebirentier(),
                        'addresse_debirentier'  => $property->getAddresseDebirentier(),
                        'code_postal_debirentier'   => $property->getCodePostalDebirentier(),
                        'ville_debirentier'    => $property->getVilleDebirentier(),
                    ];
                    $data["debirentier"]=$debirentier;
                    $data["debirentier_different"]=$property->getDebirentierDifferent();
                }
                
                try {
                    $pdf->pdf->SetDisplayMode('fullpage');
                    if($destinataire=="Débirentier"){
                        $data["target"]="Débirentier";
                        //si résultat positif= régul adréssée au débirentier
                        //Le débirentier = le propriétaire = la société Opale Business 2
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_WARRANT);//1
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-abandon-duh-debit-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }else if($destinataire=="Crédirentier"){
                        $data["target"]="Crédirentier";
                        //si résultat négatif= régul adressée au crédirentier
                        //Le crédirentier
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_PROPERTY);//2
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-abandon-duh-credit-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }
                    $pdf->output('/var/www/vhosts/adm.viag2e.fr/adm.viag2e.fr/var/tmp/pdf/'. $fileName, 'F');
                    
                    $file = new File();
                    $file->setType(File::TYPE_DOCUMENT);
                    $file->setName($fileName);
                    $file->setWarrant($property->getWarrant());
                    $file->setProperty($property);
                    $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                    $manager = $this->getDoctrine()->getManager();
                    $manager->persist($file);
                    $manager->flush();

                    

                    return $this->render('generated_files/courrier-abandon-duh.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => 'fichier Courrier d’abandon du DUH créé avec succès',
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-abandon-duh.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
        ]);
    }


     /**
     * @Route("/generated_files/mandat_de_gestion/{propertyId}", name="generate_mandat_de_gestion", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_mandat_de_gestion(Request $request, DriveManager $driveManager)
    {
        return $this->redirectToRoute('dashboard');
    }

     /**
     * @Route("/generated_files/courrier_premier_contact/{propertyId}", name="generate_courrier_premier_contact", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_premier_contact(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
        $parameters = [
            'tva'        => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva'])->getValue(),
            'footer'     => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_footer'])->getValue(),
            'address'    => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_address'])->getValue(),
            'postalcode' => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_postalcode'])->getValue(),
            'city'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_city'])->getValue(),
            'phone'      => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_phone'])->getValue(),
            'mail'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_mail'])->getValue(),
            'site'       => $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_site'])->getValue(),
        ];
        if(empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard');
        }
        function get_label($i){
            if($i==1){
                return 'Urbains';
            }else if($i==2){
                return 'Ménages';
            }else{
                return 'Ménages';
            }

        }
        $defaultData = ['message' => 'Type your message here'];
        $jour_revaluation=explode("-", $property->getRevaluationDate())[0];
        $mois_revaluation=explode("-", $property->getRevaluationDate())[1];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
            ],
        ])
        ->add('formule', ChoiceType::class, [
            'choices'  => [
                'Cher Monsieur' => "Cher Monsieur",
                'Chère Madame' => "Chère Madame",
                'Cher Monsieur, Chère Madame'=> "Cher Monsieur, Chère Madame",
            ],
            
        ])
        ->add('designation_notaire', ChoiceType::class, [
            'choices'  => [
                'Ce dernier' => "Ce dernier",
                'Cette dernière' => "Cette dernière",
            ],
            
        ])
        ->add('civilite', ChoiceType::class, [
            'choices'  => [
                'Monsieur' => "Monsieur",
                'Madame' => "Madame",
                'Madame, Monsieur' => "Madame, Monsieur",
            ],
        ])
        ->add('date_signature_acte', DateType::class, [ 
            'format' => 'dd-MMM-yyyy',
            'years' => range(date('Y'), date('Y') + 20),
            'data' => new \DateTime(date('Y').'-'.$mois_revaluation.'-01'),
        ])
        ->add('nom_notaire', TextType::class, ['required' => true])
        ->add('addresse_notaire', TextType::class, ['required' => true])
        ->add('au_profit_de', TextType::class, ['required' => true])
        ->add('bien_en_copro', CheckboxType::class, ['required' => false])
        ->add('demander_pieces', CheckboxType::class, ['required' => false])
        ->add('demander_rib', CheckboxType::class, ['required' => false])
        ->add('demander_attestation_habitation', CheckboxType::class, ['required' => false])
        ->add('demander_attestation_entretien', CheckboxType::class, ['required' => false])
        ->add('demander_facture_ramonage', CheckboxType::class, ['required' => false])
        ->getForm();
        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $data = $form->getData();
                $destinataire = $data["destinataire"];
                $date_signature_acte = $data["date_signature_acte"];
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $date_fdnm = new DateTime('First day of this month');
                $fileName = "Courrier de premier contact -".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                $now_date=new DateTime();
                

                $data = [
                    'date'       => $now_date,
                    'current_day'       => utf8_encode(strftime("%d %B %Y", strtotime( $now_date->format('d-m-Y') ))),
                    'annee'       => $now_date->format('Y'),
                    'date_a_f'       => $now_date->format('d/m/Y'),
                    'property'   => $property,
                    'warrant'    => [
                        'id'         => $property->getWarrant()->getId(),
                        'type'       => $property->getWarrant()->getType(),
                        'firstname'  => $property->getWarrant()->getFirstname(),
                        'lastname'   => $property->getWarrant()->getLastname(),
                        'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                        'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                        'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                    ],
                    "debirentier" => null,
                    "debirentier_different" => null,
                    "target" => null,
                   "adresse_bien"=>($property->getShowDuh())?$property->getAddress():$property->getGoodAddress(),
                   "date_signature_acte" =>  $date_signature_acte->format('d/m/Y'),

                    "nom_compte" => explode("/", $property->getTitle())[0],
                    "form" => $form->getData(),
                   
                ];
                if($property->getDebirentierDifferent()){
                    $debirentier    = [
                        'nom_debirentier'         => $property->getNomDebirentier(),
                        'prenom_debirentier'       => $property->getPrenomDebirentier(),
                        'addresse_debirentier'  => $property->getAddresseDebirentier(),
                        'code_postal_debirentier'   => $property->getCodePostalDebirentier(),
                        'ville_debirentier'    => $property->getVilleDebirentier(),
                    ];
                    $data["debirentier"]=$debirentier;
                    $data["debirentier_different"]=$property->getDebirentierDifferent();
                }
                
                try {
                    $pdf->pdf->SetDisplayMode('fullpage');
                    if($destinataire=="Débirentier"){
                        $data["target"]="Débirentier";
                        //si résultat positif= régul adréssée au débirentier
                        //Le débirentier = le propriétaire = la société Opale Business 2
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_WARRANT);//1
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-premier-contact-debirentier-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }else if($destinataire=="Crédirentier"){
                        $data["target"]="Crédirentier";
                        //si résultat négatif= régul adressée au crédirentier
                        //Le crédirentier
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_PROPERTY);//2
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-premier-contact-credirentier-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }
                    $pdf->output('/var/www/vhosts/adm.viag2e.fr/adm.viag2e.fr/var/tmp/pdf/'. $fileName, 'F');
                    
                    $file = new File();
                    $file->setType(File::TYPE_DOCUMENT);
                    $file->setName($fileName);
                    $file->setWarrant($property->getWarrant());
                    $file->setProperty($property);
                    $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                    $manager = $this->getDoctrine()->getManager();
                    $manager->persist($file);
                    $manager->flush();

                    

                    return $this->render('generated_files/courrier-premier-contact.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => 'fichier Courrier d’indexation créé avec succès',
                        'error' => null,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-premier-contact.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'error' =>null,
        ]);
    }

     /**
     * @Route("/generated_files/mise_en_place_charges_copro/{propertyId}", name="generate_mise_en_place_charges_copro", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_mise_en_place_charges_copro(Request $request, DriveManager $driveManager)
    {
        return $this->redirectToRoute('dashboard');
    }

    

     /**
     * @Route("/generated_files/courrier_libre/{propertyId}", name="generate_courrier_libre", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_libre(Request $request, DriveManager $driveManager)
    {
        return $this->redirectToRoute('dashboard');
    }

    

    /**
     * @Route("/file/delete/{fileId}", name="file_delete")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function delete_property_file(Request $request, DriveManager $driveManager)
    {
        $file = $this->getDoctrine()
            ->getRepository(File::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        $route = $this->generateUrl('property_view', ['type' => $file->getProperty()->getTypeString(), 'propertyId' => $file->getProperty()->getId()]);

        if (empty($file) || $file->getType() != File::TYPE_DOCUMENT) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        if ($driveManager->trashFile($file) === true) {
            $manager = $this->getDoctrine()->getManager();
            $manager->remove($file);
            $manager->flush();

            $this->addFlash('success', 'Fichier supprimé');
            return $this->redirect($route);
        }

        $this->addFlash('danger', 'Une erreur a eu lieu pendant la suppression');
        return $this->redirect($route);
    }
    /**
     * @Route("/file/download/{fileId}", name="file_download")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function download(Request $request, DriveManager $driveManager)
    {
        /** @var File $file */
        $file = $this->getDoctrine()
            ->getRepository(File::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        if (empty($file)) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $path = $driveManager->getFile($file);
        if (!empty($path)) {
            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getName().$file->getExtension());
            //$response->headers->set('Content-Type', $file->getMime());
            $response->deleteFileAfterSend(true);
            return $response;
        }

        $this->addFlash('danger', 'Fichier introuvable');
        return $this->redirectToRoute('dashboard', [], 302);
    }

    /**
     * @Route("/file/download/export/{fileId}", name="file_export_download")
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function downloadExport(Request $request, DriveManager $driveManager)
    {
        /** @var BankExport $export */
        $export = $this->getDoctrine()
            ->getRepository(BankExport::class)
            ->findOneBy(['drive_id' => $request->get('fileId')]);

        if (empty($export)) {
            $this->addFlash('danger', 'Fichier introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $path = $driveManager->getExport($export);
        if (!empty($path)) {
            $response = new BinaryFileResponse($path);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $export->getName());
            //$response->headers->set('Content-Type', $file->getMime());
            $response->deleteFileAfterSend(true);
            return $response;
        }

        $this->addFlash('danger', 'Fichier introuvable');
        return $this->redirectToRoute('dashboard', [], 302);
    }
}
