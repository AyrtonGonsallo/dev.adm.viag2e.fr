<?php

namespace App\Controller;
use App\Entity\File;
use App\Entity\Mail;
use App\Entity\Notification;
use App\Entity\Parameter;
use App\Entity\PendingInvoice;
use App\Entity\Invoice;
use App\Entity\PropertyComment;
use App\Entity\Property;
use App\Entity\RevaluationHistory;
use App\Entity\Honoraire;
use App\Entity\Warrant;
use App\Form\PropertyFormType;
use App\Form\PropertyCoproFormType;
use App\Form\PropertyPaymentFormType;
use App\Form\PropertyCommentFormType;
use App\Form\FileFormType;
use App\Form\MailFormType;
use App\Service\DriveManager;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Html2Pdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Container\ContainerInterface;

setlocale(LC_TIME, 'fr_FR.UTF8', 'fr.UTF8', 'fr_FR.UTF-8', 'fr.UTF-8');

class PropertyController extends AbstractController
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
     * @Route("/property/activate/{propertyId}", name="property_activate", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws Exception
     */
    public function activate(Request $request)
    {
        $manager = $this->getDoctrine()->getManager();

        $property = $manager
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));

        if (empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        if ($property->isActive()) {
            $property->setActive(false);

            $param = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_properties']);
            $param->setValue($param->getValue() - 1);

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

            $this->addFlash('success', 'Bien désactivé');
        } else {
            $property->setActive(true);
            $property->setLastInvoice(new DateTime());

            $param = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_properties']);
            $param->setValue($param->getValue() + 1);

            $this->addFlash('success', 'Bien activé');
        }
        $manager->flush();

        return $this->redirectToRoute('property_view', ['propertyId' => $request->get('propertyId')]);
    }

    /**
     * @Route("/property/create/{warrantId}", name="property_create", requirements={"warrantId"="\d+"})
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request)
    {
        $warrant = $this->getDoctrine()
            ->getRepository(Warrant::class)
            ->find($request->get('warrantId'));

        if (empty($warrant)) {
            $this->addFlash('danger', 'Mandat introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $property = new Property();
        $property->setType($warrant->getType());
        $form = $this->createForm(PropertyFormType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Property $property */
            $property = $form->getData();

            if (empty($property->getRevaluationIndex())) {
                $property->setRevaluationIndex(0.0);
            }

            $property->setWarrant($warrant);
            $property->setCreationUser($this->getUser());
            $property->setEditionUser($this->getUser());

            $manager = $this->getDoctrine()->getManager();
            $manager->persist($property);

            $param = $manager->getRepository(Parameter::class)->findOneBy(['name' => 'count_properties']);
            $param->setValue($param->getValue() + 1);

            $manager->flush();

            $this->addFlash('success', 'Bien créé');
            return $this->redirectToRoute('warrant_view', ['type' => Warrant::getTypeName($warrant->getType()), 'warrantId' => $warrant->getId()]);
        }

        return $this->render('property/create.html.twig', [
            'form'    => $form->createView(),
            'warrant' => $warrant
        ]);
    }

    /**
     * @Route("/property/view/{propertyId}", name="property_view", requirements={"propertyId"="\d+"})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     * @throws Exception
     */
    public function view(Request $request, DriveManager $driveManager)
    {
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));

        if (empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }
        $propertyComment=new PropertyComment(); 
        $form_comment = $this->createForm(PropertyCommentFormType::class, $propertyComment);
        $form_comment->handleRequest($request);
        if ($form_comment->isSubmitted() and $form_comment->isValid()) {
            $manager = $this->getDoctrine()->getManager();
            $data = $form_comment->getData();
            $propertyComment->setProperty($property);
            $propertyComment->setStatus(0);
            $manager->persist($propertyComment);
            $manager->flush();

            return $this->redirectToRoute('property_view',['propertyId' => $property->getId(),'onglet' => 'm_tabs_mails']);
        }
        $comments = $this->getDoctrine()
            ->getRepository(PropertyComment::class)
            ->findByProperty($property);
            $form_file = $this->createForm(FileFormType::class, new File(), ['action' => $this->generateUrl('property_file_add', ['propertyId' => $property->getId()])]);
            //$form_mail = $this->createForm(MailFormType::class, new Mail(), ['action' => $this->generateUrl('property_mail_add', ['propertyId' => $property->getId()])]);
    
            
        if (empty($property)) {
            $this->addFlash('danger', 'Bien introuvable');
            return $this->redirectToRoute('dashboard', [], 302);
        }

        $old_index = $property->getRevaluationIndex();
        $form_payment= $this->createForm(PropertyPaymentFormType::class, $property);
        $form_copro = $this->createForm(PropertyCoproFormType::class, $property);
        $form = $this->createForm(PropertyFormType::class, $property);
        $form_mail = $this->createForm(MailFormType::class, new Mail(), ['action' => $this->generateUrl('mail_add3', ['p_id' => $property->getId()])]);
        $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("inv")
        ->from('App\Entity\Invoice', 'inv')
        ->where('inv.property = :property AND inv.category = 1')
        ->setParameter('property', $property)
            ->orderBy('inv.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $invs = $query->getResult();
        $form->handleRequest($request);
        $form_copro->handleRequest($request);
        $form_payment->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            if($property->getPropertyType()=="Appartement" && $property->date_fin_exercice_copro==null){
                $errors = ' la date de fin d\'exercice de copropriété est obligatoire pour un bien de type appartement.';
                $this->addFlash('error', 'Problème lors de l\'enregistrement :'.$errors);

            }else{
                $manager = $this->getDoctrine()->getManager();
                $property->setEditionUser($this->getUser());
    
                if ($old_index !== $property->getRevaluationIndex()) {
                    $notifications = $manager
                        ->getRepository(Notification::class)
                        ->findProperty('revaluation', $property);
    
                    foreach ($notifications as $notification) {
                        $manager->remove($notification);
                    }
    
                    $history = new RevaluationHistory();
                    $history->setValue($old_index);
                    $history->setProperty($property);
    
                    $property->setLastRevaluation(new DateTime());
    
                    $manager->persist($history);
                }
                
                
                $manager->flush();
                $this->addFlash('success', 'Bien édité');
            }
            
        }else if($form->isSubmitted() && !$form->isValid()){
            $errors ='';
            foreach ($form as $fieldName => $formField) {
                // each field has an array of errors
                if(strlen($formField->getErrors())>=3){
                    $errors .= 'Champ: '.$fieldName.'- Erreur: '.$formField->getErrors().'<br/>';
                }
            }
            $this->addFlash('error', 'Problème lors de l\'enregistrement <br/>'.$errors);
        }
        if ($form_copro->isSubmitted() and $form_copro->isValid()) {
            if($property->getPropertyType()=="Appartement" && $property->date_fin_exercice_copro==null){
                $errors = ' la date de fin d\'exercice de copropriété est obligatoire pour un bien de type appartement.';
                $this->addFlash('error', 'Problème lors de l\'enregistrement : '.$errors);

            }else{
                $manager = $this->getDoctrine()->getManager();
                $data = $form_copro->getData();
               // $pendingInvoice = new PendingInvoice();
                //$pendingInvoice->setProperty($property);
                setlocale(LC_TIME, 'fr_FR.utf8', 'French', 'French_France'); // dates in french
                $regul = $data->regul;
                $date_reg_fin = $data->date_reg_fin;
                $date_reg_debut = $data->date_reg_debut;

                $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
                ->select("inv")
                ->from('App\Entity\Invoice', 'inv')
                ->where('inv.property = :property AND inv.category = 1 AND inv.date BETWEEN :start AND :end')
                ->setParameter('property', $property)
                ->setParameter('start', $date_reg_debut)
                ->setParameter('end', $date_reg_fin)
                    ->orderBy('inv.id', 'DESC');
                $query = $qb->getQuery();
                // Execute Query
                $invs = $query->getResult();

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
                $now_date=new DateTime();
                $data = [
                    'date'       => $now_date,
                    'current_day'       => utf8_encode(strftime("%A %d %B %Y", strtotime( $now_date->format('d-m-Y') ))),
                    'annee'       => $now_date->format('Y'),
                    'date_a_f'       => $now_date->format('d/m/Y'),
                    'date_d_f'       => $date_reg_debut->format('d/m/Y'),
                    'date_f_f'       => $date_reg_fin->format('d/m/Y'),
                    'amount'           => abs($regul),
                    'period'           => ' '.strftime("%B %Y", strtotime( $date_reg_debut->format('d-m-Y') )).' à '.strftime("%B %Y", strtotime( $date_reg_fin->format('d-m-Y') )),
                    'property'   => [
                        'id'         => $property->getId(),
                        'firstname'  => $property->getFirstname1(),
                        'lastname'   => $property->getLastname1(),
                        'firstname2' => $property->getFirstname2(),
                        'lastname2'  => $property->getLastname2(),
                        'address'    => $property->getAddress(),
                        'postalcode' => $property->getPostalCode(),
                        'city'       => $property->getCity(),
                        'title'       => $property->getTitle(),
                        'buyerfirstname'  => $property->getBuyerFirstname(),
                        'buyerlastname'   => $property->getBuyerLastname(),
                        'buyeraddress'    => $property->getBuyerAddress(),
                        'buyerpostalcode' => $property->getBuyerPostalCode(),
                        'buyercity'       => $property->getBuyerCity(),
                        'property_type'       => $property->getPropertyType(),
                        'condominiumFees' => $property->getCondominiumFees(),
                        'achat' => $property->getDosAuthenticInstrument(),
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
                    "debirentier" => null,
                    "debirentier_different" => null,
                    "target" => null,
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    "factures" => null,
                    "debit" => null,
                    "credit" => null,
    
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
                $data["factures"]=$factures;
                $data["credit"]=$credit;
                $data["debit"]=$credit - $regul;
                if($regul>0){
                    //si résultat positif= régul adréssée au débirentier
                    //Le débirentier = le propriétaire = la société Opale Business 2
                    //$pendingInvoice->setTarget(PendingInvoice::TARGET_WARRANT);//1
                    $data["target"]="Débirentier";
                }else{
                    //si résultat négatif= régul adressée au crédirentier
                    //Le crédirentier
                    //$pendingInvoice->setTarget(PendingInvoice::TARGET_PROPERTY);//2
                    $data["target"]="Crédirentier";
                }
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
                $fileName = "/".$now_date->format('d-m-Y h:i:s')." Régul ".$property->getId()." ".explode("/", $property->getTitle())[0].".pdf";
                try {
                    $pdf->pdf->SetDisplayMode('fullpage');
                   
                    if($regul>0){
                        //si résultat positif= régul adréssée au débirentier
                        //Le débirentier = le propriétaire = la société Opale Business 2
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_WARRANT);//1
                        $pdf->writeHTML($this->twig->render('regul_annuelle/debit.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }else{
                        //si résultat négatif= régul adressée au crédirentier
                        //Le crédirentier
                        //$pendingInvoice->setTarget(PendingInvoice::TARGET_PROPERTY);//2
                        $pdf->writeHTML($this->twig->render('regul_annuelle/credit.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    }
                    $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf'. $fileName, 'F');
                    
                    $file = new File();
                    $file->setType(File::TYPE_DOCUMENT);
                    $file->setName($fileName);
                    $file->setDate($now_date);
                    $file->setWarrant($property->getWarrant());
                    $file->setDriveId($driveManager->addFile($file->getName(), $this->path . $fileName, File::TYPE_DOCUMENT, $property->getWarrant()->getId()));
                    $manager = $this->getDoctrine()->getManager();
                    $manager->persist($file);
                    $property->regul = Null;
                    $property->date_reg_debut = Null;
                    $property->date_reg_fin = Null;
                    $manager->flush();

                    $this->addFlash('success', 'Facture enregistrée');

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
                /*$pendingInvoice->setCategory(Invoice::CATEGORY_MANUAL);
                $pendingInvoice->setOptions([]);
                $pendingInvoice->setLabel('Facture période du '.strftime("%B %Y", strtotime( $date_reg_debut->format('d-m-Y') )).' à '.strftime("%B %Y", strtotime( $date_reg_fin->format('d-m-Y') )));
                $pendingInvoice->setPeriod(' '.strftime("%B %Y", strtotime( $date_reg_debut->format('d-m-Y') )).' à '.strftime("%B %Y", strtotime( $date_reg_fin->format('d-m-Y') )));
                $pendingInvoice->setMontantht(abs($regul));
                $pendingInvoice->setHonorary(0);
                
                $manager->persist($pendingInvoice);*/
                
            }
            return $this->redirectToRoute('property_view',['propertyId' => $property->getId(),'onglet' => 'm_tabs_copro']);
        }else if($form_copro->isSubmitted() and !$form_copro->isValid()){
            $errors ='';
            foreach ($form_copro as $fieldName => $formField) {
                // each field has an array of errors
                if(strlen($formField->getErrors())>=3){
                    $errors .= 'Champ: '.$fieldName.'- Erreur: '.$formField->getErrors().'<br/>';
                }
            }
            $this->addFlash('error', 'Problème lors de l\'enregistrement <br/>'.$errors);
            return $this->redirectToRoute('property_view',['propertyId' => $property->getId(),'onglet' => 'm_tabs_copro']);

        }
        
        $startDate = \DateTime::createFromFormat('d-n-Y', "01-".date('m')."-".date('Y'));
        $startDate->setTime(0, 0 ,0);

        $endDate = \DateTime::createFromFormat('d-n-Y', "01-".(date('m')+1)."-".date('Y'));
        $endDate->setTime(0, 0, 0);
        //recuperer 
        $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("rh")
        ->from('App\Entity\RevaluationHistory', 'rh')
        ->where('rh.type LIKE :key and rh.date BETWEEN :start AND :end')
        ->setParameter('key', 'OGI')
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
            ->orderBy('rh.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        if($query->getResult()){
            $indice_og2i = $query->getResult()[0];
        }else{
            $indice_og2i = (object) array('value' => 0);
        }
        // recuperer Valeur Indice de référence* (indexation)
        function get_label($i){
            if($i==1){
                return 'Urbains';
            }else if($i==2){
                return 'Ménages';
            }else{
                return 'Ménages';
            }

        }
        $qb4=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("rh")
        ->from('App\Entity\RevaluationHistory', 'rh')
        ->where('rh.type LIKE :key')
        ->andWhere('rh.date BETWEEN :start AND :end')
        ->setParameter('key', get_label($property->getIntitulesIndicesInitial()))
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
            ->orderBy('rh.id', 'DESC');
        $query4 = $qb4->getQuery();
        // Execute Query
        if($query4->getResult()){
            $indice_m_u = $query4->getResult()[0]; 
        }else{
            $indice_m_u = (object) array('value' => 0);; 
        }
        if ($form_payment->isSubmitted() and $form_payment->isValid()) {
            $manager = $this->getDoctrine()->getManager();
            $data = $form_payment->getData();
            $rum_unique=$data->getBankRum();
            $qb5=$this->getDoctrine()->getManager()->createQueryBuilder()
            ->select("p")
            ->from('App\Entity\Property', 'p')
            ->where('p.bank_rum LIKE :key')
            ->setParameter('key', '%'.$rum_unique.'%');
            $query5 = $qb5->getQuery();
            // Execute Query
            $properties_avec_meme_rum = $query5->getResult();
            if($rum_unique && $properties_avec_meme_rum){
                $this->addFlash('error', 'Problème lors de l\'enregistrement : le numéro de RUM existe déja.');
                return $this->redirectToRoute('property_view',['propertyId' => $property->getId(),'onglet' => 'm_tabs_pay']);

            }
            $property->valeur_indexation_normale=$indice_m_u->getValue();
            //$manager->persist($propertyComment);
            $manager->flush();
            $this->addFlash('success', 'Bien édité');
            return $this->redirectToRoute('property_view',['propertyId' => $property->getId(),'onglet' => 'm_tabs_pay']);
        }else if($form_payment->isSubmitted() and !$form_payment->isValid()){
            $errors ='';
            foreach ($form_payment as $fieldName => $formField) {
                // each field has an array of errors
                if(strlen($formField->getErrors())>=3){
                    $errors .= 'Champ: '.$fieldName.'- Erreur: '.$formField->getErrors().'<br/>';
                }
            }
            $this->addFlash('error', 'Problème lors de l\'enregistrement <br/>'.$errors);
            return $this->redirectToRoute('property_view',['propertyId' => $property->getId(),'onglet' => 'm_tabs_pay']);

        }
        $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("rec")
        ->from('App\Entity\Recap', 'rec')
        ->where('rec.property = :key')
        ->setParameter('key', $property)
            ->orderBy('rec.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $recaps = $query->getResult();
        $qb2=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("inv")
        ->from('App\Entity\Invoice', 'inv')
        ->where('inv.property = :key')
        ->setParameter('key', $property)
            ->orderBy('inv.id', 'DESC');
        $query2 = $qb2->getQuery();
        // Execute Query
        $invoices_files = $query2->getResult();
        $tva = $this->getDoctrine()->getRepository(Parameter::class)->findOneBy(['name' => 'tva']);
        $messages = $this->getDoctrine()
        ->getRepository(Mail::class)
        ->findAll();
        return $this->render('property/view.html.twig', [
            'form'      => $form->createView(),
            'form_comment'      => $form_comment->createView(),
            'form_file'  => $form_file->createView(),
            'form_copro'  => $form_copro->createView(),
            'messages'  => $messages,
            'form_payment'  => $form_payment->createView(),
            'form_mail'  => $form_mail->createView(),
            'property'  => $property,
            //'form_mail'  => $form_mail,
            'indice_og2i'  => $indice_og2i,
            'indice_m_u'  => $indice_m_u,
            'recaps'  => $recaps,
            'invoices_files'  => $invoices_files,
            'invoices'  => $invs,
            'comments'  => $comments,
            'tax'       => $tva->getValue(),
            'buyer_tab' => $request->get('buyer') !== null,
        ]);
    }

    /**
     *@Route(name="get_ipcs",path="/getipcs/{type}/{month}/{year}",defaults={"month"=10,"year"=2023})
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function get_ipc_index(Request $request)
    {
        $type=$request->get('type');
        $month=$request->get('month');
        $year=$request->get('year');
        $startDate = \DateTime::createFromFormat('d-n-Y', "01-".$month."-".$year);
        $startDate->setTime(0, 0 ,0);

        $endDate = \DateTime::createFromFormat('d-n-Y', "01-".($month+1)."-".$year);
        $endDate->setTime(0, 0, 0);
        $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("rh")
        ->from('App\Entity\RevaluationHistory', 'rh')
        ->where('rh.type LIKE :key and rh.date BETWEEN :start AND :end')
        ->setParameter('key', $type)
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
            ->orderBy('rh.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $rhs = $query->getResult();
        $options="<option value=''></option>";
        $i=0;
        foreach ($rhs as  $rh) {
            $options.='<option selected value="'.$rh->getId().'">'.$rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp()).'</option>';
            $i++;
            if($i==1){continue;}
        }
        return new JsonResponse(['data' => $rhs,'options'=> $options]);
    }
    /**
     *@Route(name="get_currents_ipcs",path="/get_currents_ipcs/{type}")
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function get_currents_ipc_index(Request $request)
    {
        $type=$request->get('type');
        $startDate = \DateTime::createFromFormat('d-n-Y', "01-".date('m')."-".date('Y'));
        $startDate->setTime(0, 0 ,0);

        $endDate = \DateTime::createFromFormat('d-n-Y', "01-".(date('m')+1)."-".date('Y'));
        $endDate->setTime(0, 0, 0);
        $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("rh")
        ->from('App\Entity\RevaluationHistory', 'rh')
        ->where('rh.type LIKE :key and rh.date BETWEEN :start AND :end')
        ->setParameter('key', $type)
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
            ->orderBy('rh.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $rhs = $query->getResult();
        $options=$rhs[0];
        
        return new JsonResponse(['data' => $rhs,'options'=> $options]);
    }
    public function getTableAmount(Invoice $invoice)
    {
        if ($invoice->getCategory() === Invoice::CATEGORY_CONDOMINIUM_FEES) {
            return number_format($invoice->getData()['property']['condominiumFees'], 2, '.', ' ');
        }
        elseif ($invoice->getCategory() === Invoice::CATEGORY_GARBAGE || $invoice->getCategory() === Invoice::CATEGORY_MANUAL) {
            return number_format($invoice->getData()['amount'],2, '.', ' ');
        }
        else {
            return number_format($invoice->getData()['property']['annuity'],2, '.', ' ');
        }
    }
    /**
     *@Route(name="get_invoices",path="/get_invoices/{propertyId}/{s_day}/{s_month}/{s_year}/{e_day}/{e_month}/{e_year}",defaults={"s_day"=01,"s_month"=10,"s_year"=2023,"e_day"=01,"e_month"=10,"e_year"=2023})
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function get_invoices(Request $request)
    {
        //$type=$request->get('type');
        $property = $this->getDoctrine()
            ->getRepository(Property::class)
            ->find($request->get('propertyId'));
        $s_day=$request->get('s_day');
        $s_month=$request->get('s_month');
        $s_year=$request->get('s_year');
        $e_day=$request->get('e_day');
        $e_month=$request->get('e_month');
        $e_year=$request->get('e_year');
        $startDate = \DateTime::createFromFormat('d-n-Y', $s_day."-".$s_month."-".$s_year);
        $startDate->setTime(0, 0 ,0);

        $endDate = \DateTime::createFromFormat('d-n-Y', $e_day."-".$e_month."-".$e_year);
        $endDate->setTime(0, 0, 0);
        $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("inv")
        ->from('App\Entity\Invoice', 'inv')
        ->where('inv.property = :property AND inv.category = 1 AND inv.date BETWEEN :start AND :end')
        ->setParameter('property', $property)
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
            ->orderBy('inv.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $invs = $query->getResult();
        $credit=0;
        $debit=0;
        $regul=0;
        foreach ($invs as  $inv) {
            //$credit+=20;
            $credit+=$this->getTableAmount($inv);
            
        }
        
        return new JsonResponse(["credit"=> $credit]);
    }

    /**
     *@Route(name="get_ipc_og2is",path="/getipcs_og2i/{month}/{year}",defaults={"month"=10,"year"=2023})
     *
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function get_ipc_og2i_index(Request $request)
    {
        //$type=$request->get('type');
        $month=$request->get('month');
        $year=$request->get('year');
        $startDate = \DateTime::createFromFormat('d-n-Y', "01-".$month."-".$year);
        $startDate->setTime(0, 0 ,0);

        $endDate = \DateTime::createFromFormat('d-n-Y', "01-".($month+1)."-".$year);
        $endDate->setTime(0, 0, 0);
        $qb=$this->getDoctrine()->getManager()->createQueryBuilder()
        ->select("rh")
        ->from('App\Entity\RevaluationHistory', 'rh')
        ->where('rh.type LIKE :key and rh.date BETWEEN :start AND :end')
        ->setParameter('key', 'OGI')
        ->setParameter('start', $startDate)
        ->setParameter('end', $endDate)
            ->orderBy('rh.id', 'DESC');
        $query = $qb->getQuery();
        // Execute Query
        $rhs = $query->getResult();
        $options="<option value=''></option>";
        $i=0;
        foreach ($rhs as  $rh) {
            $options.='<option selected value="'.$rh->getId().'">'.$rh->getValue().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp()).'</option>';
            $i++;
            if($i==1){continue;}
        }
        return new JsonResponse(['data' => $rhs,'options'=> $options]);
    }

/**
     * @Route("/update_invoice/{id}/{j}/{m}/{a}", name="update_invoice",defaults={"a"=2023,"j"=10,"m"=2})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function update_invoice(Request $request)
    {
        $id=$request->get('id');
        $j=$request->get('j');
        $m=$request->get('m');
        $a=$request->get('a');
        $invoice = $this->getDoctrine()
        ->getRepository(Invoice::class)
        ->find($id);
        $payDate = \DateTime::createFromFormat('d-n-Y', $j."-".$m."-".$a);
        $payDate->setTime(0, 0 ,0);
        $invoice->date_de_paiement=$payDate;
        $manager = $this->getDoctrine()->getManager();
        $manager->persist($invoice);
        $manager->flush();
        return new JsonResponse(['id' => $id,'message'=> "date de paiement de l'invoice ".$id." mise à jour"]);
        
    }

/**
     * @Route("/get_montant_rente/{mi}/{vii}/{viog2ir}", name="get_montant_rente",defaults={"mi"=100,"viog2ir"=202.3,"vii"=10})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function get_montant_rente(Request $request)
    {
         //viog2ir*100/vii
         $mi=$request->get('mi');
         $vii_id=$request->get('vii');
         $viog2ir_id=$request->get('viog2ir');
         $vii_object = $this->getDoctrine()
         ->getRepository(RevaluationHistory::class)
         ->find($vii_id);
         $viog2ir_object = $this->getDoctrine()
         ->getRepository(RevaluationHistory::class)
         ->find($viog2ir_id);
         $res=($vii_object->getValue()*$mi)/$viog2ir_object->getValue();
         return new JsonResponse(['formule'=> 'viog2ir*mi/vii','res' => $res,'mi' => $mi,'viog2ir'=>$vii_object->getValue(),'vii'=>$viog2ir_object->getValue() ]);
         
        
    }

    
/**
     * @Route("/get_nv_montant_rente/{viog2ir}/{vir}/{rdb}/{p}", name="get_nv_montant_rente",defaults={"vir"=2023,"p"=2.5,"rdb"=2023,"viog2ir"=10})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function get_nv_montant_rente(Request $request)
    {
        //0,975*(rdb*vir/viog2ir)
        $rdb=$request->get('rdb');
        $plaf=$request->get('p');
        $vir=$request->get('vir');
        $viog2ir_id=$request->get('viog2ir');
        
        $viog2ir_object = $this->getDoctrine()
        ->getRepository(RevaluationHistory::class)
        ->find($viog2ir_id);
        $res=($vir*$rdb)/$viog2ir_object->getValue();
        $plaf_v=(1+($plaf/100))*$rdb;
        if($res<$plaf_v){
            $res=$res;
            $formule='(rdb*vir/viog2ir)';
        }else{
            $res=$plaf_v;
            $formule="(1+(plaf/100))*res";
        }
        return new JsonResponse(['formule'=>$formule ,'res' => $res,'plaf' => $plaf,'plaf_val' => $plaf_v,'viog2ir'=>$viog2ir_object->getValue(),'vir'=>$vir,'rdb'=>$rdb ]);
        
    }

    
/**
     * @Route("/get_honoraire/{taux}/{rdb}", name="get_honoraire",defaults={"rdb"=2023,"taux"=10})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function get_honoraire(Request $request)
    {
        $rdb=$request->get('rdb');
        //rdb*taux
        $taux_id=$request->get('taux');
        
        $taux_hon = $this->getDoctrine()
        ->getRepository(Honoraire::class)
        ->find($taux_id);
        $res=$taux_hon->getValeur()*$rdb/100;
        return new JsonResponse(['formule'=> 'rdb*taux','res' => $res,'taux'=>$taux_hon->getValeur(),'rdb'=>$rdb ]);
        
    }

    /**
     * @Route("/get_rente_without_og2i/{ri}/{ii}/{ia}", name="get_rente_without_og2i",defaults={"ri"=112.89,"ii"=103.71,"ia"=750})
     *
     * @param Request $request
     * @param DriveManager $driveManager
     * @return Response
     */
    public function get_rente_without_og2i(Request $request)
    {
        $ri=$request->get('ri');
        $ii_id=$request->get('ii');
        $ia=$request->get('ia');
        //(ri/ii)*ia
        $taux_id=$request->get('taux');
        
        $ii = $this->getDoctrine()
        ->getRepository(RevaluationHistory::class)
        ->find($ii_id);
        $res=($ri/$ii->getValue())*$ia;
        return new JsonResponse(['formule'=> '(ri/ii)*ia','res' => $res,'ri'=>$ri,'ii'=>$ii->getValue(),'ia'=>$ia ]);
        
    }
}
