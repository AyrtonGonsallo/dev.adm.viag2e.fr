<?php

namespace App\Form;
use App\Entity\RevaluationHistory;
use App\Entity\Property;
use App\Entity\Warrant;
use App\Entity\Honoraire;
use App\Form\Type\DayType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
setlocale(LC_TIME, 'fr_FR.UTF8', 'fr.UTF8', 'fr_FR.UTF-8', 'fr.UTF-8');

class PropertyWarrantcreateFormType extends AbstractType
{
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
       
            ->add('title', TextType::class)
            ->add('address', TextType::class, ['required' => false])
            ->add('postalCode', TextType::class, ['required' => false])
            ->add('city', TextType::class, ['required' => false])
            ->add('country', TextType::class, ['required' => false])
            ->add('firstname1', TextType::class, ['required' => false])
            ->add('lastname1', TextType::class, ['required' => false])
            ->add('adresse_similaire_credirentier', CheckboxType::class, ['required' => false])

            ->add('adresse_credirentier1', TextType::class, ['required' => false])
            ->add('code_postal_credirentier1', TextType::class, ['required' => false])
            ->add('ville_credirentier1', TextType::class, ['required' => false])
            ->add('adresse_credirentier2', TextType::class, ['required' => false])
            ->add('code_postal_credirentier2', TextType::class, ['required' => false])
            ->add('ville_credirentier2', TextType::class, ['required' => false]);
            
        $builder->add('buyerFirstname', TextType::class, ['required' => false])
            ->add('buyerLastname', TextType::class, ['required' => false])
            ->add('buyerAddress', TextType::class, ['required' => false])
            ->add('buyerPostalCode', TextType::class, ['required' => false])
            ->add('buyerCity', TextType::class, ['required' => false])
            ->add('buyerCountry', TextType::class, ['required' => false])
            ->add('buyerPhone1', TextType::class, ['required' => false])
            ->add('buyerPhone2', TextType::class, ['required' => false])
            ->add('buyerMail1', TextType::class, ['required' => false])
            ->add('buyerMail2', TextType::class, ['required' => false])
            ->add('buyer_bank_establishment_code', TextType::class, ['required' => false])
            ->add('buyer_bank_code_box', TextType::class, ['required' => false])
            ->add('buyer_bank_account_number', TextType::class, ['required' => false])
            ->add('buyer_bank_key', TextType::class, ['required' => false])
            ->add('buyer_bank_domiciliation', TextType::class, ['required' => false])
            ->add('buyer_bank_iban', TextType::class, ['required' => false])
            ->add('buyer_bank_bic', TextType::class, ['required' => false]);
            
        $builder->add('firstname2', TextType::class, ['required' => false])
            ->add('lastname2', TextType::class, ['required' => false])
            ->add('mail1', EmailType::class, ['required' => false])
            ->add('intitules_indices_initial', ChoiceType::class, ['choices' => array_flip(Property::intitules_indices_initial), 'choice_translation_domain' => false])
            ->add('mois_indice_initial', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy','years' => range(date("Y")-22, date("Y")) ])

           // ->add('abandonmentIndex', TextType::class, ['required' => false])
            ->add('revaluationDate', DayType::class, ['required' => false])
            ->add('initialAmount', TextType::class, ['required' => false])
            ->add('valeur_indice_reference_object', EntityType::class, [
                'required' => false,
                'class' => RevaluationHistory::class,
                'query_builder' => function (EntityRepository $er)  use($options){
                    
                        return $er->createQueryBuilder('rh')
                        ->where('rh.type LIKE :key1 or rh.type LIKE :key2 or rh.type LIKE :key3')
                        ->setParameter('key1', "Urbains")
                        ->setParameter('key2', "Ménages")
                        ->setParameter('key3', "OGI")
                        ->orderBy('rh.type', 'ASC')
                        ->addOrderBy('rh.date', 'DESC');
                    
                    
                },
                'choice_attr' => function($package) use ($options) {
                    if($options['data']->valeur_indice_reference_object){
                        $selected = false;
                        if($package == $options['data']->valeur_indice_reference_object) {
                            $selected = true;
                        }
                        return ['selected' => $selected];
                    }else{
                        return ['selected' => false];
                    }
                    
                },
                'choice_label' => function (RevaluationHistory $rh): string {
                    return $rh->getValue().' '.$rh->getType().' mois de '.(strftime('%B %Y',$rh->getDate()->getTimestamp()));
                },
                'choice_value' => 'id',
            ])
            ->add('condominiumFees', TextType::class, ['required' => false])
            ->add('garbageTax', TextType::class, ['required' => false])

             ->add('bank_establishment_code_1', TextType::class, ['required' => false])
            ->add('bank_iban_1', TextType::class, ['required' => false])
            ->add('bank_code_box_1', TextType::class, ['required' => false])
             ->add('bank_bic_1', TextType::class, ['required' => false])
            ->add('bank_rib_1', TextType::class, ['required' => false])
            ->add('bank_ics_1', TextType::class, ['required' => false,'disabled' => 'true','data' => 'FR12ZZZ886B32'],)
            ->add('bank_account_number_1', TextType::class, ['required' => false])
            ->add('bank_rum_1', TextType::class, ['required' => false])
            ->add('bank_domiciliation_1', TextType::class, ['required' => false])

            ->add('bank_establishment_code_2', TextType::class, ['required' => false])
            ->add('bank_iban_2', TextType::class, ['required' => false])
            ->add('bank_code_box_2', TextType::class, ['required' => false])
             ->add('bank_bic_2', TextType::class, ['required' => false])
            ->add('bank_rib_2', TextType::class, ['required' => false])
            ->add('bank_ics_2', TextType::class, ['required' => false,'disabled' => 'true','data' => 'FR12ZZZ886B32'],)
            ->add('bank_account_number_2', TextType::class, ['required' => false])
            ->add('bank_rum_2', TextType::class, ['required' => false])
            ->add('bank_domiciliation_2', TextType::class, ['required' => false])



            ->add('num_mandat_gestion', TextType::class, ['required' => false])
           // ->add('comment', TextareaType::class, ['required' => false])
            ->add('honorary_rates_object', EntityType::class, [
                'class' => Honoraire::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('h')
                        ->orderBy('h.id', 'DESC');
                },
                'choice_label' => function (Honoraire $h): string {
                    return 'Titre: '.$h->getNom().' - Taux: '.$h->getValeur().'%';
                },
                'choice_value' => 'id',

                // 👇 IMPORTANT
                'required' => false,
                'placeholder' => '— Aucun taux —',
            ])

            ->add('warrant', EntityType::class, [
                'class' => Warrant::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('w')
                        ->orderBy('w.lastname', 'ASC');
                },
                'choice_label' => function (Warrant $w): string {
                    return $w->getLastname().' '.$w->getFirstname(). ' - '.$w->getTypeString();
                },
                'choice_value' => 'id',

                // 👇 IMPORTANT
                'required' => true,
                'placeholder' => 'pas de mandant',
            ])

            ->add('initial_index_object', EntityType::class, [
                'required' => false,
                'class' => RevaluationHistory::class,
                'query_builder' => function (EntityRepository $er)  use($options){
                    if($options['data']->initial_index_object){
                        return $er->createQueryBuilder('rh')
                        ->where('rh.id = :id')
                        ->setParameter('id', $options['data']->initial_index_object)
                            ->orderBy('rh.id', 'DESC');
                    }else{
                        return $er->createQueryBuilder('rh')
                        ->where('rh.type LIKE :key1 or rh.type LIKE :key2')
                        ->setParameter('key1', "Urbains")
                        ->setParameter('key2', "Ménages")
                            ->orderBy('rh.id', 'DESC');
                    }
                    
                },
                'choice_label' => function (RevaluationHistory $rh): string {
                    return $rh->getValue().' '.$rh->getType().' mois de '.(strftime('%B %Y',$rh->getDate()->getTimestamp()));
                },
                'choice_value' => 'id',
            ])
            ->add('mail2', EmailType::class, ['required' => false])

           

            //->add('revaluationIndex', TextType::class, ['required' => false])

           
            ->add('dosAuthenticInstrument', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y'))])
            ->add('startDateManagement', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 2)])
            //->add('endDateManagement', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 25)])
    

            ->add('mandataire', ChoiceType::class, ['choices' => array_flip(Property::TYPES_MANDATAIRES), 'choice_translation_domain' => false])
            ->add('sell_type', ChoiceType::class, ['choices' => array_flip(Property::LIFETIME_TYPES), 'choice_translation_domain' => false])
            ->add('civilite1', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('civilite2', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('dernier_jour_paiement_rente', DayType::class)
            ->add('date_remise_cles', DateType::class,  ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 6)])

            ->add('civilite_notaire', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('nom_notaire', TextType::class, ['required' => false])
            ->add('prenom_notaire', TextType::class, ['required' => false])
            ->add('addresse_notaire', TextType::class, ['required' => false])
            ->add('code_postal_notaire', TextType::class, ['required' => false])
            ->add('ville_notaire', TextType::class, ['required' => false])
            ->add('telephone_notaire', TextType::class, ['required' => false])
            ->add('email_notaire', TextType::class, ['required' => false])

            ->add('civilite_debirentier', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('nom_debirentier', TextType::class, ['required' => false])
            ->add('prenom_debirentier', TextType::class, ['required' => false])
            ->add('addresse_debirentier', TextType::class, ['required' => false])
            ->add('code_postal_debirentier', TextType::class, ['required' => false])
            ->add('pays_debirentier', TextType::class, ['required' => false])
            ->add('ville_debirentier', TextType::class, ['required' => false])
            ->add('telephone_debirentier', TextType::class, ['required' => false])
            ->add('email_debirentier', TextType::class, ['required' => false])

            ->add('civilite_debirentier2', ChoiceType::class, ['choices' => array_flip(Property::TYPES_CIVILITE), 'choice_translation_domain' => false])
            ->add('nom_debirentier2', TextType::class, ['required' => false])
            ->add('prenom_debirentier2', TextType::class, ['required' => false])
            ->add('addresse_debirentier2', TextType::class, ['required' => false])
            ->add('code_postal_debirentier2', TextType::class, ['required' => false])
            ->add('pays_debirentier2', TextType::class, ['required' => false])
            ->add('ville_debirentier2', TextType::class, ['required' => false])
            ->add('telephone_debirentier2', TextType::class, ['required' => false])
            ->add('email_debirentier2', TextType::class, ['required' => false])
            
            
             ->add('buyer_phone1', TextType::class, ['required' => false])
 ->add('buyer_phone2', TextType::class, ['required' => false])
                        ->add('propertyType', ChoiceType::class, ['choices' => array_flip(Property::GOOD_TYPES), 'choice_translation_domain' => false])
 // ->add('good_address', TextType::class, ['required' => false])
  ->add('ref_cadastrales', TextType::class, ['required' => false])
            ->add('lots_copropriete', TextType::class, ['required' => false])
   ->add('assurance_habitation', CheckboxType::class, ['required' => false])
            ->add('date_assurance_habitation', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy','years' => range(date("Y")-5, date("Y")+7) ])
->add('chaudiere', CheckboxType::class, ['required' => false])
            ->add('fireplace', CheckboxType::class, ['required' => false])
            ->add('climatisation_pompe_chaleur', CheckboxType::class, ['required' => false])
            ->add('date_chaudiere', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy','years' => range(date("Y")-5, date("Y")+7) ])
            ->add('date_cheminee', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy','years' => range(date("Y")-5, date("Y")+7) ])
            ->add('date_climatisation', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy','years' => range(date("Y")-5, date("Y")+7) ])
 ->add('billingDisabled', CheckboxType::class, ['required' => false])
            ->add('plafonnement_index_og2_i', TextType::class, ['required' => false])
   ->add('pas_de_baisse', CheckboxType::class, ['required' => false])
     ->add('dernier_jour_paiement_rente', DayType::class)
             ->add('clause_OG2I', CheckboxType::class, ['required' => false])
            ->add('indexation_OG2I', CheckboxType::class, ['required' => false])
 ->add('mois_indice_ref_og2_i', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', ])
            ->add('valeur_indice_ref_og2_i_object', EntityType::class, ['required' => false,
                'class' => RevaluationHistory::class,
                'query_builder' => function (EntityRepository $er) use($options){
                    if($options['data']->valeur_indice_ref_og2_i_object){
                        return $er->createQueryBuilder('rh')
                        ->where('rh.type LIKE :key')
                        ->setParameter('key', "OGI")
                            ->orderBy('rh.id', 'DESC');
                    }else{
                        return $er->createQueryBuilder('rh')
                        ->where('rh.type LIKE :key')
                        ->setParameter('key', "OGI")
                            ->orderBy('rh.id', 'DESC');
                    }
                
                },
                'choice_label' => function (RevaluationHistory $rh): string {
                    return $rh->getValue().' mois de '.utf8_encode(strftime('%B %Y',$rh->getDate()->getTimestamp()));
                },
                'choice_value' => 'id',
                'required' => false,
                'placeholder' => '— Aucun —',
            ])
    ->add('acte_abandon_duh_drive_id', TextareaType::class, ['required' => false])
 ->add('pourcentage_revaluation_rente', TextType::class, ['required' => false])
  ->add('syndic_nom', TextType::class, ['required' => false])
            ->add('syndic_mail', TextType::class, ['required' => false])
            ->add('syndic_phone', TextType::class, ['required' => false])
            ->add('syndic_address', TextType::class, ['required' => false])
            ->add('syndic_postal_code', TextType::class, ['required' => false])
            ->add('syndic_ville', TextType::class, ['required' => false])
            ->add('syndic_id', TextType::class, ['required' => false])
            ->add('syndic_password', PasswordType::class, ['required' => false])
            ->add('date_reg_fin', DayType::class, )
            ->add('date_reg_debut', DayType::class, )
            ->add('syndic_quote_part', TextType::class, ['required' => false])
            ->add('syndic_dernier_decompte', TextType::class, ['required' => false])
   ->add('no_indexation', CheckboxType::class, ['required' => false])
            ->add('annuitiesDisabled', CheckboxType::class, ['required' => false])
            ->add('honorariesDisabled', CheckboxType::class, ['required' => false])
             ->add('hide_export_monthly', CheckboxType::class, ['required' => false])
            ->add('hide_export_otp', CheckboxType::class, ['required' => false])
            ->add('hide_honorary_export', CheckboxType::class, ['required' => false])
            ->add('hide_export_quarterly', CheckboxType::class, ['required' => false])
            ->add('condominiumFees', TextType::class, ['required' => false])
            ->add('garbageTax', TextType::class, ['required' => false])





            
            ;
           

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
