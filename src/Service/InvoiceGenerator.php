<?php
namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Property;
use Exception;
use DateTime;
use Psr\Container\ContainerInterface;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Html2Pdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class InvoiceGenerator
{
    private $path;
    private $twig;
    private $pdf_logo;

    public function __construct(ContainerInterface $container, ParameterBagInterface $params)
    {
        $this->path     = $params->get('pdf_tmp_dir');
		setlocale(LC_TIME, 'fr_FR.utf8', 'French', 'French_France');
        $this->pdf_logo = $params->get('pdf_logo_path');
        $this->twig     = $container->get('twig');
        $this->manager = $container->get('doctrine')->getManager();
    }
 public function get_label($i){
                if($i==1){
                    return 'Urbains';
                }else if($i==2){
                    return 'Ménages';
                }else{
                    return 'Ménages';
                }
        
            }

     public  function convert_from_latin1_to_utf8_recursively2($dat)
    {
      
       // $dat = json_decode($dat, true);
       if (is_string($dat)) {
        $dat = str_replace('Ã¨', 'è', $dat);
        return $dat;
     } elseif (is_array($dat)) {
            $ret = [];
            foreach ($dat as $i => $d) $ret[ $i ] = self::convert_from_latin1_to_utf8_recursively2($d);
            return $ret;
         } elseif (is_object($dat)) {
            foreach ($dat as $i => $d) $dat->$i = self::convert_from_latin1_to_utf8_recursively2($d);
            return $dat;
         } else {
            return $dat;
         }
         
         
       
    }

    /**
     * @param array $data
     * @param array $parameters
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function generateFile(array $data, array $parameters)
    {
        $pdf      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/invoice_{$data['number']}-file1.pdf";
        try {
            if($data['recursion']!=Invoice::RECURSION_OTP) {//pour regler le rpobleme des acents dans les factures manuelles
                //$data=$this->convert_from_latin1_to_utf8_recursively2($data);
            }
			if($data['type']==Invoice::TYPE_RECEIPT) {//pour regler le rpobleme des acents dans les factures manuelles
                $data=$this->convert_from_latin1_to_utf8_recursively2($data);
            }
            $pdf->pdf->SetDisplayMode('fullpage');
            if(empty($data['recursion']))
                $data['recursion'] = Invoice::RECURSION_MONTHLY;
                //$data['reason'] = utf8_decode($data['reason']);
                //$data['label'] = utf8_decode($data['label']);
                //$data['property']['address'] = utf8_decode($data['property']['address']);
            switch ($data['recursion']) {
                case Invoice::RECURSION_OTP:
					if($data['amount']==-1){
						return -1;
					}
                    $fileName = "/invoice_{$data['number']}R-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/invoice_otp_1.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                case Invoice::RECURSION_QUARTERLY:
                    $fileName = "/invoice_quarterly{$data['number']}-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/invoice_quarterly.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                case Invoice::RECURSION_MONTHLY:
                    if($data['property']['annuity']<=0){
						return -1;
					}
                    $fileName = "/invoice_{$data['number']}R-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/invoice.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                default:
                    $fileName = "/invoice_{$data['number']}R-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/invoice.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;
            }

            $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf'. $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf->clean();
            throw new Exception($e->getMessage());
        }
    }
    public function generateFile2(array $data, array $parameters)
    {
        //$data=$this->convert_from_latin1_to_utf8_recursively2($data);

        $pdf2      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/invoice_{$data['number']}-file2.pdf";
        try {
            $pdf2->pdf->SetDisplayMode('fullpage');
            if(empty($data['recursion']))
                $data['recursion'] = Invoice::RECURSION_MONTHLY;
                //$data['reason'] = utf8_decode($data['reason']);
                //$data['label'] = utf8_decode($data['label']);
                //$data['property']['address'] = utf8_decode($data['property']['address']);
            if($data['type']==Invoice::TYPE_RECEIPT) {//pour regler le rpobleme des acents dans les factures manuelles
                $data=$this->convert_from_latin1_to_utf8_recursively2($data);
            }
            switch ($data['recursion']) {
                case Invoice::RECURSION_OTP:
					if($data['montantht']==-1){
						return -1;
					}
                    $fileName = "/invoice_{$data['number']}H-file2.pdf";
                    $pdf2->writeHTML($this->twig->render('invoices/invoice_otp.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                case Invoice::RECURSION_QUARTERLY:
                    return -1;
                    $fileName = "/invoice_quarterly{$data['number']}-file2.pdf";
                    $pdf2->writeHTML($this->twig->render('invoices/invoice_quarterly2.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                case Invoice::RECURSION_MONTHLY:
                    if($data['property']['honoraryRates']<=0){
						return -1;
					}
                    $fileName = "/invoice_{$data['number']}H-file2.pdf";
					$pdf2->writeHTML($this->twig->render('invoices/invoice2.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
					break;

                default:
                    $fileName = "/invoice_{$data['number']}H-file2.pdf";
					$pdf2->writeHTML($this->twig->render('invoices/invoice2.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;
            }

            $pdf2->output($this->path. $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf2->clean();
            throw new Exception($e->getMessage());
        }
    }
	
	public function generateManualRegulFile(array $data, array $parameters)
    {
        $pdf      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/invoice_{$data['number']}-file1.pdf";
        try {
            //$data=$this->convert_from_latin1_to_utf8_recursively2($data);

            $pdf->pdf->SetDisplayMode('fullpage');
            if(empty($data['recursion']))
                $data['recursion'] = Invoice::RECURSION_MONTHLY;
                //$data['reason'] = utf8_decode($data['reason']);
                //$data['label'] = utf8_decode($data['label']);
                //$data['property']['address'] = utf8_decode($data['property']['address']);
            
					if($data['montantttc']==-1){
						return -1;
					}
                    $fileName = "/invoice_{$data['number']}R-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/invoice_regule.html.twig', ['pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    

            

            $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf'. $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf->clean();
            throw new Exception($e->getMessage());
        }
    }
	
	
    
    

    public function generateAvoirFile(array $data, array $parameters)
    {
        $pdf      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/avoir_{$data['number']}-file1.pdf";
        //$data=$this->convert_from_latin1_to_utf8_recursively2($data);
        try {
            $pdf->pdf->SetDisplayMode('fullpage');
            if(empty($data['recursion']))
                $data['recursion'] = Invoice::RECURSION_MONTHLY;
                //$data['property']['address'] = utf8_decode($data['property']['address']);
            switch ($data['recursion']) {
                case Invoice::RECURSION_OTP:
					if($data['amount']==-1){
						return -1;
					}
                    $fileName = "/avoir_{$data['number']}R-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/avoir_otp_1.html.twig', ['numero'=> "{$data['old_number']}",'pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                case Invoice::RECURSION_QUARTERLY:
                    $fileName = "/avoir_quarterly{$data['number']}-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/avoir_quarterly.html.twig', ['numero'=> "{$data['old_number']}",'pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                case Invoice::RECURSION_MONTHLY:
                    if($data['property']['annuity']<=0){
						return -1;
					}
                    $fileName = "/avoir_{$data['number']}R-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/avoir.html.twig', ['numero'=> "{$data['old_number']}",'pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                default:
                    $fileName = "/avoir_{$data['number']}R-file1.pdf";
                    $pdf->writeHTML($this->twig->render('invoices/avoir.html.twig', ['numero'=> "{$data['old_number']}",'pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;
            }

            $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf'. $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf->clean();
            throw new Exception($e->getMessage());
        }
    }
    public function generateAvoirFile2(array $data, array $parameters)
    {
        $pdf2      = new Html2Pdf('P', 'A4', 'fr');
        $fileName = "/avoir_{$data['number']}-file2.pdf";
        //$data=$this->convert_from_latin1_to_utf8_recursively2($data);
        try {
            $pdf2->pdf->SetDisplayMode('fullpage');
            if(empty($data['recursion']))
                $data['recursion'] = Invoice::RECURSION_MONTHLY;
                //$data['property']['address'] = utf8_decode($data['property']['address']);
                
            switch ($data['recursion']) {
                case Invoice::RECURSION_OTP:
					if($data['montantht']==-1){
						return -1;
					}
                    $fileName = "/avoir_{$data['number']}H-file2.pdf";
                    $pdf2->writeHTML($this->twig->render('invoices/avoir_otp.html.twig', ['numero'=> "{$data['old_number']}",'pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                case Invoice::RECURSION_QUARTERLY:
                    return -1;
                    $fileName = "/avoir_quarterly{$data['number']}-file2.pdf";
                    $pdf2->writeHTML($this->twig->render('invoices/avoir_quarterly2.html.twig', ['numero'=> "{$data['old_number']}",'pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;

                case Invoice::RECURSION_MONTHLY:
                    if($data['property']['honoraryRates']<=0){
						return -1;
					}
                    $fileName = "/avoir_{$data['number']}H-file2.pdf";
					$pdf2->writeHTML($this->twig->render('invoices/avoir2.html.twig', ['numero'=> "{$data['old_number']}",'pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
					break;

                default:
                    $fileName = "/avoir_{$data['number']}H-file2.pdf";
					$pdf2->writeHTML($this->twig->render('invoices/avoir2.html.twig', ['numero'=> "{$data['old_number']}",'pdf_logo_path' => $this->pdf_logo, 'parameters' => $parameters, 'data' => $data]));
                    break;
            }

            $pdf2->output($this->path. $fileName, 'F');
            return $this->path . $fileName;
        } catch (Html2PdfException $e) {
            $pdf2->clean();
            throw new Exception($e->getMessage());
        }
    }

    public function generateCourrierIndexationDebirentierAutomatique( Property $property, array $parameters)
    {
               $data = array();
                $now_date=new DateTime();
                $now_date2=new DateTime();
                $next_month_date=$now_date2->modify('+0 month');
                $date_virement = utf8_encode(strftime("%B %Y", strtotime( $next_month_date->format('d-m-Y') )));
                $date_revision = utf8_encode(strftime("%B %Y", strtotime('+1 year',strtotime( $next_month_date->format('d-m-Y') ))));
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
               
                $date_fdnm = new DateTime('First day of this month');
                //ne pas toucher meme si ca parait insensé
                $fileName = "Courrier d’indexation Débirentier -".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                
                
                
                $month_m_u=$property->initial_index_object->getDate()->format('m');
                $endDate_m_u = \DateTime::createFromFormat('d-n-Y', "31-".$month_m_u."-".date('Y'));
                $endDate_m_u->setTime(0, 0, 0);
                // recuperer Valeur Indice de référence* (indexation)
                
                $qb4=$this->manager->createQueryBuilder()
                ->select("rh")
                ->from('App\Entity\RevaluationHistory', 'rh')
                ->where('rh.type LIKE :key')
                ->andWhere('rh.date <= :end')
                ->andWhere('rh.date like  :endmonth')
                ->setParameter('key', $this->get_label($property->getIntitulesIndicesInitial()))
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

                $honorary= ($property->getInitialAmount()*($indice_m_u->getValue()/$property->initial_index_object->getValue()))*$property->honorary_rates_object->getValeur()/100;
                if($property->honorary_rates_object && $honorary<$property->honorary_rates_object->getMinimum()){
                    $honorary=$property->honorary_rates_object->getMinimum();

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
                    "texte_assurance_habit" => "votre attestation d’assurance habitation couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chemine" => ($property->date_cheminee && $property->date_cheminee < $now_date )?true:false,
                    "texte_assurance_chemine" => "votre attestation d’entretien cheminée couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chaudiere" => ($property->date_chaudiere && $property->date_chaudiere < $now_date )?true:false,
                    "texte_assurance_chaudiere" => "votre attestation d’entretien chaudière couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_climatisation" => ($property->date_climatisation && $property->date_climatisation < $now_date )?true:false,
                    "texte_assurance_climatisation" => "votre attestation d’entretien climatisation couvrant l’année ".$next_month_date->format('Y'),
                    "adresse_bien"=>$property->getGoodAddress(),
                    "date_virement" => $date_virement,
                    "date_revision" => $date_revision,
                    "fd_next_month_d_m_y" => utf8_encode(strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "fd_next_month_m_y" => utf8_encode(strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    
                    "date_indice_base" =>  utf8_encode(strftime("%B %Y", strtotime( $property->initial_index_object->getDate()->format('d-m-Y') ))),
                    "montant_indice_base" => $property->initial_index_object->getValue(),
                    "date_indice_actuel" =>  utf8_encode(strftime("%B %Y", strtotime( $indice_m_u->getDate()->format('d-m-Y') ))),
                    "montant_indice_actuel" => $indice_m_u->getValue(),
                    "ia" => $property->getInitialAmount(),
                    "rente" => round($property->getInitialAmount()*($indice_m_u->getValue()/$property->initial_index_object->getValue()),2),
                    "honoraires" => round($honorary,2),
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
                    $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-debit-template-auto.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                    return  $this->path."/".$fileName;

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
    }

    public function generateCourrierIndexationCredirentierAutomatique(Property $property, array $parameters)
    {
                $data = array();
                $now_date=new DateTime();
                $now_date2=new DateTime();
                $next_month_date=$now_date2->modify('+0 month');
                $date_virement = utf8_encode(strftime("%B %Y", strtotime( $next_month_date->format('d-m-Y') )));
                $date_revision = utf8_encode(strftime("%B %Y", strtotime('+1 year',strtotime( $next_month_date->format('d-m-Y') ))));
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
               
                $date_fdnm = new DateTime('First day of this month');
                //ne pas toucher meme si ca parait insensé
                $fileName = "Courrier d’indexation Crédirentier - ".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";

                
                
                $month_m_u=$property->initial_index_object->getDate()->format('m');
                $endDate_m_u = \DateTime::createFromFormat('d-n-Y', "31-".$month_m_u."-".date('Y'));
                $endDate_m_u->setTime(0, 0, 0);
                // recuperer Valeur Indice de référence* (indexation)
                
                $qb4=$this->manager->createQueryBuilder()
                ->select("rh")
                ->from('App\Entity\RevaluationHistory', 'rh')
                ->where('rh.type LIKE :key')
                ->andWhere('rh.date <= :end')
                ->andWhere('rh.date like  :endmonth')
                ->setParameter('key', $this->get_label($property->getIntitulesIndicesInitial()))
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

                $honorary=(($property->getInitialAmount()*($indice_m_u->getValue()/$property->initial_index_object->getValue()))*$property->honorary_rates_object->getValeur()/100);
                if($property->honorary_rates_object && $honorary<$property->honorary_rates_object->getMinimum()){
                    $honorary=$property->honorary_rates_object->getMinimum();
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
                    "texte_assurance_habit" => "votre attestation d’assurance habitation couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chemine" => ($property->date_cheminee && $property->date_cheminee < $now_date )?true:false,
                    "texte_assurance_chemine" => "votre attestation d’entretien cheminée couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chaudiere" => ($property->date_chaudiere && $property->date_chaudiere < $now_date )?true:false,
                    "texte_assurance_chaudiere" => "votre attestation d’entretien chaudière couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_climatisation" => ($property->date_climatisation && $property->date_climatisation < $now_date )?true:false,
                    "texte_assurance_climatisation" => "votre attestation d’entretien climatisation couvrant l’année ".$next_month_date->format('Y'),
                    "adresse_bien"=>$property->getGoodAddress(),
                    "date_virement" => $date_virement,
                    "date_revision" => $date_revision,
                    "fd_next_month_d_m_y" => utf8_encode(strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "fd_next_month_m_y" => utf8_encode(strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    
                    "date_indice_base" =>  utf8_encode(strftime("%B %Y", strtotime( $property->initial_index_object->getDate()->format('d-m-Y') ))),
                    "montant_indice_base" => $property->initial_index_object->getValue(),
                    "date_indice_actuel" =>  utf8_encode(strftime("%B %Y", strtotime( $indice_m_u->getDate()->format('d-m-Y') ))),
                    "montant_indice_actuel" => $indice_m_u->getValue(),
                    
                    "ia" => $property->getInitialAmount(),
                    "rente" => round($property->getInitialAmount()*($indice_m_u->getValue()/$property->initial_index_object->getValue()),2),
                    "honoraires" => round($honorary,2),
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
                    $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-credit-template-auto.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    
                    $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                    return  $this->path."/".$fileName;

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
    }

	
	 public function generateCourrierIndexationMandantAutomatique(Property $property, array $parameters)
    {
                $data = array();
                $now_date=new DateTime();
                $now_date2=new DateTime();
                $next_month_date=$now_date2->modify('+0 month');
                $date_virement = utf8_encode(strftime("%B %Y", strtotime( $next_month_date->format('d-m-Y') )));
                $date_revision = utf8_encode(strftime("%B %Y", strtotime('+1 year',strtotime( $next_month_date->format('d-m-Y') ))));
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
               
                $date_fdnm = new DateTime('First day of this month');
                //ne pas toucher meme si ca parait insensé
                $fileName = "Courrier d’indexation Mandant - ".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                
                
                $month_m_u=$property->initial_index_object->getDate()->format('m');
                $endDate_m_u = \DateTime::createFromFormat('d-n-Y', "31-".$month_m_u."-".date('Y'));
                $endDate_m_u->setTime(0, 0, 0);
                // recuperer Valeur Indice de référence* (indexation)
                
                $qb4=$this->manager->createQueryBuilder()
                ->select("rh")
                ->from('App\Entity\RevaluationHistory', 'rh')
                ->where('rh.type LIKE :key')
                ->andWhere('rh.date <= :end')
                ->andWhere('rh.date like  :endmonth')
                ->setParameter('key', $this->get_label($property->getIntitulesIndicesInitial()))
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

                $honorary=(($property->getInitialAmount()*($indice_m_u->getValue()/$property->initial_index_object->getValue()))*$property->honorary_rates_object->getValeur()/100);
                if($property->honorary_rates_object && $honorary<$property->honorary_rates_object->getMinimum()){
                    $honorary=$property->honorary_rates_object->getMinimum();

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
                    "texte_assurance_habit" => "votre attestation d’assurance habitation couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chemine" => ($property->date_cheminee && $property->date_cheminee < $now_date )?true:false,
                    "texte_assurance_chemine" => "votre attestation d’entretien cheminée couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chaudiere" => ($property->date_chaudiere && $property->date_chaudiere < $now_date )?true:false,
                    "texte_assurance_chaudiere" => "votre attestation d’entretien chaudière couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_climatisation" => ($property->date_climatisation && $property->date_climatisation < $now_date )?true:false,
                    "texte_assurance_climatisation" => "votre attestation d’entretien climatisation couvrant l’année ".$next_month_date->format('Y'),
                    "adresse_bien"=>($property->getShowDuh())?$property->getGoodAddress():$property->getAddress(),
                    "date_virement" => $date_virement,
                    "date_revision" => $date_revision,
                    "fd_next_month_d_m_y" => utf8_encode(strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "fd_next_month_m_y" => utf8_encode(strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    
                    "date_indice_base" =>  utf8_encode(strftime("%B %Y", strtotime( $property->initial_index_object->getDate()->format('d-m-Y') ))),
                    "montant_indice_base" => $property->initial_index_object->getValue(),
                    "date_indice_actuel" =>  utf8_encode(strftime("%B %Y", strtotime( $indice_m_u->getDate()->format('d-m-Y') ))),
                    "montant_indice_actuel" => $indice_m_u->getValue(),
                    
                    "ia" => $property->getInitialAmount(),
                    "rente" => round($property->getInitialAmount()*($indice_m_u->getValue()/$property->initial_index_object->getValue()),2),
                    "honoraires" => round($honorary,2),
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
                    $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-mandant-template-auto.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
					$pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                    return  $this->path."/".$fileName;
                    
                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
    }




    public function generateCourrierIndexationOG2IDebirentierAutomatique( Property $property, array $parameters)
    {
               $data = array();
                $now_date=new DateTime();
                $now_date2=new DateTime();
                $next_month_date=$now_date2->modify('+0 month');
                $date_virement = utf8_encode(strftime("%B %Y", strtotime( $next_month_date->format('d-m-Y') )));
                $date_revision = utf8_encode(strftime("%B %Y", strtotime('+1 year',strtotime( $next_month_date->format('d-m-Y') ))));
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
               
                $date_fdnm = new DateTime('First day of this month');
                //ne pas toucher meme si ca parait insensé
                $fileName = "Courrier d’indexation OG2I Débirentier -".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                
                
                
                $month_og2i=$property->valeur_indice_ref_og2_i_object->getDate()->format('m');
                $endDate_og2i = \DateTime::createFromFormat('d-n-Y', "31-".$month_og2i."-".date('Y'));
                $endDate_og2i->setTime(0, 0, 0);
                // recuperer Valeur Indice de référence* (indexation)
                
                $qb4=$this->manager->createQueryBuilder()
                ->select("rh")
                ->from('App\Entity\RevaluationHistory', 'rh')
                ->where('rh.type LIKE :key and rh.date <= :end')
                ->andWhere('rh.date like  :endmonth')
                ->setParameter('key', 'OGI')
                ->setParameter('end', $endDate_og2i)
                ->setParameter('endmonth',  "%-%".$month_og2i."-%")
                    ->orderBy('rh.date', 'DESC');
                $query = $qb4->getQuery();
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
                if($honoraires<$property->honorary_rates_object->getMinimum() && $property->honorary_rates_object){
                    $honoraires=$property->honorary_rates_object->getMinimum();  
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
                    "texte_assurance_habit" => "votre attestation d’assurance habitation couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chemine" => ($property->date_cheminee && $property->date_cheminee < $now_date )?true:false,
                    "texte_assurance_chemine" => "votre attestation d’entretien cheminée couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chaudiere" => ($property->date_chaudiere && $property->date_chaudiere < $now_date )?true:false,
                    "texte_assurance_chaudiere" => "votre attestation d’entretien chaudière couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_climatisation" => ($property->date_climatisation && $property->date_climatisation < $now_date )?true:false,
                    "texte_assurance_climatisation" => "votre attestation d’entretien climatisation couvrant l’année ".$next_month_date->format('Y'),
                    "adresse_bien"=>$property->getGoodAddress(),
                    "date_virement" => $date_virement,
                    "date_revision" => $date_revision,
                    "fd_next_month_d_m_y" => utf8_encode(strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "fd_next_month_m_y" => utf8_encode(strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    
                    "date_indice_base" =>  utf8_encode(strftime("%B %Y", strtotime( $property->initial_index_object->getDate()->format('d-m-Y') ))),
                    "montant_indice_base" => $property->initial_index_object->getValue(),
                    "date_indice_actuel" =>  utf8_encode(strftime("%B %Y", strtotime( $indice_og2i->getDate()->format('d-m-Y') ))),
                    "montant_indice_actuel" => $indice_og2i->getValue(),
                    "ia" => $property->getInitialAmount(),
                    "date_indice_base_og2i" =>  strftime("%B %Y", strtotime( $property->valeur_indice_ref_og2_i_object->getDate()->format('d-m-Y') )),
                    "montant_indice_base_og2i" => $property->valeur_indice_ref_og2_i_object->getValue(),
                    "date_indice_actuel_og2i" =>  strftime("%B %Y", strtotime( $indice_og2i->getDate()->format('d-m-Y') )),
                    "montant_indice_actuel_og2i" => $indice_og2i->getValue(),
                    "is_plaff" => $is_plaff,
                    "plaff_val" => $property->plafonnement_index_og2_i,
                    "rdb" => $rdb,
                    "res" => $res,
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
                    $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-og2i-debit-template-auto.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                    return  $this->path."/".$fileName;

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
    }

    public function generateCourrierIndexationOG2ICredirentierAutomatique(Property $property, array $parameters)
    {
                $data = array();
                $now_date=new DateTime();
                $now_date2=new DateTime();
                $next_month_date=$now_date2->modify('+0 month');
                $date_virement = utf8_encode(strftime("%B %Y", strtotime( $next_month_date->format('d-m-Y') )));
                $date_revision = utf8_encode(strftime("%B %Y", strtotime('+1 year',strtotime( $next_month_date->format('d-m-Y') ))));
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
               
                $date_fdnm = new DateTime('First day of this month');
                //ne pas toucher meme si ca parait insensé
                $fileName = "Courrier d’indexation OG2I Crédirentier - ".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";

                
                
                $month_og2i=$property->valeur_indice_ref_og2_i_object->getDate()->format('m');
                $endDate_og2i = \DateTime::createFromFormat('d-n-Y', "31-".$month_og2i."-".date('Y'));
                $endDate_og2i->setTime(0, 0, 0);
                // recuperer Valeur Indice de référence* (indexation)
                
                $qb4=$this->manager->createQueryBuilder()
                ->select("rh")
                ->from('App\Entity\RevaluationHistory', 'rh')
                ->where('rh.type LIKE :key and rh.date <= :end')
                ->andWhere('rh.date like  :endmonth')
                ->setParameter('key', 'OGI')
                ->setParameter('end', $endDate_og2i)
                ->setParameter('endmonth',  "%-%".$month_og2i."-%")
                    ->orderBy('rh.date', 'DESC');
                $query = $qb4->getQuery();
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
                if($honoraires<$property->honorary_rates_object->getMinimum() && $property->honorary_rates_object){
                    $honoraires=$property->honorary_rates_object->getMinimum();  
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
                    "texte_assurance_habit" => "votre attestation d’assurance habitation couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chemine" => ($property->date_cheminee && $property->date_cheminee < $now_date )?true:false,
                    "texte_assurance_chemine" => "votre attestation d’entretien cheminée couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chaudiere" => ($property->date_chaudiere && $property->date_chaudiere < $now_date )?true:false,
                    "texte_assurance_chaudiere" => "votre attestation d’entretien chaudière couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_climatisation" => ($property->date_climatisation && $property->date_climatisation < $now_date )?true:false,
                    "texte_assurance_climatisation" => "votre attestation d’entretien climatisation couvrant l’année ".$next_month_date->format('Y'),
                    "adresse_bien"=>$property->getGoodAddress(),
                    "date_virement" => $date_virement,
                    "date_revision" => $date_revision,
                    "fd_next_month_d_m_y" => utf8_encode(strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "fd_next_month_m_y" => utf8_encode(strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    
                    "date_indice_base" =>  utf8_encode(strftime("%B %Y", strtotime( $property->initial_index_object->getDate()->format('d-m-Y') ))),
                    "montant_indice_base" => $property->initial_index_object->getValue(),
                    "date_indice_actuel" =>  utf8_encode(strftime("%B %Y", strtotime( $indice_og2i->getDate()->format('d-m-Y') ))),
                    "montant_indice_actuel" => $indice_og2i->getValue(),
                    
                    "date_indice_base_og2i" =>  strftime("%B %Y", strtotime( $property->valeur_indice_ref_og2_i_object->getDate()->format('d-m-Y') )),
                    "montant_indice_base_og2i" => $property->valeur_indice_ref_og2_i_object->getValue(),
                    "date_indice_actuel_og2i" =>  strftime("%B %Y", strtotime( $indice_og2i->getDate()->format('d-m-Y') )),
                    "montant_indice_actuel_og2i" => $indice_og2i->getValue(),
                    "is_plaff" => $is_plaff,
                    "plaff_val" => $property->plafonnement_index_og2_i,
                    "ia" => $property->getInitialAmount(),
                    "rdb" => $rdb,
                    "res" => $res,
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
                    $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-og2i-credit-template-auto.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
                    
                    $pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                    return  $this->path."/".$fileName;

                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
    }

	
	 public function generateCourrierIndexationOG2IMandantAutomatique(Property $property, array $parameters)
    {
                $data = array();
                $now_date=new DateTime();
                $now_date2=new DateTime();
                $next_month_date=$now_date2->modify('+0 month');
                $date_virement = utf8_encode(strftime("%B %Y", strtotime( $next_month_date->format('d-m-Y') )));
                $date_revision = utf8_encode(strftime("%B %Y", strtotime('+1 year',strtotime( $next_month_date->format('d-m-Y') ))));
                $pdf      = new Html2Pdf('P', 'A4', 'fr');
               
                $date_fdnm = new DateTime('First day of this month');
                //ne pas toucher meme si ca parait insensé
                $fileName = "Courrier d’indexation OG2I Mandant - ".$property->getId()."-".$now_date->format('d-m-Y h:i:s').".pdf";
                
                
                $month_og2i=$property->valeur_indice_ref_og2_i_object->getDate()->format('m');
                $endDate_og2i = \DateTime::createFromFormat('d-n-Y', "31-".$month_og2i."-".date('Y'));
                $endDate_og2i->setTime(0, 0, 0);
                // recuperer Valeur Indice de référence* (indexation)
                
                $qb4=$this->manager->createQueryBuilder()
                ->select("rh")
                ->from('App\Entity\RevaluationHistory', 'rh')
                ->where('rh.type LIKE :key and rh.date <= :end')
                ->andWhere('rh.date like  :endmonth')
                ->setParameter('key', 'OGI')
                ->setParameter('end', $endDate_og2i)
                ->setParameter('endmonth',  "%-%".$month_og2i."-%")
                    ->orderBy('rh.date', 'DESC');
                $query = $qb4->getQuery();
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
                if($honoraires<$property->honorary_rates_object->getMinimum() && $property->honorary_rates_object){
                    $honoraires=$property->honorary_rates_object->getMinimum();  
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
                    "texte_assurance_habit" => "votre attestation d’assurance habitation couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chemine" => ($property->date_cheminee && $property->date_cheminee < $now_date )?true:false,
                    "texte_assurance_chemine" => "votre attestation d’entretien cheminée couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_chaudiere" => ($property->date_chaudiere && $property->date_chaudiere < $now_date )?true:false,
                    "texte_assurance_chaudiere" => "votre attestation d’entretien chaudière couvrant l’année ".$next_month_date->format('Y'),
                    "not_assurance_climatisation" => ($property->date_climatisation && $property->date_climatisation < $now_date )?true:false,
                    "texte_assurance_climatisation" => "votre attestation d’entretien climatisation couvrant l’année ".$next_month_date->format('Y'),
                    "adresse_bien"=>($property->getShowDuh())?$property->getGoodAddress():$property->getAddress(),
                    "date_virement" => $date_virement,
                    "date_revision" => $date_revision,
                    "fd_next_month_d_m_y" => utf8_encode(strftime("%d %B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "fd_next_month_m_y" => utf8_encode(strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') ))),
                    "nom_compte" => explode("/", $property->getTitle())[0],
                    
                    "date_indice_base" =>  utf8_encode(strftime("%B %Y", strtotime( $property->initial_index_object->getDate()->format('d-m-Y') ))),
                    "montant_indice_base" => $property->initial_index_object->getValue(),
                    "date_indice_actuel" =>  utf8_encode(strftime("%B %Y", strtotime( $indice_og2i->getDate()->format('d-m-Y') ))),
                    "montant_indice_actuel" => $indice_og2i->getValue(),

                    "date_indice_base_og2i" =>  strftime("%B %Y", strtotime( $property->valeur_indice_ref_og2_i_object->getDate()->format('d-m-Y') )),
                    "montant_indice_base_og2i" => $property->valeur_indice_ref_og2_i_object->getValue(),
                    "date_indice_actuel_og2i" =>  strftime("%B %Y", strtotime( $indice_og2i->getDate()->format('d-m-Y') )),
                    "montant_indice_actuel_og2i" => $indice_og2i->getValue(),
                    "is_plaff" => $is_plaff,
                    "plaff_val" => $property->plafonnement_index_og2_i,
                    "ia" => $property->getInitialAmount(),
                    "rdb" => $rdb,
                    "res" => $res,
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
                    $pdf->writeHTML($this->twig->render('generated_files/courrier-indexation-og2i-mandant-template-auto.html.twig', ['pdf_logo_path' => $this->pdf_logo,'parameters' => $parameters, 'data' => $data]));
					$pdf->output('/var/www/vhosts/dev.adm.viag2e.fr/dev.adm.viag2e.fr/pdf/'. $fileName, 'F');
                    return  $this->path."/".$fileName;
                    
                } catch (Html2PdfException $e) {
                    $pdf->clean();
                    throw new Exception($e->getMessage());
                }
    }

}

