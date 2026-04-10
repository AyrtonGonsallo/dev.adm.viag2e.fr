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

class TextReplacer
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
            return 'IRL';
        }

    }


    public function replaceText( Property $property, String $texte, String $former_destinataire)
    {
               $data = array();
               $now_date=new DateTime();
                $now_date2=new DateTime();
                $next_month_date=$now_date2->modify('+1 month');
                $date_virement = utf8_encode(strftime("%B %Y", strtotime( $next_month_date->format('d-m-Y') )));
                $date_revision = utf8_encode(strftime("%B %Y", strtotime('+1 year',strtotime( $next_month_date->format('d-m-Y') ))));
                $date_fdnm = new DateTime('First day of next month');

                if($property->getClauseOG2I()){
                    
                   
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

                    if($res<$mi){
                        $rente = $mi;
                        $is_plaff=false;
                    }
                    else if(!$plaff || $plaff<=0){
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
                    
               }else{
                    
                
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

               }
                





                $mois_indexation = utf8_encode(strftime("%B %Y", strtotime( $date_fdnm->format('d-m-Y') )));
                $nom_du_bien = $property->getTitle();
                $adresse_du_bien = $property->getGoodAddress();
                $montant_initial = $property->getInitialAmount();
                $indice_de_base = "Pour rappel, l’indice de base est celui du mois de ".$data['date_indice_base']." (valeur : ".$data['montant_indice_base'].") ";
                $nouvel_indice = "le nouvel indice du mois de ".$data['date_indice_actuel']." est de ".$data['montant_indice_actuel'];
                $civilite_destinataire = "";
                $date_prochaine_revision = $date_revision;
                $target_name = "";
                $target_address = "";
                $target_account = "";
                $creantier = "";
                if($property->getClauseOG2I()){
                    $formule_indexation_og2i = '';

                    if (!$data['is_plaff']) {
                        $formule_indexation_og2i .= 
                            "Rente de base = Rente actuelle ({$data['ia']} €) "
                            ."x Indice og2i de base de {$data['date_indice_base_og2i']} "
                            ."({$data['montant_indice_base_og2i']}) "
                            ."/ Nouvel indice de {$data['date_indice_base']} "
                            ."({$data['montant_indice_base']}).<br /><br />";

                        $formule_indexation_og2i .= 
                            "Nouveau montant = Rente de base ({$data['rdb']} €) "
                            ."x Nouvel indice de {$data['date_indice_actuel_og2i']} "
                            ."({$data['montant_indice_actuel_og2i']}) "
                            ."/ indice de référence de {$data['date_indice_base_og2i']} "
                            ."({$data['montant_indice_base_og2i']}).<br /><br />";
                    }

                    if ($data['is_plaff']) {
                        $formule_indexation_og2i .= 
                            "Rente de base ({$data['rdb']} €) "
                            ."x (1 + plafonnement ({$data['plaff_val']}) / 100).<br /><br />";
                    }

                    $montant_rente_indexation_og2i ="Le nouveau montant de la rente viagère sera ainsi porté à {$data['rente']} €</b> pour le virement de la rente du mois de {$data['date_virement']}.";
                    $montant_honoraires_indexation_og2i = "Les honoraires de gestion passent eux à ".$data['honoraires']." € TTC.";
                    $texte = str_replace(
                        '[formule_indexation_og2i]',
                        $formule_indexation_og2i,
                        $texte
                    );
                    
                    $texte = str_replace(
                        '[formule_indexation_og2i]',
                        $formule_indexation_og2i,
                        $texte
                    );
                    $texte = str_replace(
                        '[montant_rente_indexation_og2i]',
                        $montant_rente_indexation_og2i,
                        $texte
                    );
                    $texte = str_replace(
                        '[montant_honoraires_indexation_og2i]',
                        $montant_honoraires_indexation_og2i,
                        $texte
                    );
                }else{
                    $texte_indexation_honoraires = "Les honoraires de gestion passent eux à ".$data['honoraires']." € TTC.";
                    $montant_rente_indexation_normale = "Le nouveau montant de la rente viagère sera ainsi porté à ".$data['rente']." € pour le virement  de la rente du mois de ".$data['date_virement'];
                    $Valeur_indice_reference = $data['montant_indice_base']." de ".$data['date_indice_base'];

                    $texte = str_replace(
                        '[texte_indexation_honoraires]',
                        $texte_indexation_honoraires,
                        $texte
                    );

                    $texte = str_replace(
                        '[montant_rente_indexation_normale]',
                        $montant_rente_indexation_normale,
                        $texte
                    );
                    $texte = str_replace(
                        '[Valeur_indice_reference]',
                        $Valeur_indice_reference,
                        $texte
                    );
                    $texte = str_replace(
                        '[nv_montant]',
                        $data['rente'],
                        $texte
                    );
                    $texte = str_replace(
                        '[Montant_des_honoraires]',
                        $data['honoraires'],
                        $texte
                    );

                }


                $documents = [];
                // Collecte des documents manquants
                if ($data['not_assurance_habit']) {
                    $documents[] = $data['texte_assurance_habit'];
                }
                if ($data['not_assurance_chemine']) {
                    $documents[] = $data['texte_assurance_chemine'];
                }
                if ($data['not_assurance_chaudiere']) {
                    $documents[] = $data['texte_assurance_chaudiere'];
                }
                if ($data['not_assurance_climatisation']) {
                    $documents[] = $data['texte_assurance_climatisation'];
                }
                // Construction du texte final
                $documents_a_fournir_indexation = '';
                if (!empty($documents)) {
                    $documents_a_fournir_indexation .= "Je vous remercie de bien vouloir nous faire parvenir :<br>";
                    foreach ($documents as $doc) {
                        $documents_a_fournir_indexation .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- {$doc}<br>";
                    }
                }
    
                $date_duh = utf8_encode(strftime("%B %Y", strtotime( $property->date_duh->format('d-m-Y') )));

                switch ($former_destinataire) {
                    case 'Crédirentier':
                        $civilite_destinataire = $property->getCivilite1Label();
                        $nom_destinataire = $property->getLastname1();
                        $prenom_destinataire = $property->getFirstname1();
                        $target_address = $property->getAdresseCredirentier1();
                        $target_address .= "<br>".$property->getCodePostalCredirentier1();
                        $target_address .= "<br>".$property->getVilleCredirentier1();
                        $target_account = $property->bank_iban_1;
                        $target_account .= "<br>".$property->bank_bic_1;
                        $target_account .= "<br>".$property->bank_domiciliation_1;
                        $creantier = "E.U.R.L. V GIBELIN CONSEILS<br>
                            FR12ZZZ886B32<br>
                            58 rue Fondaudège<br>
                            33000<br>
                            France<br>
                            BORDEAUX<br>";
                        break;
                    case 'Débirentier':
                        $civilite_destinataire = $property->getCiviliteDebirentierLabel();
                        $nom_destinataire = $property->nom_debirentier;
                        $prenom_destinataire = $property->prenom_debirentier;
                        $target_address = $property->addresse_debirentier;
                        $target_address .= "<br>".$property->code_postal_debirentier;
                        $target_address .= "<br>".$property->ville_debirentier;
                        $target_account = $property->bank_iban_1;
                        $target_account .= "<br>".$property->bank_bic_1;
                        $target_account .= "<br>".$property->bank_domiciliation_1;
                        $creantier = "E.U.R.L. V GIBELIN CONSEILS<br>
                            FR12ZZZ886B32<br>
                            58 rue Fondaudège<br>
                            33000<br>
                            France<br>
                            BORDEAUX<br>";
                        break;
                    case 'Mandant':
                        $civilite_destinataire = "Monsieur";
                        $nom_destinataire = $property->getWarrant()->getLastname();
                        $prenom_destinataire = $property->getWarrant()->getFirstname();
                        $target_address = $property->getWarrant()->getAddress();
                        $target_address .= "<br>".$property->getWarrant()->getPostalCode();
                        $target_address .= "<br>".$property->getWarrant()->getCity();
                        $target_account = $property->getWarrant()->getBankIban();
                        $target_account .= "<br>".$property->getWarrant()->getBankBic();
                        $target_account .= "<br>".$property->getWarrant()->getBankDomiciliation();
                        $creantier = "E.U.R.L. V GIBELIN CONSEILS<br>
                            FR12ZZZ886B32<br>
                            58 rue Fondaudège<br>
                            33000<br>
                            France<br>
                            BORDEAUX<br>";
                        break;
                    
                    default:
                        $civilite = "";
                        break;
                }

                $texte = str_replace(
                    '[nouvel_indice]',
                    $nouvel_indice,
                    $texte
                );

                $texte = str_replace(
                    '[civilite_destinataire]',
                    $civilite_destinataire,
                    $texte
                );

                $texte = str_replace(
                    '[nom_destinataire]',
                    $nom_destinataire,
                    $texte
                );

                $texte = str_replace(
                    '[date_prochaine_revision]',
                    $date_prochaine_revision,
                    $texte
                );

                $texte = str_replace(
                    '[date_duh]',
                    $date_duh,
                    $texte
                );

                $texte = str_replace(
                    '[documents_a_fournir_indexation]',
                    $documents_a_fournir_indexation,
                    $texte
                );

                $texte = str_replace(
                    '[prenom_destinataire]',
                    $prenom_destinataire,
                    $texte
                );

                $texte = str_replace(
                    '[indice_de_base]',
                    $indice_de_base,
                    $texte
                );

               

                $texte = str_replace(
                    '[mois_indexation]',
                    $mois_indexation,
                    $texte
                );

                $texte = str_replace(
                    '[nom_du_bien]',
                    $nom_du_bien,
                    $texte
                );

                $texte = str_replace(
                    '[adresse_du_bien]',
                    $adresse_du_bien,
                    $texte
                );

                $texte = str_replace(
                    '[montant_initial]',
                    $montant_initial,
                    $texte
                );
                
                return $texte;
    }




    public function replaceOthersText( Property $property, String $client_texte, String $former_destinataire)
    {
           
            

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

                

                $nom_notaire = $property->nom_notaire;
                $prenom_notaire = $property->prenom_notaire;
                $adresse_notaire = $property->addresse_notaire;
                $code_postal_notaire = $property->code_postal_notaire;
                $ville_notaire = $property->ville_notaire;
                $date_acte = $property->date_duh->format('d/m/Y');
                $date_remise_clefs = $property->date_remise_cles->format('d/m/Y');
                $pourcentage_revalorisation_rente = $property->pourcentage_revaluation_rente;

                $adresse_bien = $property->getGoodAddress();
                $code_postal_bien = $property->getPostalCode();
                $ville_bien = $property->getCity();
                $num_mandat = $property->num_mandat_gestion;
                $condominiumFees = $property->getCondominiumFees();
                $syndic_quote_part = $property->syndic_quote_part;

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
               

                $client_texte = str_replace(
                            '[target_civilite1]',
                            $target_civilite1,
                            $client_texte
                        );

                        $client_texte = str_replace(
                            '[num_mandat]',
                            $num_mandat,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[target_nom1]',
                            $target_nom1,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[target_prenom1]',
                            $target_prenom1,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[target_civilite2]',
                            $target_civilite2,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[target_nom2]',
                            $target_nom2,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[target_prenom2]',
                            $target_prenom2,
                            $client_texte
                        );
                         $client_texte = str_replace(
                            '[adresse_bien]',
                            $adresse_bien,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[code_postal_bien]',
                            $code_postal_bien,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[ville_bien]',
                            $ville_bien,
                            $client_texte
                        );


                         $client_texte = str_replace(
                            '[nom_notaire]',
                            $nom_notaire,
                            $client_texte
                        );
                         $client_texte = str_replace(
                            '[condominiumFees]',
                            $condominiumFees,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[syndic_quote_part]',
                            $syndic_quote_part,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[prenom_notaire]',
                            $prenom_notaire,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[adresse_notaire]',
                            $adresse_notaire,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[code_postal_notaire]',
                            $code_postal_notaire,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[ville_notaire]',
                            $ville_notaire,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[date_acte]',
                            $date_acte,
                            $client_texte
                        );
                         $client_texte = str_replace(
                            '[pourcentage_revalorisation_rente]',
                            $pourcentage_revalorisation_rente,
                            $client_texte
                        );
                        $client_texte = str_replace(
                            '[date_remise_clefs]',
                            $date_remise_clefs,
                            $client_texte
                        );
                
                return $client_texte;
    }

  

}

