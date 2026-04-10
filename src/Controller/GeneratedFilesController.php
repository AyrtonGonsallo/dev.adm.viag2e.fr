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
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use App\Service\TextReplacer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
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
    private $replacer;
    public function __construct(ContainerInterface $container, ParameterBagInterface $params,TextReplacer $replacer)
    {
        $this->path     = $params->get('pdf_tmp_dir');
        $this->pdf_logo = $params->get('pdf_logo_path');
        $this->twig     = $container->get('twig');
        $this->replacer = $replacer;
    }
   


     /**
     * @Route("/generated_files/facture/{propertyId}", name="generate_facture", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_facture(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 2)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        $last_number = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'invoice_number']);
        $current_number = $last_number->getValue() + 1;
        $current_number_string = Invoice::formatNumber($current_number, Invoice::TYPE_NOTICE_EXPIRY);

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
        $defaultData = [
            'montant' => 0,
        ];
        $defaultText = '<table style="width:100%;border:solid 1px #000;font-size:10pt"cellspacing=0><tr><td style="width:60%;border-right:solid 1px #000"><table style=width:100%;border-collapse:collapse><tr style=width:100%><td style="border-bottom:solid 1px #000;border-collapse:collapse;width:50%">Numéro du bien: <b>'.$property->getId().'</b><td style="border-bottom:solid 1px #000;border-collapse:collapse;width:50%">Numéro de client: <b>'.$property->getWarrant()->getId().'</b><tr><td colspan=2><br><br>Madame, Monsieur,<br><br>Nous vous prions de trouver ci-joint votre appel de fonds relatif <br> [motif]<br> pour la période [period] <br>concernant le bien de:<br><br> '.$property->getFirstname1().' '.$property->getLastname1().' - '.$property->getFirstname2().' '.$property->getLastname2().'<br>'.$property->getAddress().'<br>'.$property->getPostalCode().'<br>'.$property->getCity().' <br><br><br>Nous restons à votre disposition.<br><br>Bien cordialement.<br><br><b>Univers Viager</b><br><br></table><td style=width:40%><br><br><table style=width:100%><col style=width:60%;text-align:left><col style=width:40%;text-align:right><thead><tr><th colspan=2 style=text-align:center><b>[resume]</b><tbody><tr><td>[montantht]</td></tr><tr><td>[tva]</td></tr><tr><td>[montantttc]</td></tr></table><br><br><br><br><br></table>';
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => ['Acheteur (Mandat acquéreurs)' => 1,'Acheteur (Mandat vendeurs)' => 3,'Crédirentier' => 2,'Débirentier' => 4,'Mandant' => 1,  ],
        ])
        ->add('numero_de_facture', TextType::class, ['data' =>  $current_number_string, 'disabled'=>true, 'required' => false])
        ->add('montant', TextType::class, ['required' => false])
        ->add('email', EmailType::class, array('attr' => array('placeholder' => 'E-mail...'),'required' => false))
        ->add('motif', TextType::class, ['required' => false])
        ->add('type', ChoiceType::class, ['choices' => ['Rente' => 1,'Honoraires' => 2,'Co-pro' => 3 ], 'choice_translation_domain' => false])
        ->add('facturation', ChoiceType::class, ['choices' => ['HT' => 1,'TTC' => 2  ], 'choice_translation_domain' => false])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'générer la facture' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('period', TextType::class, ['required' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $debirentier    = [
                'nom_debirentier'         => $property->getNomDebirentier(),
                'prenom_debirentier'       => $property->getPrenomDebirentier(),
                'addresse_debirentier'  => $property->getAddresseDebirentier(),
                'code_postal_debirentier'   => $property->getCodePostalDebirentier(),
                'ville_debirentier'    => $property->getVilleDebirentier(),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $dataForm['texte']=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $dataForm['texte']);
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $fileName = "Avis d'échéance - ".$property->getId()." - ".$now_date->format('d-m-Y h:i:s').".pdf";
                $former_destinataire = $dataForm['destinataire'];
                $former_montant = $dataForm['montant'];
                $former_email = $dataForm['email'];
                $former_type = $dataForm['type'];
                $former_motif = $dataForm['motif'];
                $former_facturation = $dataForm['facturation'];
                $former_period = $dataForm['period'];
                $former_status = $dataForm['status'];
                try {
                    if($status==1 || $status==3){
                        if($former_type==1||$former_type==3){//rente , copro
                            $resume='Détails de l\'appel de la période';
                            $total='Détails de l\'appel de la période';
                            $defaultText = str_replace(
                                '[montantht]',
                                'Détails de l\'appel de la période<td>'.$former_montant. ' €',
                                $defaultText
                            );
                            if($former_facturation==2){
                                $montant_tva_string=number_format(0.2*$former_montant, 2, '.', ' ');
                                $montant_tva=0.2*$former_montant;
                                $montant_ttc=number_format($montant_tva+$former_montant, 2, '.', ' ');
                                $tvaText = 'Tva 20%<td>'.$montant_tva_string. ' €';
                                $ttcText = 'Total TTC <td>'.$montant_ttc. ' €';
                            }else{
                                $tvaText = '';
                                $ttcText = '';
                            }

                            $defaultText = str_replace(
                                '[tva]',
                                $tvaText,
                                $defaultText
                            );
                            $defaultText = str_replace(
                                '[montantttc]',
                                $ttcText,
                                $defaultText
                            );
                            
                            

                        }else if($former_type==2){//honoraires
                            $resume='Coût des honoraires de la période';
                            $total='Total TTC des honoraires';
                            $defaultText = str_replace(
                                '[montantht]',
                                'Honoraires H.T<td>'.$former_montant. ' €',
                                $defaultText
                            );
                        }
                        
                        
                        
                        if($former_facturation==2 && $former_type==2){//tva honoraires
                        $montant_tva_string=number_format(0.2*$former_montant, 2, '.', ' ');
                        $montant_tva=0.2*$former_montant;
                        $honotaires_ttc=number_format($montant_tva+$former_montant, 2, '.', ' ');
                             $defaultText = str_replace(
                                '[tva]',
                                'Tva 20%<td>'.$montant_tva_string. ' €',
                                $defaultText
                            );
                            $defaultText = str_replace(
                                '[montantttc]',
                                'Total TTC des honoraires<td>'.$honotaires_ttc. ' €',
                                $defaultText
                            );

                        }

                        if($former_facturation==1 && $former_type==2){// honoraires ht
                             $defaultText = str_replace(
                                '[tva]',
                                '',
                                $defaultText
                            );
                            $defaultText = str_replace(
                                '[montantttc]',
                                '',
                                $defaultText
                            );

                        }
                       
                        $defaultText = str_replace(
                            '[period]',
                            $former_period,
                            $defaultText
                        );
                         $defaultText = str_replace(
                            '[motif]',
                            $former_motif,
                            $defaultText
                        );
                         $defaultText = str_replace(
                            '[resume]',
                            $resume,
                            $defaultText
                        );

                        $message = 'Aperçu créé avec succès';



                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, [
                            'data' =>  $former_destinataire,
                            'choices'  => ['Acheteur (Mandat acquéreurs)' => 1,'Acheteur (Mandat vendeurs)' => 3,'Crédirentier' => 2,'Débirentier' => 4,'Mandant' => 1,  ],
                        ])
                        ->add('numero_de_facture', TextType::class, ['data' =>  $current_number_string, 'disabled'=>true, 'required' => false])
                        ->add('montant', TextType::class, ['data' =>  $former_montant,'required' => false])
                        ->add('email', EmailType::class, array('data' =>  $former_email,'attr' => array('placeholder' => 'E-mail...'),'required' => false))
                        ->add('motif', TextType::class, ['data' =>  $former_motif,'required' => false])
                        ->add('type', ChoiceType::class, ['data' =>  $former_type,'choices' => ['Rente' => 1,'Honoraire' => 2,'Manuelle' => 3,  ], 'choice_translation_domain' => false])
                        ->add('facturation', ChoiceType::class, ['data' =>  $former_facturation,'choices' => ['HT' => 1,'TTC' => 2  ], 'choice_translation_domain' => false])
                        ->add('status', ChoiceType::class, ['data' => $former_status,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'générer la facture' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('period', TextType::class, ['data' =>  $former_period,'required' => false])
                        ->add('texte', TextareaType::class, [
                            'data' =>$defaultText,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        $data = [
                            'type'       => Invoice::TYPE_NOTICE_EXPIRY,
                            'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                            'date'       => $now_date,
                            'target'           => $former_destinataire,
                            'form'       => $dataForm,
                            'current_number'       => $current_number_string,
                            'day'  => $now_date->format('d'),
                            'month'     => $now_date->format('m'),
                            'year' => $now_date->format('Y'),
                            'property'           => $property,
                            'warrant'    => [
                                'id'         => $property->getWarrant()->getId(),
                                'type'       => $property->getWarrant()->getType(),
                                'firstname'  => $property->getWarrant()->getFirstname(),
                                'lastname'   => $property->getWarrant()->getLastname(),
                                'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                                'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                                'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                            ],
                            "debirentier" =>$debirentier,
                            "debirentier_different" =>$property->getDebirentierDifferent(),
                        ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/facture-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        $data = [
                            'current_number'=>$current_number_string,
                            'type'       => Invoice::TYPE_NOTICE_EXPIRY,
                            'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                            'date'       => $now_date,
                            'target'           => $former_destinataire,
                            'form'       => $dataForm,
                            'day'  => $now_date->format('d'),
                            'month'     => $now_date->format('m'),
                            'year' => $now_date->format('Y'),
                            'property'           => $property,
                            'warrant'    => [
                                'id'         => $property->getWarrant()->getId(),
                                'type'       => $property->getWarrant()->getType(),
                                'firstname'  => $property->getWarrant()->getFirstname(),
                                'lastname'   => $property->getWarrant()->getLastname(),
                                'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                                'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                                'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                            ],
                            "debirentier" =>$debirentier,
                            "debirentier_different" =>$property->getDebirentierDifferent(),
                        ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/facture-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_INVOICE);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_INVOICE, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        $d = new DateTime('First day of next month');

                        $current_date = [
                            'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                            'current_month' => date('m'),
                            'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                            'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                            'month_n'       => $d->format('m'),
                            'year'          => $d->format('Y'),
                        ];


                        $dataInvoice = [
                            'date'       => $current_date,
                            'type'       => Invoice::TYPE_NOTICE_EXPIRY,
                            'recursion'  => Invoice::RECURSION_OTP,
                            'number'     => Invoice::formatNumber($current_number, Invoice::TYPE_NOTICE_EXPIRY),
                            'number_int' => $current_number,

                            'amount'           => ($former_type==1)?$former_montant:0,
                            'honoraryRates'    => ($former_type==2)?$former_montant:0,
                            'honoraryRatesTax' => ($former_facturation==2 && $former_type==2)?$honotaires_ttc:0,
                            'period'           => $former_period,
                            'target'           => $former_destinataire,
                            'reason'           => $former_motif,
                            'label'            => $former_motif,
                            'montantht'    => ($former_type==2)?$former_montant:0,
                            'montantttc'    => ($former_facturation==2 && $former_type==2)?$honotaires_ttc:0,
                            'email'    => $former_email,
                            'property'   => [
                                'id'         => $property->getId(),
                                'firstname'  => $property->getFirstname1(),
                                'lastname'   => $property->getLastname1(),
                                'firstname2' => $property->getFirstname2(),
                                'lastname2'  => $property->getLastname2(),
                                'address'    => $property->getAddress(),
                                'postalcode' => $property->getPostalCode(),
                                'city'       => $property->getCity(),
                                'is_og2i'       => $property->getClauseOG2I(),
                                'buyerfirstname'  => $property->getBuyerFirstname(),
                                'buyerlastname'   => $property->getBuyerLastname(),
                                'buyeraddress'    => $property->getBuyerAddress(),
                                'buyerpostalcode' => $property->getBuyerPostalCode(),
                                'buyercity'       => $property->getBuyerCity(),

                                'condominiumFees' => $property->getCondominiumFees(),
                            ],
                            'warrant'    => [
                                'id'         => $property->getWarrant()->getId(),
                                'type'       => $property->getWarrant()->getType(),
                                'firstname'  => $property->getWarrant()->getFirstname(),
                                'lastname'   => $property->getWarrant()->getLastname(),
                                'address'    => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactAddress() : $property->getWarrant()->getAddress(),
                                'postalcode' => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactPostalCode() : $property->getWarrant()->getPostalCode(),
                                'city'       => ($property->getWarrant()->hasFactAddress()) ? $property->getWarrant()->getFactCity() : $property->getWarrant()->getCity(),
                            ],
                            "debirentier" =>$debirentier,
                            "debirentier_different" =>$property->getDebirentierDifferent(),

                        ];
                    
                       
                       
                        $invoice = new Invoice();
                        $invoice->setCategory(3);
                        $invoice->setType($dataInvoice['type']);


                        $invoice->setNumber($dataInvoice['number_int']);
                        $invoice->setData($dataInvoice);
                        if($former_type == 1){$invoice->setFile($file);}
                        if($former_type == 2){$invoice->setFile2($file);}
                        $invoice->setDate(new DateTime());
                        $invoice->setProperty($property);
                        $manager->persist($invoice);
                        $file->setInvoice($invoice);

                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'facture manuelle créé avec succès';
                        
                    }
                    
                    

                    return $this->render('generated_files/facture.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/facture.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'generated_files' => $generated_files,
        ]);
    }




     /**
     * @Route("/generated_files/avoir/{propertyId}", name="generate_avoir", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_avoir(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 2)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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
        $defaultData = [
            'montant' => 0,
        ];
        $defaultText = '<table style="width:100%;border:solid 1px #000;font-size:10pt"cellspacing=0><tr><td style="width:60%;border-right:solid 1px #000"><table style=width:100%;border-collapse:collapse><tr style=width:100%><td style="border-bottom:solid 1px #000;border-collapse:collapse;width:50%">Numéro du bien: <b>'.$property->getId().'</b><td style="border-bottom:solid 1px #000;border-collapse:collapse;width:50%">Numéro de client: <b>'.$property->getWarrant()->getId().'</b><tr><td colspan=2><br><br>Madame, Monsieur,<br><br>Nous vous prions de trouver ci-joint votre avoir relatif à la facture [facture] pour la période [period]  <br>concernant le bien de:<br><br> '.$property->getFirstname1().' '.$property->getLastname1().' - '.$property->getFirstname2().' '.$property->getLastname2().'<br>'.$property->getAddress().'<br>'.$property->getPostalCode().'<br>'.$property->getCity().' <br><br><br>Nous restons à votre disposition.<br><br>Bien cordialement.<br><br><b>Univers Viager</b><br><br></table><td style=width:40%><br><br><table style=width:100%><col style=width:60%;text-align:left><col style=width:40%;text-align:right><thead><tr><th colspan=2 style=text-align:center><b>[resume]</b><tbody><tr><td>[montantht]</td></tr><tr><td>[tva]</td></tr><tr><td>[montantttc]</td></tr></table><br><br><br><br><br></table>';
        $form = $this->createFormBuilder($defaultData)
        ->add('numero_de_facture', TextType::class, [ 'required' => false])
        ->add('type', ChoiceType::class, ['choices' => ['Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5  ], 'choice_translation_domain' => false])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'générer la facture' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $dataForm['texte']=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $dataForm['texte']);
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $fileName = "";
                $facture_label = "";
               
                $former_type = $dataForm['type'];
                $former_status = $dataForm['status'];
                $former_numero_de_facture = $dataForm['numero_de_facture'];
                $last4 = substr($former_numero_de_facture, -4);
                $type = null;

                if(in_array($former_type, [1, 3, 4], true)){
                    //rente
                    $type = 1;
                    $fileName = "AV".substr($former_numero_de_facture,2)." - R.pdf";
                    $facture_label = "AV".substr($former_numero_de_facture,2)." R";
                }else{
                    //honoraire
                    $type = 2;
                    $fileName = "AV".substr($former_numero_de_facture,2)." - H.pdf";
                    $facture_label = "AV".substr($former_numero_de_facture,2)." H";
                }
                $invoice = $manager
                ->getRepository(Invoice::class)
                ->findAvoirTogenerate($last4,$type);
                $dataFacture=$invoice->getData();

                if(in_array($former_type, [1, 3, 4], true)){//rente
                     $former_montant = (float) $dataFacture['property']['annuity'];
                      $former_montant_f = number_format($former_montant, 2, '.', ' ');
                }else{//honoraire
                    $honoraires_ttc = (float) $dataFacture['property']['honoraryRates'];

                    $montant_tva = (float) $dataFacture['property']['honoraryRatesTax'];
                    $former_montant = $honoraires_ttc - $montant_tva;

                    // formatage POUR L'AFFICHAGE SEULEMENT
                    $former_montant_f = number_format($former_montant, 2, '.', ' ');
                    $montant_tva_f = number_format($montant_tva, 2, '.', ' ');
                    $honoraires_ttc_f = number_format($honoraires_ttc, 2, '.', ' ');
                }

                $former_period =  "";
                if(in_array($former_type, [5, 4], true)){//manuel
                    $former_period =  $dataFacture["period"];
                }else{//avis , copro 
                    $former_period =  $dataFacture["date"]["month"].' '.$dataFacture["date"]["year"];
                }
                

                try {
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $defaultText = str_replace(
                            '[facture]',
                            $former_numero_de_facture,
                            $defaultText
                        );

                         $defaultText = str_replace(
                            '[period]',
                            $former_period,//trouver la periode
                            $defaultText
                        );

                       

                        if(in_array($former_type, [1, 3, 4], true)){//rente , copro, rente manuelle
                            $resume='Détails de l\'appel de la période';
                            $total='Détails de l\'appel de la période';
                            $defaultText = str_replace(
                                '[montantht]',
                                'Détails de l\'appel de la période<td>'.$former_montant_f. ' €',
                                $defaultText
                            );
                            $defaultText = str_replace(
                                '[tva]',
                                '',
                                $defaultText
                            );
                            $defaultText = str_replace(
                                '[montantttc]',
                                '',
                                $defaultText
                            );

                        }else if(in_array($former_type, [2,5], true)){//honoraires
                            $resume='Coût des honoraires de la période';
                            $total='Total TTC des honoraires';
                            $defaultText = str_replace(
                                '[montantht]',
                                'Honoraires H.T<td>'.$former_montant_f. ' €',
                                $defaultText
                            );
                           
                            $defaultText = str_replace(
                                '[tva]',
                                'Tva 20%<td>'.$montant_tva_f. ' €',
                                $defaultText
                            );
                            $defaultText = str_replace(
                                '[montantttc]',
                                'Total TTC des honoraires<td>'.$honoraires_ttc_f. ' €',
                                $defaultText
                            );

                          
                        }
                         $defaultText = str_replace(
                            '[resume]',
                            $resume,
                            $defaultText
                        );
                        
                        
                        

                        $message = 'Aperçu créé avec succès';



                        $form = $this->createFormBuilder($defaultData)
                        ->add('numero_de_facture', TextType::class, ['data' =>  $former_numero_de_facture, 'required' => false])
                        ->add('type', ChoiceType::class, ['data' =>  $former_type,'choices' => ['Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5 ], 'choice_translation_domain' => false])
                        ->add('status', ChoiceType::class, ['data' => $former_status,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'générer la facture' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' =>$defaultText,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                       
                        $dataFacture['current_day'] = utf8_encode(strftime('%A %e %B %Y'));
                        $dataFacture['date'] = $current_date;
                        $dataFacture['old_number_int'] = $last4;
                        $dataFacture['form'] = $dataForm;
                        $dataFacture['old_number'] = $former_numero_de_facture;
                        $dataFacture['number_int'] = $last4;
                        $dataFacture['number'] = $facture_label;
                        $dataFacture['period'] = $former_period;

                        
                        $pdf->writeHTML($this->twig->render('generated_files/avoir-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataFacture]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_INVOICE);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 2)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataFacture['current_day'] = utf8_encode(strftime('%A %e %B %Y'));
                        $dataFacture['date'] = $current_date;
                        $dataFacture['old_number_int'] = $last4;
                        $dataFacture['form'] = $dataForm;
                        $dataFacture['old_number'] = $former_numero_de_facture;
                        $dataFacture['number_int'] = $last4;
                        $dataFacture['number'] = $facture_label;
                        $dataFacture['period'] = $former_period;

                        
                        $pdf->writeHTML($this->twig->render('generated_files/avoir-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataFacture]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_INVOICE);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_INVOICE, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        $invoice = new Invoice();
                        $invoice->setCategory(4);
                        $invoice->setType(3);


                        $invoice->setNumber($dataFacture['number_int']);
                        $invoice->setData($dataFacture);
                        if(in_array($former_type, [1, 3, 4], true)){//rente
                            $message = 'avoir rente créé avec succès';
                            $invoice->setFile($file);
                        }
                        if(in_array($former_type, [2, 5], true)){//honoraires
                            $message = 'avoir honoraires créé avec succès';
                            $invoice->setFile2($file);
                        }
                        $invoice->setDate(new DateTime());
                        $invoice->setProperty($property);
                        $manager->persist($invoice);
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/avoir.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/avoir.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'generated_files' => $generated_files,
        ]);
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

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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
        $defaultText = '<h1>MANDAT SEPA</h1> <h3>Référence Unique du Mandat de Prélèvement SEPA</h3> <h3>[title] UV</h3> En signant ce formulaire de mandat, vous autorisez UNIVERS VIAGER à envoyer des instructions à votre banque pour débiter votre compte, et votre banque à débiter votre compte conformément aux instructions de UNIVERS VIAGER. Vous bénéficiez du droit d’être remboursé par votre banque selon les conditions décrites dans la convention que vous avez passée avec elle. Une demande de remboursement doit être présentée : <br> - dans les 8 semaines suivant la date de débit de votre compte pour un prélèvement autorisé, <br> - dans les 13 mois en cas de prélèvement non autorisé. <br><br> Votre Nom : [target_nom] [target_prenom] [target_nom2] [target_prenom2] <br><br> Votre adresse : [target_address] [target_address2] <br><br> <u>Les coordonnées de votre compte</u><br> IBAN (Numéro d\'identification du compte bancaire) : [target_bank_iban_1]<br> BIC (Code international d\'identification de votre) : [target_bank_bic_1]<br> Domiciliation bancaire : [target_bank_domiciliation_1] <br><br> <u>Le créancier</u><br> [creantier] <br><br> Type de paiement : Paiement récurrent / répétitif <br><br> Fait à <br><br> Le <br><br> Signature,';
        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_destinataire = $dataForm['destinataire'];
                $former_status = $dataForm['status'];
                $fileName="Mandat sepa ".$property->getId().".pdf";
                
               
                

                try {

                    $civilite = "";
                    $target_nom = "";
                    $target_prenom = "";
                    $target_address = "";
                    $target_nom2 = "";
                    $target_prenom2 = "";
                    $target_address2 = "";
                    $target_postal = "";
                    $target_ville = "";
                    $target_bank_iban_1 = "";
                    $target_bank_bic_1 = "";
                    $target_bank_domiciliation_1 = "";
                    $creantier = "E.U.R.L V.Gibelin Conseils – 58 rue Fondaudège 33000 BORDEAUX France<br>
                            Identifiant créancier SEPA : FR12ZZZ886B32";

                    switch ($former_destinataire) {
                        case 'Crédirentier':
                            $civilite = $property->getCivilite1Label();
                            $target_nom = $property->getFirstname1();
                            $target_prenom = $property->getLastname1();
                            $target_address = $property->getAdresseCredirentier1();
                            $target_postal = $property->getCodePostalCredirentier1();
                            $target_nom2 = $property->getFirstname2();
                            $target_prenom2 = $property->getLastname2();
                            $target_address2= $property->getAdresseCredirentier2();
                            $target_ville = $property->getVilleCredirentier1();
                            $target_bank_iban_1 = $property->bank_iban_1;
                            $target_bank_bic_1 = $property->bank_bic_1;
                            $target_bank_domiciliation_1 = $property->bank_domiciliation_1;
                            
                            break;
                        case 'Débirentier':
                            $civilite = $property->getCiviliteDebirentierLabel();
                            $target_nom = $property->getNomDebirentier();
                            $target_prenom = $property->getPrenomDebirentier();
                            $target_address = $property->getAddresseDebirentier();
                            $target_postal = $property->getCodePostalDebirentier();
                            $target_nom2 = $property->getNomDebirentier2();
                            $target_prenom2 = $property->getPrenomDebirentier2();
                            $target_address2 = $property->getAddresseDebirentier2();
                            $target_ville = $property->getVilleDebirentier2();
                            $target_bank_iban_1 = $property->bank_iban_1;
                            $target_bank_bic_1 = $property->bank_bic_1;
                            $target_bank_domiciliation_1 = $property->bank_domiciliation_1;
                            
                            break;
                        case 'Mandant':
                            $civilite = "";
                            $target_nom = $property->getWarrant()->getFirstname();
                            $target_prenom = $property->getWarrant()->getLastname();
                            $target_address = $property->getWarrant()->getAddress();
                            $target_postal = $property->getWarrant()->getPostalCode();
                            $target_nom2 = "";
                            $target_prenom2 = "";
                            $target_address2 = "";
                            $target_ville = $property->getWarrant()->getCity();
                            $target_bank_iban_1 = $property->getWarrant()->getBankIban();
                            $target_bank_bic_1 = $property->getWarrant()->getBankBic();
                            $target_bank_domiciliation_1 = $property->getWarrant()->getBankDomiciliation();
                            
                            break;
                        
                        default:
                            $civilite = "";
                            break;
                    }
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = str_replace(
                            '[title]',
                            $property->getTitle(),
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_nom]',
                            $target_nom,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_nom2]',
                            $target_nom2,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_prenom]',
                            $target_prenom,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_prenom2]',
                            $target_prenom2,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[ville_bien]',
                            $property->getCity(),
                            $former_texte
                        );

                        $former_texte = str_replace(
                            '[target_address]',
                            $target_address,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_address2]',
                            $target_address2,
                            $former_texte
                        );

                        $former_texte = str_replace(
                            '[target_bank_iban_1]',
                            $target_bank_iban_1,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_bank_bic_1]',
                            $target_bank_bic_1,
                            $former_texte
                        );

                        $former_texte = str_replace(
                            '[target_bank_domiciliation_1]',
                            $target_bank_domiciliation_1,
                            $former_texte
                        );

                        $former_texte = str_replace(
                            '[creantier]',
                            $creantier,
                            $former_texte
                        );
                        



                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                            
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                        'civilite' => $civilite,
                        'target_nom' => $target_nom,
                        'target_prenom' => $target_prenom,
                        'target_address' => $target_address,
                        'target_nom2' => $target_nom2,
                        'target_prenom2' => $target_prenom2,
                        'target_address2' => $target_address2,
                        'target_postal' => $target_postal,
                        'target_ville' => $target_ville,
                        'target_bank_iban_1' => $target_bank_iban_1,
                        'target_bank_bic_1' => $target_bank_bic_1,
                        'target_bank_domiciliation_1' => $target_bank_domiciliation_1,
                        'creantier' =>$creantier,
                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-mandat-sepa-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property' =>  $property,
                        'civilite' => $civilite,
                        'target_nom' => $target_nom,
                        'target_prenom' => $target_prenom,
                        'target_address' => $target_address,
                        'target_nom2' => $target_nom2,
                        'target_prenom2' => $target_prenom2,
                        'target_address2' => $target_address2,
                        'target_postal' => $target_postal,
                        'target_ville' => $target_ville,
                        'target_bank_iban_1' => $target_bank_iban_1,
                        'target_bank_bic_1' => $target_bank_bic_1,
                        'target_bank_domiciliation_1' => $target_bank_domiciliation_1,
                        'creantier' =>$creantier,
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-mandat-sepa-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = 'mandat sepa créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/courrier-mandat-sepa.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
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
            'generated_files' => $generated_files,
        ]);
    }

    /**
     * Transforme un slug en titre lisible
     *
     * @param string $slug
     * @return string
     */
    public function deslugify(string $slug): string
    {
        $dropdownItems = [
            'mandat-de-gestion' => 'Mandat de gestion',
            'courrier-d-indexation' => "Courrier d'indexation",
            'courrier-1er-contact' => 'Courrier 1er contact',
            'courrier-d-appel-des-charges-trimestrielles-de-copro' => 'Courrier appel des charges trimestrielles de copro',
            'courrier-regularisation-charges-copro' => 'Courrier régularisation charges copro',
            'courrier-abandon-du-duh' => 'Courrier abandon du DUH',
            'courrier-libre' => 'Courrier libre',
        ];

        // Remplacer les tirets par des espaces
        $title = $dropdownItems[$slug];


        return $title;
    }


    /**
     * @Route("/generated_files/generique/{type}/{propertyId}", name="generate_fichier_generique", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_fichier_generique(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
            
        $type = $this->deslugify($request->get('type'));

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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

        
        $defaultText = 'écrivez le texte en vous servant des variables ';
               

        


        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('type_de_fichier', ChoiceType::class, [
            'data' => $type,
            'choices'  => [
                'Mandat de gestion' => "Mandat de gestion",
                'Courrier appel des charges trimestrielles de copro' => "Courrier appel des charges trimestrielles de copro",
                'Courrier régularisation charges copro' => "Courrier régularisation charges copro",
                'Courrier abandon du DUH' => "Courrier abandon du DUH",
                "Courrier d'indexation" => "Courrier d'indexation",
                "Courrier d'indexation og2i" => "Courrier d'indexation og2i",
                'Courrier 1er contact' => "Courrier 1er contact",
                'Courrier libre' => "Courrier libre",
            ],
        ])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_type_de_fichier = $dataForm['type_de_fichier'];
                $former_destinataire = $dataForm['destinataire'];
                $fileName = $former_type_de_fichier.' '.$property->getId().'.pdf';
                
                $target_civilite1 = "";
                $target_civilite2 = "";
                $target_nom = "";
                $target_prenom = "";
                $target_address = "";
                $target_nom2 = "";
                $target_prenom2 = "";
                $target_address2 = "";
                $target_postal = "";
                $target_ville = "";
                $target_bank_iban_1 = "";
                $target_bank_bic_1 = "";
                $target_bank_domiciliation_1 = "";
                $creantier = "E.U.R.L V.Gibelin Conseils – 58 rue Fondaudège 33000 BORDEAUX France<br>
                        Identifiant créancier SEPA : FR12ZZZ886B32";

                switch ($former_destinataire) {
                    case 'Crédirentier':
                        $target_civilite1 = $property->getCivilite1Label();
                        $target_nom = $property->getFirstname1();
                        $target_prenom = $property->getLastname1();
                        $target_address = $property->getAdresseCredirentier1();
                        $target_postal = $property->getCodePostalCredirentier1();
                        $target_civilite2 = $property->getCivilite2Label();
                        $target_nom2 = $property->getFirstname2();
                        $target_prenom2 = $property->getLastname2();
                        $target_address2= $property->getAdresseCredirentier2();
                        $target_ville = $property->getVilleCredirentier1();
                        $target_bank_iban_1 = $property->bank_iban_1;
                        $target_bank_bic_1 = $property->bank_bic_1;
                        $target_bank_domiciliation_1 = $property->bank_domiciliation_1;
                        
                        break;
                    case 'Débirentier':
                        $target_civilite1 = $property->getCiviliteDebirentierLabel();
                        $target_nom = $property->getNomDebirentier();
                        $target_prenom = $property->getPrenomDebirentier();
                        $target_address = $property->getAddresseDebirentier();
                        $target_postal = $property->getCodePostalDebirentier();
                        $target_civilite2 = $property->civilite_debirentier2;
                        $target_nom2 = $property->getNomDebirentier2();
                        $target_prenom2 = $property->getPrenomDebirentier2();
                        $target_address2 = $property->getAddresseDebirentier2();
                        $target_ville = $property->getVilleDebirentier2();
                        $target_bank_iban_1 = $property->bank_iban_1;
                        $target_bank_bic_1 = $property->bank_bic_1;
                        $target_bank_domiciliation_1 = $property->bank_domiciliation_1;
                        
                        break;
                    case 'Mandant':
                        $target_civilite1 = "";
                        $target_civilite2 = "";
                        $target_nom = $property->getWarrant()->getFirstname();
                        $target_prenom = $property->getWarrant()->getLastname();
                        $target_address = $property->getWarrant()->getAddress();
                        $target_postal = $property->getWarrant()->getPostalCode();
                        $target_nom2 = "";
                        $target_prenom2 = "";
                        $target_address2 = "";
                        $target_ville = $property->getWarrant()->getCity();
                        $target_bank_iban_1 = $property->getWarrant()->getBankIban();
                        $target_bank_bic_1 = $property->getWarrant()->getBankBic();
                        $target_bank_domiciliation_1 = $property->getWarrant()->getBankDomiciliation();
                        
                        break;
                    
                    default:
                        $target_civilite1 = "";
                        $target_civilite2 = "";
                        break;
                }
                

                try {

                    
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = $this->replacer->replaceText($property,$former_texte,$former_destinataire);
                      


                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                        ->add('type_de_fichier', ChoiceType::class, [
                            'data' => $type,
                            'choices'  => [
                                'Mandat de gestion' => "Mandat de gestion",
                                'Courrier appel des charges trimestrielles de copro' => "Courrier appel des charges trimestrielles de copro",
                                'Courrier régularisation charges copro' => "Courrier régularisation charges copro",
                                'Courrier abandon du DUH' => "Courrier abandon du DUH",
                                'Courrier d\'indexation' => "Courrier d\'indexation",
                                'Courrier d’indexation og2i' => "Courrier d’indexation og2i",
                                'Courrier 1er contact' => "Courrier 1er contact",
                                'Courrier libre' => "Courrier libre",
                            ],
                        ])
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                        'civilite'           => $civilite,

                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-generique-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                        'civilite'           => $civilite
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-generique-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = $former_type_de_fichier.' créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/courrier-generique.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'type' => $type,
                        'generated_files' => $generated_files,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-generique.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'type' => $type,
            'generated_files' => $generated_files,
        ]);
    }






     /**
     * @Route("/generated_files/mandat-gestion/{propertyId}", name="generate_mandat_gestion", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_mandat_gestion(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
            
      

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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

       
        
        $defaultText = '<div><strong><br></strong><p >[civilite1] [nom1] [prenom1],<br> [civilite2] [nom2] [prenom2]</p><strong><br></strong><p >Comme convenu, veuillez trouver ci-joint deux exemplaires du mandat de gestion et « Informations précontractuelles » qui nous permettrons dès leur réception de commencer la prise en charge de la gestion de votre viager.</p><p >Merci de :</p><p >- Parapher en bas de toutes les pages (recto et verso) avec vos initiales</p><p >- Signer en page 4 en dessous de «&nbsp;Le mandant&nbsp;» <br>et recopier les mentions « Lu et approuvé, bon pour mandat »</p><p >- Signer en page 8</p><p >- Vous nous retournez un des deux exemplaires à l’adresse indiquée en haut à gauche et conservez le second.</p><strong><br></strong><p >Nous restons, bien entendu, à votre disposition pour tous renseignements complémentaires et vous prions de croire en l’expression de nos sincères salutations.</p><p ><br data-mce-bogus="1"></p><strong id="docs-internal-guid-70c3856a-7fff-8543-363d-d9d1e0f2e377"><br>Le Service Gestion,</strong></div>';


        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
       
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_destinataire = $dataForm['destinataire'];
                $fileName = 'Mandat de gestion '.$property->getId().'.pdf';
                
                $target_civilite1 = "";
                $target_civilite2 = "";
                $target_nom = "";
                $target_prenom = "";
                $target_address = "";
                $target_nom2 = "";
                $target_prenom2 = "";
                $target_address2 = "";
                $target_postal = "";
                $target_ville = "";
                $target_bank_iban_1 = "";
                $target_bank_bic_1 = "";
                $target_bank_domiciliation_1 = "";
                $creantier = "E.U.R.L V.Gibelin Conseils – 58 rue Fondaudège 33000 BORDEAUX France<br>
                        Identifiant créancier SEPA : FR12ZZZ886B32";

                switch ($former_destinataire) {
                    case 'Crédirentier':
                        $target_civilite1 = $property->getCivilite1Label();
                        $target_nom = $property->getFirstname1();
                        $target_prenom = $property->getLastname1();
                        $target_address = $property->getAdresseCredirentier1();
                        $target_postal = $property->getCodePostalCredirentier1();
                        $target_civilite2 = $property->getCivilite2Label();
                        $target_nom2 = $property->getFirstname2();
                        $target_prenom2 = $property->getLastname2();
                        $target_address2= $property->getAdresseCredirentier2();
                        $target_ville = $property->getVilleCredirentier1();
                        $target_bank_iban_1 = $property->bank_iban_1;
                        $target_bank_bic_1 = $property->bank_bic_1;
                        $target_bank_domiciliation_1 = $property->bank_domiciliation_1;
                        
                        break;
                    case 'Débirentier':
                        $target_civilite1 = $property->getCiviliteDebirentierLabel();
                        $target_nom = $property->getNomDebirentier();
                        $target_prenom = $property->getPrenomDebirentier();
                        $target_address = $property->getAddresseDebirentier();
                        $target_postal = $property->getCodePostalDebirentier();
                        $target_civilite2 = $property->civilite_debirentier2;
                        $target_nom2 = $property->getNomDebirentier2();
                        $target_prenom2 = $property->getPrenomDebirentier2();
                        $target_address2 = $property->getAddresseDebirentier2();
                        $target_ville = $property->getVilleDebirentier2();
                        $target_bank_iban_1 = $property->bank_iban_1;
                        $target_bank_bic_1 = $property->bank_bic_1;
                        $target_bank_domiciliation_1 = $property->bank_domiciliation_1;
                        
                        break;
                    case 'Mandant':
                        $target_civilite1 = "";
                        $target_civilite2 = "";
                        $target_nom = $property->getWarrant()->getFirstname();
                        $target_prenom = $property->getWarrant()->getLastname();
                        $target_address = $property->getWarrant()->getAddress();
                        $target_postal = $property->getWarrant()->getPostalCode();
                        $target_nom2 = "";
                        $target_prenom2 = "";
                        $target_address2 = "";
                        $target_ville = $property->getWarrant()->getCity();
                        $target_bank_iban_1 = $property->getWarrant()->getBankIban();
                        $target_bank_bic_1 = $property->getWarrant()->getBankBic();
                        $target_bank_domiciliation_1 = $property->getWarrant()->getBankDomiciliation();
                        
                        break;
                    
                    default:
                        $target_civilite1 = "";
                        $target_civilite2 = "";
                        break;
                }
                

                try {

                    
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = $this->replacer->replaceText($property,$former_texte,$former_destinataire);
                        
                        $former_texte = str_replace(
                            '[civilite1]',
                            $target_civilite1,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[nom1]',
                            $target_nom,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[prenom1]',
                            $target_prenom,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[civilite2]',
                            $target_civilite2,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[nom2]',
                            $target_nom2,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[prenom2]',
                            $target_prenom2,
                            $former_texte
                        );


                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                      
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,

                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/mandat-gestion-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/mandat-gestion-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = $former_type_de_fichier.' créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/mandat-gestion.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/mandat-gestion.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'generated_files' => $generated_files,
        ]);
    }





     /**
     * @Route("/generated_files/cpc/{propertyId}", name="generate_cpc", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_cpc(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
            
     

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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

        
        $defaultText = '<p >[target_civilite1] [target_nom1] [target_prenom1],<br>[target_civilite2] [target_nom2] [target_prenom2],<br> </p><p><strong><br></strong></p><p >Un mandat de gestion numéro [num_mandat] nous a été confié dans le cadre de votre viager concernant le bien situé au [adresse_bien] [code_postal_bien] [ville_bien] du bien selon acte passé chez Maître [nom_notaire] [prenom_notaire] à [adresse_notaire] [code_postal_notaire] [ville_notaire] en date du [date_acte] et vous trouverez ci-dessous l’ensemble des missions que nous accomplissons&nbsp;:<br></p><p >- Appeler et encaisser toute somme représentative des rentes viagères relatives au bien géré sur le compte de gestion Univers Viager et les reverser au CRÉDIRENTIER à la périodicité convenue dans l’acte authentique de vente. Assurer le suivi des règlements.&nbsp;</p><p >- Procéder à la révision annuelle de la rente viagère (indexation).</p><p >En cas d’abandon de jouissance du bien de la part du CRÉDIRENTIER, modifier le montant de la rente selon les clauses prévues dans l’acte authentique de vente.</p><p > - Vérifier annuellement la conformité et validité des assurances et entretiens obligatoires.</p><p >- S’il s’agit d’un bien en copropriété. Procéder à l’encaissement et au décaissement des avances de charges trimestrielles dues par le CRÉDIRENTIER selon la répartition convenue entre les parties dans l’acte authentique de vente et procéder à réception du décompte annuel de charges transmis par le DÉBIRENTIER à la régularisation annuelle entre les parties.</p><p >- Informer les parties de la répartition des charges relatives aux travaux selon les conventions passées dans l’acte à 1ère demande.</p></li><p><strong><br></strong></p><p >&nbsp; Nous sommes donc votre interlocuteur privilégié pour tous les aspects relatifs aux missions sus-indiqués.</p><p >A ce titre, les versements mensuels des rentes, soit actuellement montant de la rente €, seront désormais effectués sur notre compte de gestion à compter du 1er octobre (1er du mois suivant la date de ce courrier).</p><p><strong><br></strong></p><p >- À chaque fin de mois, vous recevrez un avis d’échéance concernant la rente du mois suivant. Puis, à réception des fonds, nous vous enverrons une quittance attestant le bon paiement de cette rente.<strong></strong></p><p >- Vous trouverez joint à ce courrier une autorisation de prélèvement, celle-ci nous servira à&nbsp;:</p><p >- Prélever chaque mois la rente viagère</p><p >- Prélever chaque mois nos honoraires de gestion</p><p >- Prélever chaque trimestre votre quote-part locative des charges de copropriété</p><p >- Prélever chaque année, en fonction du décompte annuel de charges, le solde éventuel des charges de copropriété restant dû.</p><p >- D’autre part, en ce qui concerne les charges de copropriété, il est convenu que le(s) crédirentier(s) versent une avance trimestrielle de la quote-part locative au(x) débirentier(s). Compte tenu, du montant de cette quote part du dernier décompte annuel de charges de copropriété, nous provisionnerons la somme trimestrielle de « Quote part locative - charges trimestrielles” €.<strong></strong></p><p >- Pour la bonne gestion de ce dossier, et si ce n’est déjà fait, merci de bien vouloir nous faire parvenir&nbsp;:</p><p >- Votre RIB</p><p >- Votre attestation d’assurance habitation</p><p >- Votre attestation d’entretien chaudière, clim, pompe à chaleur</p><p >- La facture de ramonage de votre cheminée/poêle<br><strong></strong></p><p >Nous restons, bien entendu, à votre disposition pour tous renseignements complémentaires et vous prions de croire en l’expression de nos sincères salutations.</p><p><strong><br></strong></p><p><br data-mce-bogus="1"></p><p >Le Service Gestion,</p><p><br data-mce-bogus="1"></p>';
              
        


        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_destinataire = $dataForm['destinataire'];
                $fileName = 'Courrier de premier contact '.$property->getId().'.pdf';
                
                $target_civilite1 = "";
                $target_civilite2 = "";
                $target_nom1 = "";
                $target_prenom1 = "";
                $target_address1 = "";
                $target_nom2 = "";
                $target_prenom2 = "";
                $target_address2 = "";
                $target_postal1 = "";
                $target_ville1 = "";
                
                $num_mandat = $property->num_mandat_gestion;
                $adresse_bien = $property->getGoodAddress();
                $code_postal_bien = $property->getPostalCode();
                $ville_bien = $property->getCity();
                $nom_notaire = $property->nom_notaire;
                $prenom_notaire = $property->prenom_notaire;
                $adresse_notaire = $property->addresse_notaire;
                $code_postal_notaire = $property->code_postal_notaire;
                $ville_notaire = $property->ville_notaire;
                $date_acte = $property->date_duh->format('Y-m-d');

                switch ($former_destinataire) {
                    case 'Crédirentier':
                        $target_civilite1 = $property->getCivilite1Label();
                        $target_nom1 = $property->getFirstname1();
                        $target_prenom1 = $property->getLastname1();
                        $target_address1 = $property->getAdresseCredirentier1();
                        $target_postal1 = $property->getCodePostalCredirentier1();
                        $target_civilite2 = $property->getCivilite2Label();
                        $target_nom2 = $property->getFirstname2();
                        $target_prenom2 = $property->getLastname2();
                        $target_address2= $property->getAdresseCredirentier2();
                        $target_ville1 = $property->getVilleCredirentier1();
                        
                        break;
                    case 'Débirentier':
                        $target_civilite1 = $property->getCiviliteDebirentierLabel();
                        $target_nom1 = $property->getNomDebirentier();
                        $target_prenom1 = $property->getPrenomDebirentier();
                        $target_address1 = $property->getAddresseDebirentier();
                        $target_postal = $property->getCodePostalDebirentier();
                        $target_civilite2 = $property->civilite_debirentier2;
                        $target_nom2 = $property->getNomDebirentier2();
                        $target_prenom2 = $property->getPrenomDebirentier2();
                        $target_address2 = $property->getAddresseDebirentier2();
                        $target_ville1 = $property->getVilleDebirentier2();
                        
                        break;
                    case 'Mandant':
                        $target_civilite1 = "";
                        $target_civilite2 = "";
                        $target_nom1 = $property->getWarrant()->getFirstname();
                        $target_prenom1 = $property->getWarrant()->getLastname();
                        $target_address1 = $property->getWarrant()->getAddress();
                        $target_postal = $property->getWarrant()->getPostalCode();
                        $target_nom2 = "";
                        $target_prenom2 = "";
                        $target_address2 = "";
                        $target_ville1 = $property->getWarrant()->getCity();
                        
                        break;
                    
                    default:
                        $target_civilite1 = "";
                        $target_civilite2 = "";
                        break;
                }
                

                try {

                    
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = $this->replacer->replaceText($property,$former_texte,$former_destinataire);
                        
                        $former_texte = $this->replacer->replaceOthersText($property,$former_texte,$former_destinataire);
                       


                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                      
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,

                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-premier-contact-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-premier-contact-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = $former_type_de_fichier.' créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/courrier-premier-contact.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
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
            'generated_files' => $generated_files,
        ]);
    }


    
    


    /**
     * @Route("/generated_files/courrier-indexation/{propertyId}", name="generate_courrier_indexation", requirements={"propertyId"="\d+"})
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
            
     

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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

        
        $defaultText = '<div><p >[target_civilite1] [target_nom1] [target_prenom1],<br>[target_civilite2] [target_nom2] [target_prenom2],<br><strong></strong></p><p >Dans le cadre du mandat de gestion qui nous a été confié, nous venons de procéder au calcul de l’indexation de la rente viagère concernant le bien [nom_du_bien] situé au [adresse_bien] [code_postal_bien] [ville_bien].<strong></strong></p><p >Le calcul d’indexation :<strong></strong></p><p >Rente initiale de l’Acte : [montant_initial] €<strong></strong></p><p >/<strong></strong></p><p >Nouvel indice : [nouvel_indice]&nbsp;<strong></strong></p><p >x<strong></strong></p><p >Indice de référence : [Valeur_indice_reference]<strong></strong></p><p >=<strong></strong></p><p >Le nouveau montant de la rente viagère sera ainsi porté à [nv_montant] €&nbsp;</p><p >pour le virement de la rente du mois de [mois_indexation].<strong><br></strong></p><p >- Les honoraires de gestion passent eux à [Montant_des_honoraires] €</p><p><strong><br></strong></p><p >La prochaine révision interviendra pour le mois de [date_prochaine_revision].<strong></strong></p><p >Nous restons, bien entendu, à votre disposition pour tous renseignements complémentaires et vous prions de croire en l’expression de nos sincères salutations.</p><p ><strong><br data-mce-bogus="1"></strong></p><p><br>Le Service Gestion,</strong><br></p></div>';
              
        


        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_destinataire = $dataForm['destinataire'];
                $fileName = 'Courrier d\'indexation '.$property->getId().'.pdf';
                
                $target_civilite1 = "";
                $target_civilite2 = "";
                $target_nom1 = "";
                $target_prenom1 = "";
                $target_address1 = "";
                $target_nom2 = "";
                $target_prenom2 = "";
                $target_address2 = "";
                $target_postal1 = "";
                $target_ville1 = "";

                $adresse_bien = $property->getGoodAddress();
                $code_postal_bien = $property->getPostalCode();
                $ville_bien = $property->getCity();

                switch ($former_destinataire) {
                    case 'Crédirentier':
                        $target_civilite1 = $property->getCivilite1Label();
                        $target_nom1 = $property->getFirstname1();
                        $target_prenom1 = $property->getLastname1();
                        $target_address1 = $property->getAdresseCredirentier1();
                        $target_postal1 = $property->getCodePostalCredirentier1();
                        $target_civilite2 = $property->getCivilite2Label();
                        $target_nom2 = $property->getFirstname2();
                        $target_prenom2 = $property->getLastname2();
                        $target_address2= $property->getAdresseCredirentier2();
                        $target_ville1 = $property->getVilleCredirentier1();
                        
                        break;
                    case 'Débirentier':
                        $target_civilite1 = $property->getCiviliteDebirentierLabel();
                        $target_nom1 = $property->getNomDebirentier();
                        $target_prenom1 = $property->getPrenomDebirentier();
                        $target_address1 = $property->getAddresseDebirentier();
                        $target_postal1 = $property->getCodePostalDebirentier();
                        $target_civilite2 = $property->civilite_debirentier2;
                        $target_nom2 = $property->getNomDebirentier2();
                        $target_prenom2 = $property->getPrenomDebirentier2();
                        $target_address2 = $property->getAddresseDebirentier2();
                        $target_ville1 = $property->getVilleDebirentier2();
                        
                        break;
                    case 'Mandant':
                        $target_civilite1 = "";
                        $target_civilite2 = "";
                        $target_nom1 = $property->getWarrant()->getFirstname();
                        $target_prenom1 = $property->getWarrant()->getLastname();
                        $target_address1 = $property->getWarrant()->getAddress();
                        $target_postal1 = $property->getWarrant()->getPostalCode();
                        $target_nom2 = "";
                        $target_prenom2 = "";
                        $target_address2 = "";
                        $target_ville1 = $property->getWarrant()->getCity();
                        
                        break;
                    
                    default:
                        $target_civilite1 = "";
                        $target_civilite2 = "";
                        break;
                }
                

                try {

                    
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = $this->replacer->replaceText($property,$former_texte,$former_destinataire);
                        
                        $former_texte = str_replace(
                            '[target_civilite1]',
                            $target_civilite1,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_nom1]',
                            $target_nom1,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_prenom1]',
                            $target_prenom1,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_civilite2]',
                            $target_civilite2,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_nom2]',
                            $target_nom2,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[target_prenom2]',
                            $target_prenom2,
                            $former_texte
                        );
                         $former_texte = str_replace(
                            '[adresse_bien]',
                            $adresse_bien,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[code_postal_bien]',
                            $code_postal_bien,
                            $former_texte
                        );
                        $former_texte = str_replace(
                            '[ville_bien]',
                            $ville_bien,
                            $former_texte
                        );


                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                      
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,

                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = $former_type_de_fichier.' créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/courrier-indexation.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
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
            'generated_files' => $generated_files,
        ]);
    }



    /**
     * @Route("/generated_files/appel-charges-trim-copro/{propertyId}", name="generate_courrier_ctcopro", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_ctcopro(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
            
     

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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

        
        $defaultText = '[target_civilite1] [target_nom1] [target_prenom1],<br>[target_civilite2] [target_nom2] [target_prenom2],<br><br><br> Dans le cadre des missions de gestion viagère qui nous ont été confiées, je vous informe que nous avons procédé au calcul de la provision trimestrielle de la quote-part locative des charges de copropriété.<br><br> Pour rappel, en ce qui concerne les charges de copropriété, il est convenu dans votre acte authentique que le(s) crédirentier(s) versent une avance trimestrielle de la quote-part locative au(x) débirentier(s).<br><br> Compte tenu du montant de cette quote part lors du dernier décompte annuel de charges de copropriété, nous provisionnerons la somme trimestrielle de [syndic_quote_part] €.<br><br> Cette somme sera prélevée à chaque début de trimestre (janvier, avril, juillet et octobre) sur le compte du crédirentier et reversée par nos soins au débirentier.<br><br> A chaque fin d’exercice, en fonction du décompte annuel de charges voté en Assemblée Générale, nous procèderons à la régularisation de la différence entre les provisions et les charges réelles. <br><br><br> Nous restons, bien entendu, à votre disposition pour tous renseignements complémentaires et vous prions de croire en l’expression de nos sincères salutations. <br><br> Le Service Gestion,';
              
        


        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_destinataire = $dataForm['destinataire'];
                $fileName = 'Courrier avance trimestrielle de charges '.$property->getId().'.pdf';
                
               

                try {

                    
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = $this->replacer->replaceOthersText($property,$former_texte,$former_destinataire);
                        
                       


                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                      
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,

                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-appel-charges-trim-copro-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-appel-charges-trim-copro-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = $former_type_de_fichier.' créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/courrier-appel-charges-trim-copro.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-appel-charges-trim-copro.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'generated_files' => $generated_files,
        ]);
    }



    /**
     * @Route("/generated_files/regul-copro/{propertyId}", name="generate_courrier_regul_copro", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return RedirectResponse|Response
     */
    public function generate_courrier_regul_copro(Request $request, DriveManager $driveManager)
    {
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
            
     

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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

        
        $defaultText = '&nbsp;&nbsp;&nbsp;&nbsp;[target_civilite1] [target_nom1] [target_prenom1],<br>&nbsp;&nbsp;&nbsp;&nbsp;[target_civilite2] [target_nom2] [target_prenom2],<br><br><br>&nbsp;&nbsp;&nbsp;&nbsp; Dans le cadre des missions de gestion viagère qui nous ont été confiées, je vous informe que nous avons procédé à la régularisation des charges de copropriété.<br><br>&nbsp;&nbsp;&nbsp;&nbsp; Vous trouverez joint à ce courrier un état des comptes indiquant :<br><br>&nbsp;&nbsp;&nbsp;&nbsp; -&nbsp;&nbsp; Le montant annuel de la quote part locative réelle (décompte de charges voté en Assemblée Générale)<br><br>&nbsp;&nbsp;&nbsp;&nbsp; -&nbsp;&nbsp; Les avances trimestrielles provisionnées <br><br>&nbsp;&nbsp;&nbsp;&nbsp; -&nbsp;&nbsp; Le montant de la régularisation (différence entre le provisionnel et le réel), ainsi que le débiteur de cette régularisation <br><br>&nbsp;&nbsp;&nbsp;&nbsp; -&nbsp;&nbsp; Et s’il y a lieu d’être, un nouveau montant d’avances trimestrielles adapté au réel <br><br>&nbsp;&nbsp;&nbsp;&nbsp; Si vous êtes débiteur de cette régularisation, vous serez prélevé dans les prochains jours. Dans le cas, où vous ne nous auriez pas confié d’autorisation de prélèvement, merci de procéder au virement de cette somme sur notre compte bancaire de gestion (coordonnées bancaires indiqués plus bas). <br><br><br>&nbsp;&nbsp;&nbsp;&nbsp; Nous restons, bien entendu, à votre disposition pour tous renseignements complémentaires et vous prions de croire en l’expression de nos sincères salutations. <br><br> Le Service Gestion,';
              
        


        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_destinataire = $dataForm['destinataire'];
                $fileName = 'Courrier régularisation des charges de copro '.$property->getId().'.pdf';
                
               
                

                try {

                    $civilite = "";

                    switch ($former_destinataire) {
                        case 'Crédirentier':
                            $civilite = $property->getCivilite1Label();
                           
                            
                            break;
                        case 'Débirentier':
                            $civilite = $property->getCiviliteDebirentierLabel();
                           
                            break;
                        case 'Mandant':
                            $civilite = "";
                          
                            
                            break;
                        
                        default:
                            $civilite = "";
                            break;
                    }

                    
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = $this->replacer->replaceOthersText($property,$former_texte,$former_destinataire);
                        
                        


                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                      
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                        'civilite'           => $civilite,
                        

                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-regul-copro-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                        'civilite'           => $civilite,
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-regul-copro-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = $former_type_de_fichier.' créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/courrier-regul-copro.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-regul-copro.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'generated_files' => $generated_files,
        ]);
    }



    /**
     * @Route("/generated_files/courrier-abandon-duh/{propertyId}", name="generate_courrier_abandon_duh", requirements={"propertyId"="\d+"})
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
            
     

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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

        
        $defaultText = '[target_civilite1] [target_nom1] [target_prenom1],<br>[target_civilite2] [target_nom2] [target_prenom2],<br><br><br> Nous vous informons qu’en application de ce qui a été convenu dans l’acte de vente en viager passé chez Maître [nom_notaire] [prenom_notaire] en date du [date_acte], suite à l’abandon du droit d’usage et d’habitation sur le bien, le montant de la rente viagère sera majoré de [pourcentage_revalorisation_rente] % à compter de la date de remise des clefs. <br><br> Adresse du bien : [adresse_bien] [code_postal_bien] [ville_bien]<br><br> Date de remise des clefs : [date_remise_clefs]<br><br> Le nouveau montant de rente applicable sera donc porté à [nv_montant] €. <br><br> Nous restons, bien entendu, à votre disposition pour tous renseignements complémentaires et vous prions de croire en l’expression de nos sincères salutations. <br><br> Le Service Gestion';
              
        


        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_destinataire = $dataForm['destinataire'];
                $fileName = 'Courrider abdandon duh '.$property->getId().'.pdf';
                
                
                

                try {

                    
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = $this->replacer->replaceText($property,$former_texte,$former_destinataire);

                        $former_texte = $this->replacer->replaceOthersText($property,$former_texte,$former_destinataire);
                        
                        




                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                      
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,

                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-abandon-duh-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-abandon-duh-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = $former_type_de_fichier.' créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/courrier-abandon-duh.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
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
            'generated_files' => $generated_files,
        ]);
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
        /** @var Property $property */
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
            
     

        $manager = $this->getDoctrine()->getManager();

        $qb = $manager->createQueryBuilder()
        ->select("f")
        ->from('App\Entity\File', 'f')
        ->where('f.property = :key_property')
        ->andWhere('f.type = :key_type')
        ->setParameter('key_property', $property)
        ->setParameter('key_type', 1)
            ->orderBy('f.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $generated_files = $query->getResult();

        

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

        
        $defaultText = '[target_civilite1] [target_nom1] [target_prenom1],<br>[target_civilite2] [target_nom2] [target_prenom2],<br><br><br>  <br><br> Nous restons, bien entendu, à votre disposition pour tous renseignements complémentaires et vous prions de croire en l’expression de nos sincères salutations. <br><br> Le Service Gestion,';
              
        


        $defaultData = ['message' => 'Type your message here'];
        $form = $this->createFormBuilder($defaultData)
        ->add('destinataire', ChoiceType::class, [
            'choices'  => [
                'Crédirentier' => "Crédirentier",
                'Débirentier' => "Débirentier",
                'Mandant' => "Mandant",
            ],
        ])
        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
        ->add('texte', TextareaType::class, [
            'data' => $defaultText,
            
          
        ])
        ->getForm();

            $d = new DateTime('First day of next month');

            $current_date = [
                'current_day'   => utf8_encode(strftime('%A %e %B %Y')),
                'current_month' => date('m'),
                'max_days'      => cal_days_in_month(CAL_GREGORIAN, $d->format('m'), $d->format('Y')),
                'month'         => utf8_encode(strftime('%B', $d->getTimestamp())),
                'month_n'       => $d->format('m'),
                'year'          => $d->format('Y'),
            ];
                        
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                
                // data is an array with "name", "email", and "message" keys
                $dataForm = $form->getData();
                $former_texte = $dataForm['texte'];
                $status = $dataForm['status'];
                $former_texte=$content = preg_replace('#<p data-f-id="pbf".*?</p>#is', '', $former_texte);
                $dataForm['texte']=$former_texte;
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $now_date=new DateTime();
                $former_status = $dataForm['status'];
                $former_destinataire = $dataForm['destinataire'];
                $fileName = 'Courrier libre '.$property->getId().'.pdf';
                
                
                

                try {

                    
                    if(in_array($former_status, [1, 3], true)){

                    //'Rente' => 1,'Honoraire' => 2,'Copro' => 3,'Manuelle rente' => 4,'Manuelle honoraire' => 5

                        $former_texte = $this->replacer->replaceText($property,$former_texte,$former_destinataire);
                        $former_texte = $this->replacer->replaceOthersText($property,$former_texte,$former_destinataire);
                        
                      


                        $message = 'Aperçu créé avec succès';


                        $form = $this->createFormBuilder($defaultData)
                        ->add('destinataire', ChoiceType::class, ['data' =>  $former_destinataire, 
                            'choices'  => [
                                'Crédirentier' => "Crédirentier",
                                'Débirentier' => "Débirentier",
                                'Mandant' => "Mandant",
                            ],
                        ])
                      
                        ->add('status', ChoiceType::class, ['data' => 1,'choices' => ['prévisualiser le texte' => 1,'créer un aperçu' => 2,'envoyer le document' => 3   ],'expanded' => true,'multiple' => false, 'choice_translation_domain' => false])
                        ->add('texte', TextareaType::class, [
                            'data' => $former_texte,
                        
                        ])
                        ->getForm();

                    }
                    else if($status==2){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                       
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,

                    ];

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-libre-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/aperçu.pdf', 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName("aperçu.pdf");
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId("frf");
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);
                        $manager->flush();
                        
                        $message = 'Document sans facture créé.';
                         $qb = $manager->createQueryBuilder()
                        ->select("f")
                        ->from('App\Entity\File', 'f')
                        ->where('f.property = :key_property')
                        ->andWhere('f.type = :key_type')
                        ->setParameter('key_property', $property)
                        ->setParameter('key_type', 1)
                            ->orderBy('f.id', 'DESC');
                        $query = $qb->getQuery();
                        // Execute Query
                        $generated_files = $query->getResult();
                        
                    }

                    if($status==3){
                        $pdf->pdf->SetDisplayMode('fullpage');
                        
                        
                        $dataCourrier = [
                        'date'       => $now_date,
                        'current_day'=> utf8_encode(strftime('%A %e %B %Y')),
                        'form'       => $dataForm,
                        'day'  => $now_date->format('d'),
                        'month'     => $now_date->format('m'),
                        'year' => $now_date->format('Y'),
                        'property'           => $property,
                    ];
                       

                        
                        $pdf->writeHTML($this->twig->render('generated_files/courrier-libre-template.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $dataCourrier]));
                        $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                        
                        $file = new File();
                        $file->setType(File::TYPE_DOCUMENT);
                        $file->setName($fileName);
                        $file->setWarrant($property->getWarrant());
                        $file->setProperty($property);
                        $file->setDriveId($driveManager->addFile($file->getName(), $this->path.'/'.$fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                        $manager = $this->getDoctrine()->getManager();
                        $manager->persist($file);

                        
                       
                       
                        
                        $message = $former_type_de_fichier.' créé avec succès';
                        

                        $manager->persist($file);
                        $manager->flush();
                        
                    }
                    
                    

                    return $this->render('generated_files/courrier-libre.html.twig', [
                        'property' => $property,
                        'form' => $form->createView(),
                        'message' => $message,
                        'generated_files' => $generated_files,
                    ]);

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
            }

        return $this->render('generated_files/courrier-libre.html.twig', [
            'property' => $property,
            'form' => $form->createView(),
            'message' => null,
            'generated_files' => $generated_files,
        ]);
    }


   
}
